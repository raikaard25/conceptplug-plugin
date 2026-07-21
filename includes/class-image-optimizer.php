<?php
/**
 * Image optimizer — local WebP (not IP).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_Image_Optimizer
 */
class ConceptPlug_Image_Optimizer {

	const META_OPTIMIZED = '_cp_wc_optimized';
	const META_SOURCE_ID = '_cp_wc_optimizer_source_id';
	const META_VERSION   = '_cp_wc_optimizer_version';
	const META_DERIVATIVE_ID = '_cp_wc_optimizer_derivative_id';
	const META_SIGNATURE     = '_cp_wc_optimizer_signature';
	const VERSION        = '2';

	/**
	 * Get optimizer options from WooCommerce settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_options() {
		$settings = class_exists( 'ConceptPlug_WooCommerce_Settings' ) ? ConceptPlug_WooCommerce_Settings::get() : array();
		return array(
			'enabled'   => ! empty( $settings['optimize_webp'] ),
			'quality'   => max( 50, min( 100, (int) ( $settings['webp_quality'] ?? 82 ) ) ),
			'max_width' => max( 800, min( 4000, (int) ( $settings['max_image_width'] ?? 1600 ) ) ),
			'slug'      => '',
			'keyword'   => '',
			'index'     => 1,
		);
	}

	/**
	 * Optimize attachment to WebP.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $options       Options.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function optimize( $attachment_id, array $options = array() ) {
		$opts = wp_parse_args( $options, self::get_options() );

		if ( empty( $opts['enabled'] ) ) {
			return array(
				'attachment_id' => $attachment_id,
				'saved_bytes'   => 0,
				'format'        => 'skipped',
			);
		}

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error( 'cp_opt_invalid_attachment', __( 'Invalid image attachment.', 'conceptplug' ) );
		}
		if ( get_post_meta( $attachment_id, self::META_OPTIMIZED, true ) ) {
			return array(
				'attachment_id' => $attachment_id,
				'saved_bytes'   => (int) get_post_meta( $attachment_id, '_cp_wc_saved_bytes', true ),
				'format'        => 'already-optimized',
				'source_id'     => (int) get_post_meta( $attachment_id, self::META_SOURCE_ID, true ),
			);
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'cp_opt_no_file', __( 'Image file not found.', 'conceptplug' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$original_size = filesize( $file_path );
		$editor        = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$size = $editor->get_size();
		if ( ! empty( $size['width'] ) && $size['width'] > $opts['max_width'] ) {
			$editor->resize( $opts['max_width'], null, false );
		}

		$source_mime = (string) get_post_mime_type( $attachment_id );
		$preferred   = apply_filters( 'conceptplug_optimizer_mime', 'image/webp', $attachment_id, $opts );
		if ( ! in_array( $preferred, array( 'image/webp', 'image/avif' ), true ) || ! $editor->supports_mime_type( $preferred ) ) {
			$preferred = $editor->supports_mime_type( 'image/webp' ) ? 'image/webp' : $source_mime;
		}
		$mime = in_array( $preferred, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) ? $preferred : $source_mime;
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) ) {
			return new WP_Error( 'cp_opt_unsupported', __( 'This server cannot create a safe optimized copy of the image.', 'conceptplug' ) );
		}
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		);
		$format  = $extensions[ $mime ];
		$quality = 'image/png' === $mime ? 90 : (int) $opts['quality'];
		$signature = hash(
			'sha256',
			implode(
				'|',
				array(
					self::VERSION,
					$mime,
					(string) $quality,
					(string) $opts['max_width'],
					(string) $original_size,
					(string) filemtime( $file_path ),
				)
			)
		);
		$existing_id = (int) get_post_meta( $attachment_id, self::META_DERIVATIVE_ID, true );
		if (
			$existing_id
			&& 'attachment' === get_post_type( $existing_id )
			&& wp_attachment_is_image( $existing_id )
			&& hash_equals( $signature, (string) get_post_meta( $existing_id, self::META_SIGNATURE, true ) )
		) {
			return array(
				'attachment_id' => $existing_id,
				'source_id'     => $attachment_id,
				'saved_bytes'   => (int) get_post_meta( $existing_id, '_cp_wc_saved_bytes', true ),
				'format'        => 'already-optimized',
			);
		}
		$editor->set_quality( $quality );

		$upload_dir = wp_upload_dir();
		$slug_part  = ! empty( $opts['slug'] ) ? sanitize_title( $opts['slug'] ) : 'product';
		$keyword    = ! empty( $opts['keyword'] ) ? sanitize_title( $opts['keyword'] ) : '';
		$index      = max( 1, (int) $opts['index'] );
		$name_parts = array_filter( array( $slug_part, $keyword, (string) $index ) );
		$filename   = sanitize_file_name( implode( '-', $name_parts ) . '-optimized.' . $format );
		$filename   = wp_unique_filename( $upload_dir['path'], $filename );
		$new_path   = trailingslashit( $upload_dir['path'] ) . $filename;

		$saved = $editor->save( $new_path, $mime );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$new_path = $saved['path'];
		$new_size = filesize( $new_path );

		if ( false === $new_size || $new_size <= 0 ) {
			wp_delete_file( $new_path );
			return new WP_Error( 'cp_opt_empty', __( 'The optimized image copy is invalid.', 'conceptplug' ) );
		}
		if ( $new_size >= $original_size && ( empty( $size['width'] ) || $size['width'] <= $opts['max_width'] ) ) {
			wp_delete_file( $new_path );
			return array(
				'attachment_id' => $attachment_id,
				'saved_bytes'   => 0,
				'format'        => 'skipped-larger',
			);
		}

		$source_post = get_post( $attachment_id );
		$new_id      = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => $source_post ? $source_post->post_title : pathinfo( $filename, PATHINFO_FILENAME ),
				'post_excerpt'   => $source_post ? $source_post->post_excerpt : '',
				'post_content'   => $source_post ? $source_post->post_content : '',
				'post_status'    => 'inherit',
				'post_parent'    => $source_post ? (int) $source_post->post_parent : 0,
				'guid'           => trailingslashit( $upload_dir['url'] ) . basename( $new_path ),
			),
			$new_path
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			wp_delete_file( $new_path );
			return is_wp_error( $new_id ) ? $new_id : new WP_Error( 'cp_opt_attachment', __( 'The optimized image attachment could not be created.', 'conceptplug' ) );
		}

		$metadata = wp_generate_attachment_metadata( $new_id, $new_path );
		if ( is_wp_error( $metadata ) ) {
			wp_delete_attachment( $new_id, true );
			return $metadata;
		}
		wp_update_attachment_metadata( $new_id, $metadata );
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' !== (string) $alt ) {
			update_post_meta( $new_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		$saved_bytes = max( 0, (int) $original_size - (int) $new_size );
		update_post_meta( $new_id, self::META_OPTIMIZED, 1 );
		update_post_meta( $new_id, self::META_SOURCE_ID, $attachment_id );
		update_post_meta( $new_id, self::META_VERSION, self::VERSION );
		update_post_meta( $new_id, self::META_SIGNATURE, $signature );
		update_post_meta( $new_id, '_cp_wc_saved_bytes', $saved_bytes );
		update_post_meta( $attachment_id, self::META_DERIVATIVE_ID, $new_id );

		return array(
			'attachment_id' => (int) $new_id,
			'source_id'     => $attachment_id,
			'saved_bytes'   => $saved_bytes,
			'format'        => $format,
		);
	}

	/**
	 * Resolve the untouched source attachment for an optimized copy.
	 *
	 * The optimized file is retained until WordPress or the customer deletes it.
	 *
	 * @param int $attachment_id Optimized attachment ID.
	 * @return int|WP_Error Source attachment ID.
	 */
	public static function revert_attachment_id( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$source_id     = (int) get_post_meta( $attachment_id, self::META_SOURCE_ID, true );
		if ( ! $source_id || 'attachment' !== get_post_type( $source_id ) || ! wp_attachment_is_image( $source_id ) ) {
			return new WP_Error( 'cp_opt_no_source', __( 'The original image is no longer available.', 'conceptplug' ) );
		}
		return $source_id;
	}

	/**
	 * Optimize many attachments.
	 *
	 * @param array<int>           $attachment_ids IDs.
	 * @param array<string, mixed> $options        Options.
	 * @return array<int, int>
	 */
	public static function optimize_many( array $attachment_ids, array $options = array() ) {
		$result = array();
		$index  = 1;
		foreach ( $attachment_ids as $attach_id ) {
			$attach_id = (int) $attach_id;
			if ( ! $attach_id ) {
				continue;
			}
			$out                  = self::optimize( $attach_id, array_merge( $options, array( 'index' => $index ) ) );
			$result[ $attach_id ] = is_wp_error( $out ) ? $attach_id : (int) $out['attachment_id'];
			++$index;
		}
		return $result;
	}
}
