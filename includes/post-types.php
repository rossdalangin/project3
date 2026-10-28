<?php
/**
 * Post Types and Taxonomies Registration
 *
 * @package Ross_Portfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ross_Portfolio_Post_Types
 */
class Ross_Portfolio_Post_Types {

	/**
	 * Single Instance
	 *
	 * @var Ross_Portfolio_Post_Types|null
	 */
	private static $instance = null;

	/**
	 * Main Ross_Portfolio_Post_Types Instance.
	 *
	 * @return Ross_Portfolio_Post_Types
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register Custom Post Type portfolio_project.
	 */
	public function register_post_types() {
		$labels = array(
			'name'                  => _x( 'Portfolio Projects', 'Post Type General Name', 'ross-portfolio' ),
			'singular_name'         => _x( 'Portfolio Project', 'Post Type Singular Name', 'ross-portfolio' ),
			'menu_name'             => __( 'Portfolio', 'ross-portfolio' ),
			'name_admin_bar'        => __( 'Portfolio Project', 'ross-portfolio' ),
			'archives'              => __( 'Project Archives', 'ross-portfolio' ),
			'attributes'            => __( 'Project Attributes', 'ross-portfolio' ),
			'parent_item_colon'     => __( 'Parent Project:', 'ross-portfolio' ),
			'all_items'             => __( 'All Projects', 'ross-portfolio' ),
			'add_new_item'          => __( 'Add New Project', 'ross-portfolio' ),
			'add_new'               => __( 'Add New', 'ross-portfolio' ),
			'new_item'              => __( 'New Project', 'ross-portfolio' ),
			'edit_item'             => __( 'Edit Project', 'ross-portfolio' ),
			'update_item'           => __( 'Update Project', 'ross-portfolio' ),
			'view_item'             => __( 'View Project', 'ross-portfolio' ),
			'view_items'            => __( 'View Projects', 'ross-portfolio' ),
			'search_items'          => __( 'Search Project', 'ross-portfolio' ),
			'not_found'             => __( 'Not found', 'ross-portfolio' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ross-portfolio' ),
			'featured_image'        => __( 'Project Cover Image', 'ross-portfolio' ),
			'set_featured_image'    => __( 'Set project cover image', 'ross-portfolio' ),
			'remove_featured_image' => __( 'Remove project cover image', 'ross-portfolio' ),
			'use_featured_image'    => __( 'Use as project cover image', 'ross-portfolio' ),
			'insert_into_item'      => __( 'Insert into project', 'ross-portfolio' ),
			'uploaded_to_this_item' => __( 'Uploaded to this project', 'ross-portfolio' ),
			'items_list'            => __( 'Projects list', 'ross-portfolio' ),
			'items_list_navigation' => __( 'Projects list navigation', 'ross-portfolio' ),
			'filter_items_list'     => __( 'Filter projects list', 'ross-portfolio' ),
		);

		$args = array(
			'label'               => __( 'Portfolio Project', 'ross-portfolio' ),
			'description'         => __( 'Custom Post Type for Portfolio Projects', 'ross-portfolio' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'taxonomies'          => array( 'project_category', 'project_tech' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-portfolio',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'portfolio_projects',
		);

		register_post_type( 'portfolio_project', $args );
	}

	/**
	 * Register Custom Taxonomies project_category and project_tech.
	 */
	public function register_taxonomies() {
		// Category Taxonomy
		$cat_labels = array(
			'name'                       => _x( 'Project Categories', 'Taxonomy General Name', 'ross-portfolio' ),
			'singular_name'              => _x( 'Project Category', 'Taxonomy Singular Name', 'ross-portfolio' ),
			'menu_name'                  => __( 'Categories', 'ross-portfolio' ),
			'all_items'                  => __( 'All Categories', 'ross-portfolio' ),
			'parent_item'                => __( 'Parent Category', 'ross-portfolio' ),
			'parent_item_colon'          => __( 'Parent Category:', 'ross-portfolio' ),
			'new_item_name'              => __( 'New Category Name', 'ross-portfolio' ),
			'add_new_item'               => __( 'Add New Category', 'ross-portfolio' ),
			'edit_item'                  => __( 'Edit Category', 'ross-portfolio' ),
			'update_item'                => __( 'Update Category', 'ross-portfolio' ),
			'view_item'                  => __( 'View Category', 'ross-portfolio' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'ross-portfolio' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'ross-portfolio' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'ross-portfolio' ),
			'popular_items'              => __( 'Popular Categories', 'ross-portfolio' ),
			'search_items'               => __( 'Search Categories', 'ross-portfolio' ),
			'not_found'                  => __( 'Not Found', 'ross-portfolio' ),
			'no_terms'                   => __( 'No categories', 'ross-portfolio' ),
			'items_list'                 => __( 'Categories list', 'ross-portfolio' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'ross-portfolio' ),
		);

		$cat_args = array(
			'labels'            => $cat_labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
			'rest_base'         => 'project_categories',
		);

		register_taxonomy( 'project_category', array( 'portfolio_project' ), $cat_args );

		// Tech Taxonomy
		$tech_labels = array(
			'name'                       => _x( 'Project Technologies', 'Taxonomy General Name', 'ross-portfolio' ),
			'singular_name'              => _x( 'Project Technology', 'Taxonomy Singular Name', 'ross-portfolio' ),
			'menu_name'                  => __( 'Technologies', 'ross-portfolio' ),
			'all_items'                  => __( 'All Technologies', 'ross-portfolio' ),
			'parent_item'                => __( 'Parent Technology', 'ross-portfolio' ),
			'parent_item_colon'          => __( 'Parent Technology:', 'ross-portfolio' ),
			'new_item_name'              => __( 'New Technology Name', 'ross-portfolio' ),
			'add_new_item'               => __( 'Add New Technology', 'ross-portfolio' ),
			'edit_item'                  => __( 'Edit Technology', 'ross-portfolio' ),
			'update_item'                => __( 'Update Technology', 'ross-portfolio' ),
			'view_item'                  => __( 'View Technology', 'ross-portfolio' ),
			'separate_items_with_commas' => __( 'Separate technologies with commas', 'ross-portfolio' ),
			'add_or_remove_items'        => __( 'Add or remove technologies', 'ross-portfolio' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'ross-portfolio' ),
			'popular_items'              => __( 'Popular Technologies', 'ross-portfolio' ),
			'search_items'               => __( 'Search Technologies', 'ross-portfolio' ),
			'not_found'                  => __( 'Not Found', 'ross-portfolio' ),
			'no_terms'                   => __( 'No technologies', 'ross-portfolio' ),
			'items_list'                 => __( 'Technologies list', 'ross-portfolio' ),
			'items_list_navigation'      => __( 'Technologies list navigation', 'ross-portfolio' ),
		);

		$tech_args = array(
			'labels'            => $tech_labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
			'rest_base'         => 'project_techs',
		);

		register_taxonomy( 'project_tech', array( 'portfolio_project' ), $tech_args );
	}
}
