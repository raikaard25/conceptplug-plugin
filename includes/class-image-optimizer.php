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

	/**
	 * Get optimizer options from ConWoo settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_options() {
		$settings = class_exists( 'ConWoo_Settings' ) ? ConWoo_Settings::get() : array();
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

		$use_webp = $editor->supports_mime_type( 'image/webp' );
		$format   = $use_webp ? 'webp' : 'jpeg';
		$mime     = $use_webp ? 'image/webp' : 'image/jpeg';
		$quality  = $use_webp ? $opts['quality'] : max( 75, $opts['quality'] + 3 );

		$upload_dir = wp_upload_dir();
		$slug_part  = ! empty( $opts['slug'] ) ? sanitize_title( $opts['slug'] ) : 'product';
		$keyword    = ! empty( $opts['keyword'] ) ? sanitize_title( $opts['keyword'] ) : '';
		$index      = max( 1, (int) $opts['index'] );
		$name_parts = array_filter( array( $slug_part, $keyword, (string) $index ) );
		$filename   = sanitize_file_name( implode( '-', $name_parts ) . '.' . $format );
		$new_path   = trailingslashit( $upload_dir['path'] ) . $filename;

		if ( file_exists( $new_path ) ) {
			$filename = sanitize_file_name( implode( '-', $name_parts ) . '-' . time() . '.' . $format );
			$new_path = trailingslashit( $upload_dir['path'] ) . $filename;
		}

		$saved = $editor->save( $new_path, $mime, $quality );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$new_path = $saved['path'];
		$new_size = filesize( $new_path );

		if ( $new_path !== $file_path && file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		update_attached_file( $attachment_id, $new_path );
		wp_update_post(
			array(
				'ID'             => $attachment_id,
				'post_mime_type' => $mime,
			)
		);

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $new_path ) );

		$saved_bytes = max( 0, (int) $original_size - (int) $new_size );
		update_post_meta( $attachment_id, '_conwoo_optimized', 1 );
		update_post_meta( $attachment_id, '_conwoo_saved_bytes', $saved_bytes );

		return array(
			'attachment_id' => $attachment_id,
			'saved_bytes'   => $saved_bytes,
			'format'        => $format,
		);
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
