<?php
/**
 * WP_List_Table for ConWoo-generated products.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

// List-table query parameters are read-only filters; its small metadata query is intentional.
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.DB.SlowDBQuery

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class ConWoo_Products_Table
 */
class ConWoo_Products_Table extends WP_List_Table {

	/**
	 * Active list filters.
	 *
	 * @var array<string, mixed>
	 */
	private $filters = array();

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
	 * Primary column for responsive list table (row actions live in title).
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'title';
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'thumb'        => __( 'Image', 'conceptplug' ),
			'title'        => __( 'Product', 'conceptplug' ),
			'categories'   => __( 'Category', 'conceptplug' ),
			'tags'         => __( 'Tags', 'conceptplug' ),
			'product_type' => __( 'Type', 'conceptplug' ),
			'status'       => __( 'Status', 'conceptplug' ),
			'price'        => __( 'Price', 'conceptplug' ),
			'seo_score'    => __( 'SEO Score', 'conceptplug' ),
			'created'      => __( 'Created', 'conceptplug' ),
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
		return array(
			'set_category'  => __( 'Set category', 'conceptplug' ),
			'add_tags'      => __( 'Add tags', 'conceptplug' ),
			'remove_tags'   => __( 'Remove tags', 'conceptplug' ),
			'change_status' => __( 'Change status', 'conceptplug' ),
		);
	}

	/**
	 * Read active filters from the request.
	 *
	 * @return array<string, mixed>
	 */
	private function get_filters() {
		if ( ! empty( $this->filters ) ) {
			return $this->filters;
		}

		$this->filters = array(
			'category'    => isset( $_REQUEST['category'] ) ? absint( wp_unslash( $_REQUEST['category'] ) ) : 0,
			'product_tag' => isset( $_REQUEST['product_tag'] ) ? absint( wp_unslash( $_REQUEST['product_tag'] ) ) : 0,
			'post_status' => isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : '',
		);

		return $this->filters;
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = isset( $_REQUEST['paged'] ) ? max( 1, absint( wp_unslash( $_REQUEST['paged'] ) ) ) : 1;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'date';
		$order    = isset( $_REQUEST['order'] ) && 'asc' === sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
		$filters  = $this->get_filters();

		$post_status = array( 'publish', 'draft', 'pending', 'private' );
		if ( ! empty( $filters['post_status'] ) && in_array( $filters['post_status'], $post_status, true ) ) {
			$post_status = array( $filters['post_status'] );
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'meta_query'     => array(
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
			$args['meta_key'] = '_conwoo_seo_score';
			$args['orderby']  = 'meta_value_num';
		}

		$tax_query = array();
		if ( ! empty( $filters['category'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => (int) $filters['category'],
			);
		}
		if ( ! empty( $filters['product_tag'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => (int) $filters['product_tag'],
			);
		}
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		$query       = new WP_Query( $args );
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
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$ids = isset( $_REQUEST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['product_ids'] ) ) : array();
		$ids = array_values( array_filter( $ids ) );
		if ( empty( $ids ) ) {
			return;
		}

		$updater = new ConWoo_Product_Updater();
		$result  = $updater->bulk_edit(
			$ids,
			$action,
			array(
				'category_ids' => isset( $_REQUEST['bulk_category_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['bulk_category_ids'] ) ) : array(),
				'tags'         => isset( $_REQUEST['bulk_tags'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bulk_tags'] ) ) : '',
				'status'       => isset( $_REQUEST['bulk_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['bulk_status'] ) ) : '',
			)
		);

		$redirect_args = array(
			'page' => 'conwoo-products',
		);

		$filters = $this->get_filters();
		if ( ! empty( $filters['category'] ) ) {
			$redirect_args['category'] = (int) $filters['category'];
		}
		if ( ! empty( $filters['product_tag'] ) ) {
			$redirect_args['product_tag'] = (int) $filters['product_tag'];
		}
		if ( ! empty( $filters['post_status'] ) ) {
			$redirect_args['post_status'] = $filters['post_status'];
		}
		if ( ! empty( $_REQUEST['s'] ) ) {
			$redirect_args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		if ( ! empty( $_REQUEST['paged'] ) ) {
			$redirect_args['paged'] = absint( wp_unslash( $_REQUEST['paged'] ) );
		}

		if ( is_wp_error( $result ) ) {
			$redirect_args['conwoo_bulk_error'] = rawurlencode( $result->get_error_message() );
		} else {
			$redirect_args['conwoo_bulk_updated'] = (int) ( $result['updated'] ?? 0 );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Extra controls above/below the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			$this->render_filter_controls();
			return;
		}

		if ( 'bottom' === $which ) {
			$this->render_bulk_extra_controls();
		}
	}

	/**
	 * Filter dropdowns.
	 */
	private function render_filter_controls() {
		$filters    = $this->get_filters();
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$tags       = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}
		if ( is_wp_error( $tags ) ) {
			$tags = array();
		}

		$statuses = array(
			''        => __( 'All statuses', 'conceptplug' ),
			'publish' => __( 'Published', 'conceptplug' ),
			'draft'   => __( 'Draft', 'conceptplug' ),
			'pending' => __( 'Pending', 'conceptplug' ),
			'private' => __( 'Private', 'conceptplug' ),
		);
		?>
		<div class="alignleft actions conwoo-products-filters">
			<label class="screen-reader-text" for="filter-by-category"><?php esc_html_e( 'Filter by category', 'conceptplug' ); ?></label>
			<select name="category" id="filter-by-category">
				<option value=""><?php esc_html_e( 'All categories', 'conceptplug' ); ?></option>
				<?php foreach ( $categories as $term ) : ?>
					<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( (int) $filters['category'], (int) $term->term_id ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="filter-by-tag"><?php esc_html_e( 'Filter by tag', 'conceptplug' ); ?></label>
			<select name="product_tag" id="filter-by-tag">
				<option value=""><?php esc_html_e( 'All tags', 'conceptplug' ); ?></option>
				<?php foreach ( $tags as $term ) : ?>
					<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( (int) $filters['product_tag'], (int) $term->term_id ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="filter-by-status"><?php esc_html_e( 'Filter by status', 'conceptplug' ); ?></label>
			<select name="post_status" id="filter-by-status">
				<?php foreach ( $statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['post_status'], $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'conceptplug' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Extra inputs for bulk actions.
	 */
	private function render_bulk_extra_controls() {
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		$statuses = array(
			'publish' => __( 'Published', 'conceptplug' ),
			'draft'   => __( 'Draft', 'conceptplug' ),
			'pending' => __( 'Pending', 'conceptplug' ),
			'private' => __( 'Private', 'conceptplug' ),
		);
		?>
		<div class="alignleft actions conwoo-bulk-extra" id="conwoo-bulk-extra" hidden>
			<div class="conwoo-bulk-field conwoo-bulk-field-category" hidden>
				<span class="conwoo-field-label"><?php esc_html_e( 'Categories', 'conceptplug' ); ?></span>
				<div class="conwoo-category-checklist">
					<?php foreach ( $categories as $term ) : ?>
						<label class="conwoo-category-option">
							<input type="checkbox" name="bulk_category_ids[]" value="<?php echo esc_attr( (string) $term->term_id ); ?>" />
							<?php echo esc_html( $term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="conwoo-bulk-field conwoo-bulk-field-tags" hidden>
				<label for="bulk_tags"><?php esc_html_e( 'Tags', 'conceptplug' ); ?></label>
				<input type="text" name="bulk_tags" id="bulk_tags" class="regular-text" list="conwoo-tag-suggestions" placeholder="<?php esc_attr_e( 'tag-one, tag-two', 'conceptplug' ); ?>" />
			</div>
			<div class="conwoo-bulk-field conwoo-bulk-field-status" hidden>
				<label for="bulk_status"><?php esc_html_e( 'Status', 'conceptplug' ); ?></label>
				<select name="bulk_status" id="bulk_status">
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<?php
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
		$attrs    = $this->get_quick_edit_attrs( $item );

		$actions = array(
			'view'       => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'conceptplug' ) ),
			'edit'       => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'conceptplug' ) ),
			'quick_edit' => sprintf(
				'<a href="#" class="conwoo-quick-edit-open"%1$s>%2$s</a>',
				$this->quick_edit_attr_string( $attrs ),
				esc_html__( 'Quick edit', 'conceptplug' )
			),
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

		$card_quick_edit = sprintf(
			'<button type="button" class="button button-small conwoo-card-quick-edit conwoo-quick-edit-open"%1$s>%2$s</button>',
			$this->quick_edit_attr_string( $attrs ),
			esc_html__( 'Quick edit', 'conceptplug' )
		);

		return sprintf(
			'<div class="conwoo-title-cell-wrap">%5$s<strong><a href="%1$s">%2$s</a></strong>%3$s<div class="conwoo-seo-report-panel" id="conwoo-seo-report-%4$d" hidden></div></div>',
			esc_url( $edit_url ),
			$title,
			$this->row_actions( $actions ),
			(int) $item->ID,
			$card_quick_edit
		);
	}

	/**
	 * Categories column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_categories( $item ) {
		$attrs = $this->get_quick_edit_attrs( $item );
		return sprintf(
			'<button type="button" class="conwoo-quick-edit-cell" data-focus="category"%1$s>%2$s</button>',
			$this->quick_edit_attr_string( $attrs ),
			ConWoo_Product_Updater::render_categories_cell( $item->ID )
		);
	}

	/**
	 * Tags column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_tags( $item ) {
		$attrs = $this->get_quick_edit_attrs( $item );
		return sprintf(
			'<button type="button" class="conwoo-quick-edit-cell" data-focus="tags"%1$s>%2$s</button>',
			$this->quick_edit_attr_string( $attrs ),
			ConWoo_Product_Updater::render_tags_cell( $item->ID )
		);
	}

	/**
	 * Product type column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_product_type( $item ) {
		return ConWoo_Product_Updater::render_product_type_cell( $item->ID );
	}

	/**
	 * Status column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_status( $item ) {
		$attrs = $this->get_quick_edit_attrs( $item );
		return sprintf(
			'<button type="button" class="conwoo-quick-edit-cell conwoo-quick-edit-status" data-focus="status"%1$s>%2$s</button>',
			$this->quick_edit_attr_string( $attrs ),
			ConWoo_Product_Updater::render_status_cell( $item->ID )
		);
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
	 * Build quick-edit data attributes for a row.
	 *
	 * @param WP_Post $item Item.
	 * @return array<string, mixed>
	 */
	private function get_quick_edit_attrs( $item ) {
		$product      = wc_get_product( $item->ID );
		$category_ids = wp_get_post_terms( $item->ID, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $category_ids ) ) {
			$category_ids = array();
		}

		return array(
			'product-id'     => (int) $item->ID,
			'category-ids'   => implode( ',', array_map( 'intval', $category_ids ) ),
			'tags'           => $this->get_tag_names_csv( $item->ID ),
			'status'         => get_post_status( $item ),
			'product-type'   => $product ? $product->get_type() : 'simple',
			'virtual'        => $product && $product->is_virtual() ? '1' : '0',
			'downloadable'   => $product && $product->is_downloadable() ? '1' : '0',
			'edit-url'       => get_edit_post_link( $item->ID, 'raw' ),
		);
	}

	/**
	 * Render quick-edit data attributes as HTML string.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	private function quick_edit_attr_string( array $attrs ) {
		$html = '';
		foreach ( $attrs as $key => $value ) {
			$html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
		}
		return $html;
	}

	/**
	 * Get comma-separated tag names.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function get_tag_names_csv( $product_id ) {
		$terms = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return implode( ', ', array_map( 'sanitize_text_field', $terms ) );
	}

	/**
	 * Render row columns with data-colname for responsive card layout.
	 *
	 * @param WP_Post $item Item.
	 */
	protected function single_row_columns( $item ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}

			if ( in_array( $column_name, $hidden, true ) ) {
				$classes .= ' hidden';
			}

			$label      = wp_strip_all_tags( $column_display_name );
			$attributes = "class='" . esc_attr( $classes ) . "' data-colname='" . esc_attr( $label ) . "'";

			if ( 'cb' === $column_name ) {
				echo '<th scope="row" class="check-column" data-colname="' . esc_attr( $label ) . '">';
				echo $this->column_cb( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</th>';
			} elseif ( method_exists( $this, 'column_' . $column_name ) ) {
				echo "<td $attributes>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo call_user_func( array( $this, 'column_' . $column_name ), $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</td>';
			} else {
				echo "<td $attributes>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->column_default( $item, $column_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</td>';
			}
		}
	}

	/**
	 * Message when no items.
	 */
	public function no_items() {
		esc_html_e( 'No ConWoo-generated products yet. Create your first product!', 'conceptplug' );
	}
}
