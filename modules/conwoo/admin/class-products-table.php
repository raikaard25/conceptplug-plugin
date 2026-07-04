<?php
/**
 * WP_List_Table for ConWoo-generated products.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class ConWoo_Products_Table
 */
class ConWoo_Products_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'conwoo_product',
				'plural'   => 'conwoo_products',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'thumb'      => __( 'Image', 'conceptplug' ),
			'title'      => __( 'Product', 'conceptplug' ),
			'status'     => __( 'Status', 'conceptplug' ),
			'price'      => __( 'Price', 'conceptplug' ),
			'seo_score'  => __( 'SEO Score', 'conceptplug' ),
			'created'    => __( 'Created', 'conceptplug' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'title'     => array( 'title', false ),
			'status'    => array( 'status', false ),
			'seo_score' => array( 'seo_score', true ),
			'created'   => array( 'date', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array();
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		// These GET values only control list-table filtering and sorting.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$order    = isset( $_GET['order'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['order'] ) ) ? 'ASC' : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The generated-product marker is required for this admin list.
				array(
					'key'   => '_conwoo_generated',
					'value' => '1',
				),
			),
			'orderby'        => 'date',
			'order'          => $order,
		);

		if ( $search ) {
			$args['s'] = $search;
		}

		if ( 'title' === $orderby ) {
			$args['orderby'] = 'title';
		} elseif ( 'status' === $orderby ) {
			$args['orderby'] = 'post_status';
		} elseif ( 'seo_score' === $orderby ) {
			$args['meta_key'] = '_conwoo_seo_score'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Requested SEO-score sorting requires this indexed-sized result set.
			$args['orderby']  = 'meta_value_num';
		}

		$query = new WP_Query( $args );
		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => (int) $query->max_num_pages,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Default column output.
	 *
	 * @param WP_Post $item        Item.
	 * @param string  $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Checkbox column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="product_ids[]" value="%d" />', (int) $item->ID );
	}

	/**
	 * Thumbnail column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_thumb( $item ) {
		$thumb = get_the_post_thumbnail( $item->ID, array( 50, 50 ) );
		if ( ! $thumb ) {
			return '<span class="conwoo-no-thumb">—</span>';
		}
		return $thumb;
	}

	/**
	 * Title column with row actions.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_title( $item ) {
		$edit_url = get_edit_post_link( $item->ID, 'raw' );
		$view_url = get_permalink( $item->ID );
		$title    = esc_html( get_the_title( $item ) );

		$actions = array(
			'view'       => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'conceptplug' ) ),
			'edit'       => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'conceptplug' ) ),
			'seo_report' => sprintf(
				'<a href="#" class="conwoo-toggle-seo-report" data-product-id="%d">%s</a>',
				(int) $item->ID,
				esc_html__( 'SEO Report', 'conceptplug' )
			),
			'reanalyze'  => sprintf(
				'<a href="#" class="conwoo-reanalyze-one" data-product-id="%d">%s</a>',
				(int) $item->ID,
				esc_html__( 'Re-analyze', 'conceptplug' )
			),
		);

		return sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>%3$s<div class="conwoo-seo-report-panel" id="conwoo-seo-report-%4$d" hidden></div>',
			esc_url( $edit_url ),
			$title,
			$this->row_actions( $actions ),
			(int) $item->ID
		);
	}

	/**
	 * Status column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_status( $item ) {
		$status = get_post_status( $item );
		$labels = array(
			'publish' => __( 'Published', 'conceptplug' ),
			'draft'   => __( 'Draft', 'conceptplug' ),
			'pending' => __( 'Pending', 'conceptplug' ),
			'private' => __( 'Private', 'conceptplug' ),
		);
		return esc_html( $labels[ $status ] ?? $status );
	}

	/**
	 * Price column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_price( $item ) {
		$product = wc_get_product( $item->ID );
		if ( ! $product ) {
			return '—';
		}
		return wp_kses_post( $product->get_price_html() ?: '—' );
	}

	/**
	 * SEO score column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_seo_score( $item ) {
		$score = (int) get_post_meta( $item->ID, '_conwoo_seo_score', true );
		$grade = get_post_meta( $item->ID, '_conwoo_seo_grade', true );
		if ( ! $score && ! $grade ) {
			return '<span class="conwoo-score-badge conwoo-score-none">' . esc_html__( 'Not analyzed', 'conceptplug' ) . '</span>';
		}
		$class = ConWoo_Ajax_Handlers::score_class( $score );
		return sprintf(
			'<span class="conwoo-score-badge %1$s" data-score="%2$d"><span class="conwoo-score-num">%2$d</span><span class="conwoo-score-grade">%3$s</span></span>',
			esc_attr( $class ),
			$score,
			esc_html( $grade ?: ( $score >= 80 ? 'B' : ( $score >= 50 ? 'C' : 'F' ) ) )
		);
	}

	/**
	 * Created column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_created( $item ) {
		$generated = get_post_meta( $item->ID, '_conwoo_generated_at', true );
		$date      = $generated ? $generated : $item->post_date;
		return esc_html( mysql2date( get_option( 'date_format' ), $date ) );
	}

	/**
	 * Message when no items.
	 */
	public function no_items() {
		esc_html_e( 'No ConWoo-generated products yet. Create your first product!', 'conceptplug' );
	}
}
