<?php
/**
 * Per-user persistence for resumable ConceptPlug AI jobs.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores only job metadata and sanitized UI context. Provider payloads and image
 * bytes stay in the API/object store and are fetched only when a result is used.
 */
class ConceptPlug_WooCommerce_Ai_Job_Store {

	const META_KEY        = '_conceptplug_ai_jobs_v2';
	const RETENTION       = 7 * DAY_IN_SECONDS;
	const MAX_JOBS        = 30;
	const RESULT_LOCK_TTL = 120;

	/** Validate an API job UUID. */
	public static function valid_job_id( $job_id ) {
		return is_string( $job_id ) && (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $job_id );
	}

	/** Register a newly accepted job for the current WordPress user. */
	public static function register( array $job, $request_id, $catalog_version, array $context ) {
		$job_id = sanitize_text_field( $job['job_id'] ?? '' );
		if ( ! self::valid_job_id( $job_id ) ) {
			return new WP_Error( 'cp_wc_job_invalid', __( 'ConceptPlug returned an invalid AI job identifier.', 'conceptplug' ) );
		}

		$jobs            = self::read();
		$jobs[ $job_id ] = array_merge(
			self::remote_fields( $job ),
			array(
				'job_id'               => $job_id,
				'request_id'            => sanitize_text_field( $request_id ),
				'catalog_version'       => sanitize_text_field( $catalog_version ),
				'context'               => self::sanitize_context( $context ),
				'created_timestamp'     => time(),
				'updated_timestamp'     => time(),
				'result_attachment_id'  => 0,
				'delivered'             => false,
			)
		);
		self::write( $jobs );
		return $jobs[ $job_id ];
	}

	/** Find a job owned by the current WordPress user. */
	public static function find( $job_id ) {
		if ( ! self::valid_job_id( $job_id ) ) {
			return null;
		}
		$jobs = self::read();
		return isset( $jobs[ $job_id ] ) && is_array( $jobs[ $job_id ] ) ? $jobs[ $job_id ] : null;
	}

	/** Merge fresh status/balance metadata returned by the API. */
	public static function update_remote( $job_id, array $remote ) {
		$jobs = self::read();
		if ( empty( $jobs[ $job_id ] ) || ! is_array( $jobs[ $job_id ] ) ) {
			return null;
		}
		$jobs[ $job_id ] = array_merge( $jobs[ $job_id ], self::remote_fields( $remote ) );
		$jobs[ $job_id ]['updated_timestamp'] = time();
		self::write( $jobs );
		return $jobs[ $job_id ];
	}

	/** Record a derivative attachment so repeated polls never download it twice. */
	public static function set_result_attachment( $job_id, $attachment_id ) {
		$jobs = self::read();
		if ( empty( $jobs[ $job_id ] ) || ! is_array( $jobs[ $job_id ] ) ) {
			return null;
		}
		$jobs[ $job_id ]['result_attachment_id'] = absint( $attachment_id );
		$jobs[ $job_id ]['updated_timestamp']    = time();
		self::write( $jobs );
		return $jobs[ $job_id ];
	}

	/** Mark a result as integrated by the browser while retaining an audit breadcrumb. */
	public static function acknowledge( $job_id ) {
		$jobs = self::read();
		if ( empty( $jobs[ $job_id ] ) || ! is_array( $jobs[ $job_id ] ) ) {
			return false;
		}
		$jobs[ $job_id ]['delivered']         = true;
		$jobs[ $job_id ]['updated_timestamp'] = time();
		self::write( $jobs );
		return true;
	}

	/** Jobs still requiring polling or browser delivery. */
	public static function pending() {
		$jobs = self::read();
		return array_values(
			array_filter(
				$jobs,
				static function ( $job ) {
					return is_array( $job ) && empty( $job['delivered'] );
				}
			)
		);
	}

	/** Acquire a short lock before converting an image result into an attachment. */
	public static function acquire_result_lock( $job_id ) {
		$key      = self::lock_key( $job_id );
		$existing = (int) get_option( $key, 0 );
		if ( $existing && ( time() - $existing ) > self::RESULT_LOCK_TTL ) {
			delete_option( $key );
		}
		return add_option( $key, time(), '', false );
	}

	/** Release an image result lock. */
	public static function release_result_lock( $job_id ) {
		delete_option( self::lock_key( $job_id ) );
	}

	/** Read, normalize, and prune the current user's job index. */
	private static function read() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$jobs = get_user_meta( $user_id, self::META_KEY, true );
		$jobs = is_array( $jobs ) ? $jobs : array();
		$now  = time();
		foreach ( $jobs as $job_id => $job ) {
			$created = is_array( $job ) ? (int) ( $job['created_timestamp'] ?? 0 ) : 0;
			if ( ! self::valid_job_id( $job_id ) || ! $created || ( $now - $created ) > self::RETENTION ) {
				unset( $jobs[ $job_id ] );
			}
		}
		return $jobs;
	}

	/** Persist a bounded newest-first index. */
	private static function write( array $jobs ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		uasort(
			$jobs,
			static function ( $left, $right ) {
				return (int) ( $right['updated_timestamp'] ?? 0 ) <=> (int) ( $left['updated_timestamp'] ?? 0 );
			}
		);
		$jobs = array_slice( $jobs, 0, self::MAX_JOBS, true );
		update_user_meta( $user_id, self::META_KEY, $jobs );
	}

	/** Fields safe to cache from a remote AiJob response. */
	private static function remote_fields( array $job ) {
		$allowed_statuses = array( 'queued', 'running', 'succeeded', 'failed', 'canceled' );
		$status           = sanitize_key( $job['status'] ?? 'queued' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'queued';
		}
		return array(
			'status'           => $status,
			'operation'        => sanitize_key( $job['operation'] ?? '' ),
			'pricing_version'  => sanitize_text_field( $job['pricing_version'] ?? '' ),
			'credits_reserved' => max( 0, (int) ( $job['credits_reserved'] ?? 0 ) ),
			'credits_used'     => max( 0, (int) ( $job['credits_used'] ?? 0 ) ),
			'credits'          => isset( $job['credits'] ) ? max( 0, (int) $job['credits'] ) : null,
			'correlation_id'   => sanitize_text_field( $job['correlation_id'] ?? '' ),
			'error_code'       => sanitize_key( $job['error_code'] ?? '' ),
			'result_expires_at'=> sanitize_text_field( $job['result_expires_at'] ?? '' ),
		);
	}

	/** Strictly bound UI context persisted alongside a job. */
	private static function sanitize_context( array $context ) {
		$surface = sanitize_key( $context['surface'] ?? '' );
		if ( ! in_array( $surface, array( 'create', 'enhance' ), true ) ) {
			$surface = 'create';
		}
		$input = is_array( $context['input'] ?? null ) ? $context['input'] : array();
		$selected_fields = array_intersect(
			array_map( 'sanitize_key', (array) ( $context['selected_fields'] ?? array() ) ),
			array( 'title', 'slug', 'short_description', 'long_description', 'meta_description', 'focus_keyword', 'tags', 'image_alts', 'featured_image', 'gallery_images', 'category' )
		);
		return array(
			'kind'                 => in_array( $context['kind'] ?? '', array( 'content', 'image' ), true ) ? $context['kind'] : 'content',
			'surface'              => $surface,
			'product_id'           => absint( $context['product_id'] ?? 0 ),
			'source_attachment_id' => absint( $context['source_attachment_id'] ?? 0 ),
			'product_name'         => sanitize_text_field( $context['product_name'] ?? '' ),
			'selected_fields'      => array_values( array_unique( $selected_fields ) ),
			'input'                => array(
				'product_name'  => sanitize_text_field( $input['product_name'] ?? '' ),
				'brief_details' => sanitize_textarea_field( $input['brief_details'] ?? '' ),
				'focus_keyword' => sanitize_text_field( $input['focus_keyword'] ?? '' ),
				'regular_price' => sanitize_text_field( $input['regular_price'] ?? '' ),
				'sale_price'    => sanitize_text_field( $input['sale_price'] ?? '' ),
				'category_id'   => absint( $input['category_id'] ?? 0 ),
				'language'      => in_array( $input['language'] ?? '', array( 'en', 'th' ), true ) ? $input['language'] : 'en',
				'image_ids'     => array_slice( array_values( array_filter( array_map( 'absint', (array) ( $input['image_ids'] ?? array() ) ) ) ), 0, 5 ),
			),
		);
	}

	/** Non-secret lock key scoped to a user and job. */
	private static function lock_key( $job_id ) {
		return '_cp_ai_job_lock_' . get_current_user_id() . '_' . substr( hash( 'sha256', $job_id ), 0, 24 );
	}
}
