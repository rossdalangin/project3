<?php
/**
 * Custom Meta Field Registration
 *
 * @package Ross_Portfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ross_Portfolio_Metadata
 */
class Ross_Portfolio_Metadata {

	/**
	 * Single Instance
	 *
	 * @var Ross_Portfolio_Metadata|null
	 */
	private static $instance = null;

	/**
	 * Main Ross_Portfolio_Metadata Instance.
	 *
	 * @return Ross_Portfolio_Metadata
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
		add_action( 'init', array( $this, 'register_meta_fields' ) );
	}

	/**
	 * Register Custom Meta Fields for portfolio_project CPT.
	 */
	public function register_meta_fields() {
		// Project Type (_ross_project_type)
		register_post_meta(
			'portfolio_project',
			'_ross_project_type',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Type or category of project', 'ross-portfolio' ),
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
			)
		);

		// Project Summary (_ross_project_summary)
		register_post_meta(
			'portfolio_project',
			'_ross_project_summary',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Summary overview of project', 'ross-portfolio' ),
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
			)
		);

		// Project Solution (_ross_project_solution)
		register_post_meta(
			'portfolio_project',
			'_ross_project_solution',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'description'       => __( 'Solution description for project', 'ross-portfolio' ),
				'sanitize_callback' => array( $this, 'sanitize_solution_field' ),
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
			)
		);

		// Project Featured (_ross_project_featured)
		register_post_meta(
			'portfolio_project',
			'_ross_project_featured',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'description'       => __( 'Whether project is featured', 'ross-portfolio' ),
				'sanitize_callback' => array( $this, 'sanitize_boolean_field' ),
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
			)
		);
	}

	/**
	 * Authorization callback for meta fields.
	 *
	 * @param bool   $allowed
	 * @param string $meta_key
	 * @param int    $post_id
	 * @param int    $user_id
	 * @param string $cap
	 * @param array  $caps
	 * @return bool
	 */
	public function auth_meta_callback( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize Solution Field (allowing safe HTML formatting).
	 *
	 * @param string $content
	 * @return string
	 */
	public function sanitize_solution_field( $content ) {
		return wp_kses_post( $content );
	}

	/**
	 * Sanitize Boolean Field.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public function sanitize_boolean_field( $value ) {
		return rest_sanitize_boolean( $value );
	}
}
