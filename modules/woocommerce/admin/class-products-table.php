<?php
/**
 * WP_List_Table for WooCommerce-generated products.
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
 * Class ConceptPlug_WooCommerce_Products_Table
 */
class ConceptPlug_WooCommerce_Products_Table extends WP_List_Table {

	/**
	 * Active list filters.
	 *
	 * @var array<string, mixed>
	 */
	private $filters = array();

	/**
	 * Cached WooCommerce product IDs for filter term lookups.
	 *
	 * @var array<int, int>|null
	 */
	private $cp_woocommerce_product_ids = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'cp_woocommerce_product',
				'plural'   => 'cp_woocommerce_products',
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
			'source'       => __( 'Source', 'conceptplug' ),
			'categories'   => __( 'Category', 'conceptplug' ),
			'tags'         => __( 'Tags', 'conceptplug' ),
			'product_type' => __( 'Type', 'conceptplug' ),
			'status'       => __( 'Status', 'conceptplug' ),
			'price'        => __( 'Price', 'conceptplug' ),
			'seo_score'    => __( 'SEO Score', 'conceptplug' ),
			'created'      => __( 'Date', 'conceptplug' ),
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
			'set_category'     => __( 'Set category', 'conceptplug' ),
			'add_tags'         => __( 'Add tags', 'conceptplug' ),
			'change_status'    => __( 'Change status', 'conceptplug' ),
			'enhance_selected' => __( 'Enhance with AI', 'conceptplug' ),
		);
	}

	/**
	 * Post statuses shown in the products list.
	 *
	 * @return array<int, string>
	 */
	private function get_list_post_statuses() {
		return array( 'publish', 'draft', 'pending', 'private' );
	}

	/**
	 * Status labels for filters and columns.
	 *
	 * @return array<string, string>
	 */
	private function get_status_labels() {
		return array(
			'publish' => __( 'Published', 'conceptplug' ),
			'draft'   => __( 'Draft', 'conceptplug' ),
			'pending' => __( 'Pending', 'conceptplug' ),
			'private' => __( 'Private', 'conceptplug' ),
		);
	}

	/**
	 * Sanitize status filter input.
	 *
	 * @param string $raw Raw status.
	 * @return string
	 */
	private function get_allowed_filter_status( $raw ) {
		if ( is_array( $raw ) ) {
			return '';
		}
		$status = sanitize_key( (string) $raw );
		return in_array( $status, $this->get_list_post_statuses(), true ) ? $status : '';
	}

	/**
	 * Source tab filter keys.
	 *
	 * @return array<string, string>
	 */
	private function get_source_tab_labels() {
		return array(
			'all'       => __( 'All products', 'conceptplug' ),
			'created'   => __( 'Created by ConceptPlug', 'conceptplug' ),
			'enhanced'  => __( 'Enhanced by ConceptPlug', 'conceptplug' ),
			'untouched' => __( 'Not yet enhanced', 'conceptplug' ),
		);
	}

	/**
	 * Sanitize source tab filter.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	private function get_allowed_source_tab( $raw ) {
		$tab = sanitize_key( (string) $raw );
		return array_key_exists( $tab, $this->get_source_tab_labels() ) ? $tab : 'all';
	}

	/**
	 * Build meta_query for source tab.
	 *
	 * @param string $source_tab Tab key.
	 * @return array<int, array<string, mixed>>|null
	 */
	private function get_source_meta_query( $source_tab ) {
		switch ( $source_tab ) {
			case 'created':
				return array(
					array(
						'key'   => '_cp_wc_generated',
						'value' => '1',
					),
				);
			case 'enhanced':
				return array(
					'relation' => 'AND',
					array(
						'key'   => '_cp_wc_enhanced',
						'value' => '1',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_cp_wc_generated',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_cp_wc_generated',
							'value'   => '1',
							'compare' => '!=',
						),
					),
				);
			case 'untouched':
				return array(
					'relation' => 'AND',
					array(
						'relation' => 'OR',
						array(
							'key'     => '_cp_wc_generated',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_cp_wc_generated',
							'value'   => '1',
							'compare' => '!=',
						),
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_cp_wc_enhanced',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_cp_wc_enhanced',
							'value'   => '1',
							'compare' => '!=',
						),
					),
				);
			default:
				return null;
		}
	}

	/**
	 * All WooCommerce-generated product IDs (cached per request).
	 *
	 * @return array<int, int>
	 */
	private function get_cp_wc_product_ids() {
		if ( null !== $this->cp_woocommerce_product_ids ) {
			return $this->cp_woocommerce_product_ids;
		}

		$cached = get_transient( 'cp_woocommerce_product_ids_v1' );
		if ( is_array( $cached ) ) {
			$this->cp_woocommerce_product_ids = array_map( 'intval', $cached );
			return $this->cp_woocommerce_product_ids;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => $this->get_list_post_statuses(),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_cp_wc_generated',
						'value' => '1',
					),
				),
			)
		);

		$this->cp_woocommerce_product_ids = is_array( $query->posts ) ? array_map( 'intval', $query->posts ) : array();
		set_transient( 'cp_woocommerce_product_ids_v1', $this->cp_woocommerce_product_ids, 5 * MINUTE_IN_SECONDS );
		return $this->cp_woocommerce_product_ids;
	}

	/**
	 * Register hooks that invalidate cached WooCommerce product ID lists.
	 */
	public static function register_cache_invalidation_hooks() {
		add_action( 'save_post_product', array( __CLASS__, 'maybe_clear_product_ids_cache' ), 10, 1 );
		add_action( 'delete_post', array( __CLASS__, 'maybe_clear_product_ids_cache' ), 10, 1 );
		add_action( 'set_object_terms', array( __CLASS__, 'maybe_clear_product_ids_cache_on_terms' ), 10, 4 );
	}

	/**
	 * Clear cached product IDs when a WooCommerce product changes.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function maybe_clear_product_ids_cache( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		delete_transient( 'conceptplug_store_health_v1' );
		if (
			get_post_meta( $post_id, '_cp_wc_generated', true )
			|| get_post_meta( $post_id, '_cp_wc_enhanced', true )
		) {
			delete_transient( 'cp_woocommerce_product_ids_v1' );
		}
	}

	/**
	 * Clear cache when product taxonomy terms change.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Term IDs.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy slug.
	 */
	public static function maybe_clear_product_ids_cache_on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		unset( $terms, $tt_ids );
		if ( ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
			return;
		}
		if ( 'product' !== get_post_type( $object_id ) ) {
			return;
		}
		delete_transient( 'conceptplug_store_health_v1' );
		if ( get_post_meta( $object_id, '_cp_wc_generated', true ) || get_post_meta( $object_id, '_cp_wc_enhanced', true ) ) {
			delete_transient( 'cp_woocommerce_product_ids_v1' );
		}
	}

	/**
	 * Include ancestor term IDs so hierarchical dropdowns stay complete.
	 *
	 * @param array<int, int> $term_ids Term IDs.
	 * @param string          $taxonomy Taxonomy.
	 * @return array<int, int>
	 */
	private function include_term_ancestors( array $term_ids, $taxonomy ) {
		$merged = array_map( 'intval', $term_ids );
		foreach ( $term_ids as $term_id ) {
			$ancestors = get_ancestors( (int) $term_id, $taxonomy, 'taxonomy' );
			if ( ! empty( $ancestors ) ) {
				$merged = array_merge( $merged, array_map( 'intval', $ancestors ) );
			}
		}
		return array_values( array_unique( $merged ) );
	}

	/**
	 * Taxonomy terms used by WooCommerce products (for filter dropdowns).
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int, WP_Term>
	 */
	private function get_filter_terms( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$filters = $this->get_filters();

		if ( 'product_cat' === $taxonomy ) {
			$term_ids = wp_list_pluck( $terms, 'term_id' );
			if ( ! empty( $filters['category'] ) ) {
				$term_ids[] = (int) $filters['category'];
			}
			$term_ids = $this->include_term_ancestors( $term_ids, $taxonomy );
			if ( empty( $term_ids ) ) {
				return array();
			}

			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'include'    => $term_ids,
					'hide_empty' => false,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
			if ( is_wp_error( $terms ) ) {
				return array();
			}
			return $terms;
		}

		if ( ! empty( $filters['product_tag'] ) ) {
			$selected_id = (int) $filters['product_tag'];
			$existing    = wp_list_pluck( $terms, 'term_id' );
			if ( ! in_array( $selected_id, $existing, true ) ) {
				$selected = get_term( $selected_id, 'product_tag' );
				if ( $selected && ! is_wp_error( $selected ) ) {
					$terms[] = $selected;
				}
			}
		}

		usort(
			$terms,
			static function ( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			}
		);

		return $terms;
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

		$status = isset( $_REQUEST['status'] ) ? $this->get_allowed_filter_status( wp_unslash( $_REQUEST['status'] ) ) : '';

		$this->filters = array(
			'category'    => isset( $_REQUEST['category'] ) ? absint( wp_unslash( $_REQUEST['category'] ) ) : 0,
			'product_tag' => isset( $_REQUEST['product_tag'] ) ? absint( wp_unslash( $_REQUEST['product_tag'] ) ) : 0,
			'status'      => $status,
			'cp_source'   => isset( $_REQUEST['cp_source'] ) ? $this->get_allowed_source_tab( wp_unslash( $_REQUEST['cp_source'] ) ) : 'all',
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

		$post_status = $this->get_list_post_statuses();
		if ( ! empty( $filters['status'] ) ) {
			$post_status = $filters['status'];
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => $order,
		);

		$source_meta = $this->get_source_meta_query( $filters['cp_source'] ?? 'all' );
		if ( ! empty( $source_meta ) ) {
			$args['meta_query'] = $source_meta;
		}

		if ( $search ) {
			$args['s'] = $search;
		}

		if ( 'title' === $orderby ) {
			$args['orderby'] = 'title';
		} elseif ( 'status' === $orderby ) {
			$args['orderby'] = 'post_status';
		} elseif ( 'seo_score' === $orderby ) {
			$args['meta_key'] = '_cp_wc_seo_score';
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

		if ( 'enhance_selected' === $action ) {
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

		$updater = new ConceptPlug_WooCommerce_Product_Updater();
		$bulk_category_id = isset( $_REQUEST['bulk_category_id'] ) ? absint( wp_unslash( $_REQUEST['bulk_category_id'] ) ) : 0;
		$result  = $updater->bulk_edit(
			$ids,
			$action,
			array(
				'category_ids' => $bulk_category_id ? array( $bulk_category_id ) : array(),
				'tags'         => isset( $_REQUEST['bulk_tags'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bulk_tags'] ) ) : '',
				'status'       => isset( $_REQUEST['bulk_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['bulk_status'] ) ) : '',
			)
		);

		$redirect_args = array(
			'page' => 'cp-woocommerce-products',
		);

		$filters = $this->get_filters();
		if ( ! empty( $filters['category'] ) ) {
			$redirect_args['category'] = (int) $filters['category'];
		}
		if ( ! empty( $filters['product_tag'] ) ) {
			$redirect_args['product_tag'] = (int) $filters['product_tag'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$redirect_args['status'] = $filters['status'];
		}
		if ( ! empty( $filters['cp_source'] ) && 'all' !== $filters['cp_source'] ) {
			$redirect_args['cp_source'] = $filters['cp_source'];
		}
		if ( ! empty( $_REQUEST['s'] ) ) {
			$redirect_args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		if ( ! empty( $_REQUEST['paged'] ) ) {
			$redirect_args['paged'] = absint( wp_unslash( $_REQUEST['paged'] ) );
		}

		if ( is_wp_error( $result ) ) {
			set_transient(
				'cp_woocommerce_admin_notice_' . get_current_user_id(),
				array(
					'type'    => 'error',
					'message' => ConceptPlug_User_Messages::for_error( $result ),
				),
				30
			);
		} else {
			$redirect_args['cp_woocommerce_bulk_updated'] = (int) ( $result['updated'] ?? 0 );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render source tabs, filter dropdowns, and active filter chips above the list table.
	 */
	public function render_list_toolbar() {
		echo '<div class="cp-wc-products-list-toolbar">';
		$this->render_source_tabs();
		$this->render_filter_controls();
		$this->render_active_filters();
		echo '</div>';
	}

	/**
	 * Extra controls above/below the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'bottom' === $which ) {
			$this->render_bulk_extra_controls();
		}
	}

	/**
	 * Source filter tabs above the table.
	 */
	private function render_source_tabs() {
		$filters = $this->get_filters();
		$current = $filters['cp_source'] ?? 'all';
		$labels  = $this->get_source_tab_labels();
		?>
		<div class="cp-wc-source-tabs">
			<?php foreach ( $labels as $key => $label ) : ?>
				<?php
				$url = $this->build_filter_url(
					array(
						'cp_source' => 'all' === $key ? null : $key,
					),
					true
				);
				$class = 'cp-wc-source-tab' . ( $current === $key ? ' is-active' : '' );
				?>
				<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Filter dropdowns.
	 */
	private function render_filter_controls() {
		$filters    = $this->get_filters();
		$categories = $this->get_filter_terms( 'product_cat' );
		$tags       = $this->get_filter_terms( 'product_tag' );
		$statuses   = $this->get_status_labels();

		?>
		<div class="cp-woocommerce-products-filters">
			<label class="screen-reader-text" for="filter-by-category"><?php esc_html_e( 'Filter by category', 'conceptplug' ); ?></label>
			<select name="category" id="filter-by-category">
				<option value=""><?php esc_html_e( 'All categories', 'conceptplug' ); ?></option>
				<?php $this->render_category_filter_options( $categories, 0, 0, (int) $filters['category'] ); ?>
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
			<select name="status" id="filter-by-status">
				<option value=""><?php esc_html_e( 'All statuses', 'conceptplug' ); ?></option>
				<?php foreach ( $statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'conceptplug' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Render hierarchical category <option> elements.
	 *
	 * @param array<int, WP_Term> $terms       Category terms.
	 * @param int                 $parent_id   Parent term ID.
	 * @param int                 $depth       Indent depth.
	 * @param int                 $selected_id Selected term ID.
	 */
	private function render_category_filter_options( array $terms, $parent_id, $depth, $selected_id ) {
		foreach ( $terms as $term ) {
			if ( (int) $term->parent !== (int) $parent_id ) {
				continue;
			}

			$prefix = $depth > 0 ? str_repeat( '— ', $depth ) : '';
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $term->term_id ),
				selected( $selected_id, (int) $term->term_id, false ),
				esc_html( $prefix . $term->name )
			);
			$this->render_category_filter_options( $terms, (int) $term->term_id, $depth + 1, $selected_id );
		}
	}

	/**
	 * Build admin URL for products list with filter query args.
	 *
	 * @param array<string, mixed> $overrides   Values to override/remove (use null to drop).
	 * @param bool                 $reset_paged Omit paged from the URL.
	 * @return string
	 */
	private function build_filter_url( array $overrides = array(), $reset_paged = false ) {
		$filters = $this->get_filters();
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$paged   = isset( $_REQUEST['paged'] ) ? absint( wp_unslash( $_REQUEST['paged'] ) ) : 0;

		$category = array_key_exists( 'category', $overrides ) ? $overrides['category'] : $filters['category'];
		$tag      = array_key_exists( 'product_tag', $overrides ) ? $overrides['product_tag'] : $filters['product_tag'];
		$status   = array_key_exists( 'status', $overrides ) ? $overrides['status'] : $filters['status'];
		$source   = array_key_exists( 'cp_source', $overrides ) ? $overrides['cp_source'] : ( $filters['cp_source'] ?? 'all' );
		$s        = array_key_exists( 's', $overrides ) ? $overrides['s'] : $search;

		$args = array(
			'page' => 'cp-woocommerce-products',
		);

		if ( $category ) {
			$args['category'] = (int) $category;
		}
		if ( $tag ) {
			$args['product_tag'] = (int) $tag;
		}
		if ( $status ) {
			$args['status'] = $status;
		}
		if ( $source && 'all' !== $source ) {
			$args['cp_source'] = $source;
		}
		if ( $s ) {
			$args['s'] = $s;
		}
		if ( ! $reset_paged && $paged > 1 ) {
			$args['paged'] = $paged;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Active filter chips with per-filter remove and clear-all links.
	 */
	private function render_active_filters() {
		$filters  = $this->get_filters();
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$statuses = $this->get_status_labels();
		$chips    = array();

		if ( ! empty( $filters['category'] ) ) {
			$term = get_term( (int) $filters['category'], 'product_cat' );
			$label = ( $term && ! is_wp_error( $term ) )
				? sprintf(
					/* translators: %s: category name */
					__( 'Category: %s', 'conceptplug' ),
					$term->name
				)
				: __( 'Category filter', 'conceptplug' );

			$chips[] = array(
				'url'   => $this->build_filter_url( array( 'category' => null ) ),
				'label' => $label,
			);
		}

		if ( ! empty( $filters['product_tag'] ) ) {
			$term = get_term( (int) $filters['product_tag'], 'product_tag' );
			$label = ( $term && ! is_wp_error( $term ) )
				? sprintf(
					/* translators: %s: tag name */
					__( 'Tag: %s', 'conceptplug' ),
					$term->name
				)
				: __( 'Tag filter', 'conceptplug' );

			$chips[] = array(
				'url'   => $this->build_filter_url( array( 'product_tag' => null ) ),
				'label' => $label,
			);
		}

		if ( ! empty( $filters['status'] ) ) {
			$status_label = $statuses[ $filters['status'] ] ?? $filters['status'];
			$chips[]      = array(
				'url'   => $this->build_filter_url( array( 'status' => null ) ),
				'label' => sprintf(
					/* translators: %s: post status label */
					__( 'Status: %s', 'conceptplug' ),
					$status_label
				),
			);
		}

		if ( $search ) {
			$chips[] = array(
				'url'   => $this->build_filter_url( array( 's' => null ) ),
				'label' => sprintf(
					/* translators: %s: search keywords */
					__( 'Search: "%s"', 'conceptplug' ),
					$search
				),
			);
		}

		if ( empty( $chips ) ) {
			return;
		}

		?>
		<div class="cp-wc-active-filters alignleft">
			<span class="cp-wc-active-filters-label"><?php esc_html_e( 'Active filters:', 'conceptplug' ); ?></span>
			<?php foreach ( $chips as $chip ) : ?>
				<a class="cp-wc-filter-chip" href="<?php echo esc_url( $chip['url'] ); ?>">
					<?php echo esc_html( $chip['label'] ); ?>
					<span class="cp-wc-filter-chip-remove" aria-hidden="true">&times;</span>
				</a>
			<?php endforeach; ?>
			<a class="cp-wc-clear-filters" href="<?php echo esc_url( $this->build_filter_url( array( 'category' => null, 'product_tag' => null, 'status' => null, 's' => null ), true ) ); ?>">
				<?php esc_html_e( 'Clear all', 'conceptplug' ); ?>
			</a>
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
		<div class="alignleft actions cp-wc-bulk-extra" id="cp-wc-bulk-extra" hidden>
			<div class="cp-wc-bulk-field cp-wc-bulk-field-category" hidden>
				<label for="bulk_category_id"><?php esc_html_e( 'Category', 'conceptplug' ); ?></label>
				<select name="bulk_category_id" id="bulk_category_id">
					<option value=""><?php esc_html_e( 'Select category', 'conceptplug' ); ?></option>
					<?php foreach ( $categories as $term ) : ?>
						<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="cp-wc-bulk-field cp-wc-bulk-field-tags" hidden>
				<label for="bulk_tags"><?php esc_html_e( 'Tags', 'conceptplug' ); ?></label>
				<input type="text" name="bulk_tags" id="bulk_tags" class="regular-text" list="cp-wc-tag-suggestions" placeholder="<?php esc_attr_e( 'tag-one, tag-two', 'conceptplug' ); ?>" />
			</div>
			<div class="cp-wc-bulk-field cp-wc-bulk-field-status" hidden>
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
			return '<span class="cp-wc-no-thumb">—</span>';
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
		$product  = wc_get_product( $item->ID );
		$is_simple = $product && 'simple' === $product->get_type();

		$actions = array(
			'view'       => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'conceptplug' ) ),
			'edit'       => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'conceptplug' ) ),
			'quick_edit' => sprintf(
				'<a href="#" class="cp-wc-quick-edit-open"%1$s>%2$s</a>',
				$this->quick_edit_attr_string( $attrs ),
				esc_html__( 'Quick edit', 'conceptplug' )
			),
			'seo_report' => sprintf(
				'<a href="#" class="cp-wc-toggle-seo-report" data-product-id="%d">%s</a>',
				(int) $item->ID,
				esc_html__( 'SEO Report', 'conceptplug' )
			),
			'reanalyze'  => sprintf(
				'<a href="#" class="cp-wc-reanalyze-one" data-product-id="%d">%s</a>',
				(int) $item->ID,
				esc_html__( 'Re-analyze', 'conceptplug' )
			),
		);

		if ( $is_simple ) {
			$actions['enhance'] = sprintf(
				'<a href="#" class="cp-wc-enhance-open" data-product-id="%d" data-product-title="%s">%s</a>',
				(int) $item->ID,
				esc_attr( get_the_title( $item ) ),
				esc_html__( 'Enhance', 'conceptplug' )
			);
			$versions_count = ( new ConceptPlug_WooCommerce_Product_Version_Store() )->count_versions( (int) $item->ID );
			$versions_badge = $versions_count > 0
				? sprintf( ' <span class="cp-wc-versions-badge" data-product-id="%d">%d</span>', (int) $item->ID, $versions_count )
				: sprintf( ' <span class="cp-wc-versions-badge" data-product-id="%d" hidden>%d</span>', (int) $item->ID, 0 );
			$actions['versions'] = sprintf(
				'<a href="#" class="cp-wc-versions-open" data-product-id="%d" data-product-title="%s">%s%s</a>',
				(int) $item->ID,
				esc_attr( get_the_title( $item ) ),
				esc_html__( 'Versions', 'conceptplug' ),
				$versions_badge
			);
		} else {
			$actions['enhance'] = sprintf(
				'<span class="cp-wc-enhance-disabled" title="%s">%s</span>',
				esc_attr__( 'AI enhance is available for simple products only.', 'conceptplug' ),
				esc_html__( 'Enhance', 'conceptplug' )
			);
		}

		return sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>%3$s<div class="cp-wc-seo-report-panel" id="cp-wc-seo-report-%4$d" hidden></div>',
			esc_url( $edit_url ),
			$title,
			$this->row_actions( $actions ),
			(int) $item->ID
		);
	}

	/**
	 * Categories column (display only).
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_categories( $item ) {
		return '<span class="cp-wc-tax-display">' . ConceptPlug_WooCommerce_Product_Updater::render_categories_cell( $item->ID ) . '</span>';
	}

	/**
	 * Tags column (display only).
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_tags( $item ) {
		return '<span class="cp-wc-tax-display">' . ConceptPlug_WooCommerce_Product_Updater::render_tags_cell( $item->ID ) . '</span>';
	}

	/**
	 * Product type column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_product_type( $item ) {
		return ConceptPlug_WooCommerce_Product_Updater::render_product_type_cell( $item->ID );
	}

	/**
	 * Status column (display only).
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_status( $item ) {
		return '<span class="cp-wc-tax-display">' . ConceptPlug_WooCommerce_Product_Updater::render_status_cell( $item->ID ) . '</span>';
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
	 * Source column badge.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_source( $item ) {
		$badge = ConceptPlug_WooCommerce_Product_Enhancer::get_source_badge( $item->ID );
		$labels = array(
			'created'  => __( 'Created', 'conceptplug' ),
			'enhanced' => __( 'Enhanced', 'conceptplug' ),
			'store'    => __( 'Store', 'conceptplug' ),
		);
		$label = $labels[ $badge ] ?? $badge;
		return sprintf(
			'<span class="cp-wc-source-badge cp-wc-source-%1$s">%2$s</span>',
			esc_attr( $badge ),
			esc_html( $label )
		);
	}

	/**
	 * SEO score column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	protected function column_seo_score( $item ) {
		$score = (int) get_post_meta( $item->ID, '_cp_wc_seo_score', true );
		$grade = get_post_meta( $item->ID, '_cp_wc_seo_grade', true );
		if ( ! $score && ! $grade ) {
			return '<span class="cp-wc-score-badge cp-wc-score-none">' . esc_html__( 'Not analyzed', 'conceptplug' ) . '</span>';
		}
		$class = ConceptPlug_WooCommerce_Ajax_Handlers::score_class( $score );
		return sprintf(
			'<span class="cp-wc-score-badge %1$s" data-score="%2$d"><span class="cp-wc-score-num">%2$d</span><span class="cp-wc-score-grade">%3$s</span></span>',
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
		$generated = get_post_meta( $item->ID, '_cp_wc_generated_at', true );
		$enhanced  = get_post_meta( $item->ID, '_cp_wc_enhanced_at', true );
		$date      = $generated ?: ( $enhanced ?: $item->post_date );
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
	 * Render tablenav outside the horizontal scroll region so filters never overlap headers.
	 */
	public function display() {
		$singular = $this->_args['singular'];

		$this->display_tablenav( 'top' );

		echo '<div class="cp-table-scroll cp-products-table-scroll">';

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
		<?php $this->print_table_description(); ?>
	<thead>
	<tr>
		<?php $this->print_column_headers(); ?>
	</tr>
	</thead>

	<tbody id="the-list"
		<?php
		if ( $singular ) {
			echo " data-wp-lists='list:" . esc_attr( $singular ) . "'";
		}
		?>
		>
		<?php $this->display_rows_or_placeholder(); ?>
	</tbody>

	<tfoot>
	<tr>
		<?php $this->print_column_headers( false ); ?>
	</tr>
	</tfoot>

</table>
		<?php
		echo '</div>';

		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Message when no items.
	 */
	public function no_items() {
		esc_html_e( 'No WooCommerce products match this view.', 'conceptplug' );
	}
}
