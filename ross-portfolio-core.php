<?php
/**
 * Plugin Name: Ross Portfolio Core
 * Plugin URI: https://example.com/ross-portfolio-core
 * Description: Standalone, object-oriented WordPress portfolio, membership, and content restriction plugin.
 * Version: 1.0.0
 * Author: Ross
 * Author URI: https://example.com
 * License: GPLv2 or later
 * Text Domain: ross-portfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'ROSS_PORTFOLIO_VERSION', '1.0.0' );
define( 'ROSS_PORTFOLIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'ROSS_PORTFOLIO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
final class Ross_Portfolio_Core {

	/**
	 * Single Instance
	 *
	 * @var Ross_Portfolio_Core|null
	 */
	private static $instance = null;

	/**
	 * Main Ross_Portfolio_Core Instance.
	 *
	 * @return Ross_Portfolio_Core
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
		$this->include_files();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function include_files() {
		require_once ROSS_PORTFOLIO_PATH . 'includes/post-types.php';
		require_once ROSS_PORTFOLIO_PATH . 'includes/metadata.php';
		require_once ROSS_PORTFOLIO_PATH . 'includes/admin-fields.php';
		require_once ROSS_PORTFOLIO_PATH . 'includes/helpers.php';
	}

	/**
	 * Register plugin hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init_classes' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'template_redirect', array( $this, 'handle_template_redirect' ) );
		add_filter( 'the_content', array( $this, 'filter_content' ) );
		add_filter( 'the_excerpt', array( $this, 'filter_excerpt' ) );
		add_filter( 'the_content_feed', array( $this, 'filter_feed' ) );
		add_filter( 'the_excerpt_rss', array( $this, 'filter_feed' ) );
		add_filter( 'rest_prepare_portfolio_project', array( $this, 'filter_rest_response' ), 10, 3 );
	}

	/**
	 * Initialize included class instances.
	 */
	public function init_classes() {
		if ( class_exists( 'Ross_Portfolio_Post_Types' ) ) {
			Ross_Portfolio_Post_Types::get_instance();
		}
		if ( class_exists( 'Ross_Portfolio_Metadata' ) ) {
			Ross_Portfolio_Metadata::get_instance();
		}
		if ( class_exists( 'Ross_Portfolio_Admin_Fields' ) ) {
			Ross_Portfolio_Admin_Fields::get_instance();
		}
	}

	/**
	 * Plugin Activation.
	 */
	public static function activate() {
		// Include post-types if not already loaded to register CPT before flush
		if ( file_exists( ROSS_PORTFOLIO_PATH . 'includes/post-types.php' ) ) {
			require_once ROSS_PORTFOLIO_PATH . 'includes/post-types.php';
			if ( class_exists( 'Ross_Portfolio_Post_Types' ) ) {
				$pt = Ross_Portfolio_Post_Types::get_instance();
				$pt->register_post_types();
				$pt->register_taxonomies();
			}
		}

		// Set default allowed roles if option not set
		if ( false === get_option( 'ross_portfolio_allowed_roles' ) ) {
			$default_roles = array( 'administrator', 'editor' );
			update_option( 'ross_portfolio_allowed_roles', $default_roles );
		}

		self::sync_capabilities();
		flush_rewrite_rules();
	}

	/**
	 * Synchronize capability 'read_private_portfolio' across WP roles based on plugin settings.
	 */
	public static function sync_capabilities() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$allowed_roles = get_option( 'ross_portfolio_allowed_roles', array( 'administrator', 'editor' ) );
		if ( ! is_array( $allowed_roles ) ) {
			$allowed_roles = array();
		}

		$wp_roles = wp_roles();
		foreach ( $wp_roles->roles as $role_key => $role_info ) {
			$role_obj = get_role( $role_key );
			if ( ! $role_obj ) {
				continue;
			}

			if ( in_array( $role_key, $allowed_roles, true ) ) {
				$role_obj->add_cap( 'read_private_portfolio' );
			} else {
				$role_obj->remove_cap( 'read_private_portfolio' );
			}
		}
	}

	/**
	 * Plugin Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Add Settings Page under Options menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Portfolio Access Settings', 'ross-portfolio' ),
			__( 'Portfolio Access', 'ross-portfolio' ),
			'manage_options',
			'ross-portfolio-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register Settings API options and sections.
	 */
	public function register_settings() {
		register_setting(
			'ross_portfolio_settings_group',
			'ross_portfolio_allowed_roles',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_allowed_roles' ),
				'default'           => array( 'administrator', 'editor' ),
			)
		);

		add_settings_section(
			'ross_portfolio_main_section',
			__( 'Access Control Settings', 'ross-portfolio' ),
			array( $this, 'render_section_info' ),
			'ross-portfolio-settings'
		);

		add_settings_field(
			'ross_portfolio_allowed_roles_field',
			__( 'Allowed Roles', 'ross-portfolio' ),
			array( $this, 'render_allowed_roles_field' ),
			'ross-portfolio-settings',
			'ross_portfolio_main_section'
		);
	}

	/**
	 * Sanitize allowed roles input and sync capabilities.
	 * Validate inputs against valid system roles.
	 *
	 * @param mixed $input
	 * @return array
	 */
	public function sanitize_allowed_roles( $input ) {
		$sanitized = array();
		$valid_roles = function_exists( 'wp_roles' ) ? array_keys( wp_roles()->get_names() ) : array();

		if ( is_array( $input ) ) {
			foreach ( $input as $role ) {
				$clean_role = sanitize_key( $role );
				if ( empty( $valid_roles ) || in_array( $clean_role, $valid_roles, true ) ) {
					$sanitized[] = $clean_role;
				}
			}
		}

		// Update capabilities for roles
		update_option( 'ross_portfolio_allowed_roles', $sanitized );
		self::sync_capabilities();

		return $sanitized;
	}

	/**
	 * Render settings section description.
	 */
	public function render_section_info() {
		echo '<p>' . esc_html__( 'Select which user roles have permission to access restricted portfolio projects.', 'ross-portfolio' ) . '</p>';
	}

	/**
	 * Render roles checkbox group in Settings API.
	 */
	public function render_allowed_roles_field() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$allowed_roles = get_option( 'ross_portfolio_allowed_roles', array( 'administrator', 'editor' ) );
		if ( ! is_array( $allowed_roles ) ) {
			$allowed_roles = array();
		}

		$all_roles = wp_roles()->get_names();

		foreach ( $all_roles as $role_key => $role_name ) {
			$checked = in_array( $role_key, $allowed_roles, true ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 6px;">';
			echo '<input type="checkbox" name="ross_portfolio_allowed_roles[]" value="' . esc_attr( $role_key ) . '" ' . $checked . '> ';
			echo esc_html( translate_user_role( $role_name ) ) . ' (<code>' . esc_html( $role_key ) . '</code>)';
			echo '</label>';
		}
	}

	/**
	 * Render Settings Page HTML.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'ross_portfolio_settings_group' );
				do_settings_sections( 'ross_portfolio_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle template redirect logic to prevent caching restricted content pages.
	 */
	public function handle_template_redirect() {
		if ( is_singular( 'portfolio_project' ) ) {
			if ( ! function_exists( 'ross_user_has_access' ) || ! ross_user_has_access() ) {
				if ( function_exists( 'nocache_headers' ) ) {
					nocache_headers();
				}
			}
		}
	}

	/**
	 * Filter the_content to restrict access for portfolio_project CPT.
	 *
	 * @param string $content
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( ! is_singular( 'portfolio_project' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( function_exists( 'ross_user_has_access' ) && ross_user_has_access() ) {
			return $content;
		}

		return $this->render_restricted_notice();
	}

	/**
	 * Filter post excerpt for restricted projects.
	 *
	 * @param string $excerpt
	 * @return string
	 */
	public function filter_excerpt( $excerpt ) {
		if ( is_singular( 'portfolio_project' ) ) {
			if ( ! function_exists( 'ross_user_has_access' ) || ! ross_user_has_access() ) {
				return esc_html__( 'Content restricted.', 'ross-portfolio' );
			}
		}
		return $excerpt;
	}

	/**
	 * Redact RSS feeds for portfolio projects.
	 *
	 * @param string $content
	 * @return string
	 */
	public function filter_feed( $content ) {
		if ( get_post_type() === 'portfolio_project' ) {
			if ( ! function_exists( 'ross_user_has_access' ) || ! ross_user_has_access() ) {
				return esc_html__( 'Content restricted.', 'ross-portfolio' );
			}
		}
		return $content;
	}

	/**
	 * Filter REST API response to sanitize / redact sensitive meta for unauthorized users.
	 *
	 * @param WP_REST_Response $response
	 * @param WP_Post          $post
	 * @param WP_REST_Request  $request
	 * @return WP_REST_Response
	 */
	public function filter_rest_response( $response, $post, $request ) {
		if ( ! function_exists( 'ross_user_has_access' ) || ! ross_user_has_access() ) {
			$data = $response->get_data();
			if ( isset( $data['content'] ) ) {
				$data['content']['rendered'] = esc_html__( 'Content restricted.', 'ross-portfolio' );
			}
			if ( isset( $data['excerpt'] ) ) {
				$data['excerpt']['rendered'] = esc_html__( 'Content restricted.', 'ross-portfolio' );
			}
			if ( isset( $data['meta'] ) ) {
				$data['meta']['_ross_project_type']     = '';
				$data['meta']['_ross_project_summary']  = '';
				$data['meta']['_ross_project_solution'] = '';
				$data['meta']['_ross_project_featured'] = false;
			}
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Render Glassmorphic Access Denied / Login Notice.
	 *
	 * @return string
	 */
	private function render_restricted_notice() {
		ob_start();
		?>
		<style>
			.ross-glassmorphic-notice {
				background: rgba( 255, 255, 255, 0.25 );
				backdrop-filter: blur( 12px );
				-webkit-backdrop-filter: blur( 12px );
				border: 1px solid rgba( 255, 255, 255, 0.3 );
				border-radius: 16px;
				padding: 30px;
				margin: 20px 0;
				box-shadow: 0 8px 32px 0 rgba( 31, 38, 135, 0.15 );
				text-align: center;
				font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				color: #1f2937;
			}
			.ross-glassmorphic-notice h3 {
				margin-top: 0;
				font-size: 1.5rem;
				color: #111827;
			}
			.ross-glassmorphic-notice p {
				font-size: 1rem;
				color: #4b5563;
				margin-bottom: 20px;
			}
			.ross-glassmorphic-notice .ross-login-form {
				max-width: 360px;
				margin: 0 auto;
				text-align: left;
			}
			.ross-glassmorphic-notice label {
				display: block;
				margin-bottom: 6px;
				font-weight: 600;
				font-size: 0.875rem;
			}
			.ross-glassmorphic-notice input[type="text"],
			.ross-glassmorphic-notice input[type="password"] {
				width: 100%;
				padding: 10px 14px;
				margin-bottom: 14px;
				border: 1px solid #d1d5db;
				border-radius: 8px;
				box-sizing: border-box;
			}
			.ross-glassmorphic-notice input[type="submit"] {
				width: 100%;
				padding: 10px 16px;
				background-color: #2563eb;
				color: #ffffff;
				border: none;
				border-radius: 8px;
				font-weight: 600;
				cursor: pointer;
				transition: background-color 0.2s ease;
			}
			.ross-glassmorphic-notice input[type="submit"]:hover {
				background-color: #1d4ed8;
			}
		</style>
		<div class="ross-glassmorphic-notice" role="alert" aria-live="polite">
			<h3><?php echo esc_html__( 'Restricted Content', 'ross-portfolio' ); ?></h3>
			<?php if ( is_user_logged_in() ) : ?>
				<p><?php echo esc_html__( 'You do not have sufficient permissions to view this project.', 'ross-portfolio' ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html__( 'This content is restricted. Please log in with authorized credentials to view this project.', 'ross-portfolio' ); ?></p>
				<div class="ross-login-form">
					<?php
					wp_login_form(
						array(
							'echo'           => true,
							'redirect'       => get_permalink(),
							'form_id'        => 'ross-portfolio-loginform',
							'label_username' => __( 'Username or Email Address', 'ross-portfolio' ),
							'label_password' => __( 'Password', 'ross-portfolio' ),
							'label_remember' => __( 'Remember Me', 'ross-portfolio' ),
							'label_log_in'   => __( 'Log In', 'ross-portfolio' ),
							'remember'       => true,
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Register Activation & Deactivation Hooks
register_activation_hook( __FILE__, array( 'Ross_Portfolio_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Ross_Portfolio_Core', 'deactivate' ) );

// Initialize Main Plugin Class
function ross_portfolio_core() {
	return Ross_Portfolio_Core::get_instance();
}
ross_portfolio_core();
