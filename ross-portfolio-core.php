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

		if ( false === get_option( 'ross_portfolio_restriction_title' ) ) {
			update_option( 'ross_portfolio_restriction_title', __( 'Private Client Portfolio', 'ross-portfolio' ) );
		}

		if ( false === get_option( 'ross_portfolio_guest_message' ) ) {
			update_option( 'ross_portfolio_guest_message', __( 'This project is part of our confidential client portfolio. Please log in with authorized credentials to view details.', 'ross-portfolio' ) );
		}

		if ( false === get_option( 'ross_portfolio_denied_message' ) ) {
			update_option( 'ross_portfolio_denied_message', __( 'Your current account level does not have clearance to view this confidential portfolio item.', 'ross-portfolio' ) );
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
		// Allowed Roles
		register_setting(
			'ross_portfolio_settings_group',
			'ross_portfolio_allowed_roles',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_allowed_roles' ),
				'default'           => array( 'administrator', 'editor' ),
			)
		);

		// Notice Title
		register_setting(
			'ross_portfolio_settings_group',
			'ross_portfolio_restriction_title',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Private Client Portfolio', 'ross-portfolio' ),
			)
		);

		// Guest Message
		register_setting(
			'ross_portfolio_settings_group',
			'ross_portfolio_guest_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => __( 'This project is part of our confidential client portfolio. Please log in with authorized credentials to view details.', 'ross-portfolio' ),
			)
		);

		// Access Denied Message (Logged in)
		register_setting(
			'ross_portfolio_settings_group',
			'ross_portfolio_denied_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => __( 'Your current account level does not have clearance to view this confidential portfolio item.', 'ross-portfolio' ),
			)
		);

		// Section: Role Access Controls
		add_settings_section(
			'ross_portfolio_main_section',
			__( 'Role Access Permissions', 'ross-portfolio' ),
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

		// Section: Presentation & Messaging
		add_settings_section(
			'ross_portfolio_notice_section',
			__( 'Restricted Notice Presentation', 'ross-portfolio' ),
			array( $this, 'render_notice_section_info' ),
			'ross-portfolio-settings'
		);

		add_settings_field(
			'ross_portfolio_restriction_title_field',
			__( 'Notice Headline', 'ross-portfolio' ),
			array( $this, 'render_restriction_title_field' ),
			'ross-portfolio-settings',
			'ross_portfolio_notice_section'
		);

		add_settings_field(
			'ross_portfolio_guest_message_field',
			__( 'Guest Message', 'ross-portfolio' ),
			array( $this, 'render_guest_message_field' ),
			'ross-portfolio-settings',
			'ross_portfolio_notice_section'
		);

		add_settings_field(
			'ross_portfolio_denied_message_field',
			__( 'Access Denied Message', 'ross-portfolio' ),
			array( $this, 'render_denied_message_field' ),
			'ross-portfolio-settings',
			'ross_portfolio_notice_section'
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
		echo '<p class="description">' . esc_html__( 'Select which user roles are granted the read_private_portfolio capability to access restricted portfolio projects.', 'ross-portfolio' ) . '</p>';
	}

	/**
	 * Render notice section description.
	 */
	public function render_notice_section_info() {
		echo '<p class="description">' . esc_html__( 'Customize the messaging shown to visitors and unauthorized members on restricted project pages.', 'ross-portfolio' ) . '</p>';
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

		echo '<div class="ross-roles-grid">';
		foreach ( $all_roles as $role_key => $role_name ) {
			$checked = in_array( $role_key, $allowed_roles, true ) ? 'checked="checked"' : '';
			$is_admin = 'administrator' === $role_key;
			?>
			<label class="ross-role-card <?php echo $checked ? 'is-selected' : ''; ?>">
				<input type="checkbox" name="ross_portfolio_allowed_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php echo $checked; ?>>
				<div class="ross-role-info">
					<span class="ross-role-name"><?php echo esc_html( translate_user_role( $role_name ) ); ?></span>
					<span class="ross-role-slug"><code><?php echo esc_html( $role_key ); ?></code></span>
				</div>
				<?php if ( $is_admin ) : ?>
					<span class="ross-role-badge"><?php esc_html_e( 'System Admin', 'ross-portfolio' ); ?></span>
				<?php endif; ?>
			</label>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Render headline field.
	 */
	public function render_restriction_title_field() {
		$val = get_option( 'ross_portfolio_restriction_title', __( 'Private Client Portfolio', 'ross-portfolio' ) );
		?>
		<input type="text" name="ross_portfolio_restriction_title" value="<?php echo esc_attr( $val ); ?>" class="regular-text" style="width: 100%; max-width: 500px;">
		<p class="description"><?php esc_html_e( 'The headline displayed on the glassmorphic modal / notice.', 'ross-portfolio' ); ?></p>
		<?php
	}

	/**
	 * Render guest message field.
	 */
	public function render_guest_message_field() {
		$val = get_option( 'ross_portfolio_guest_message', __( 'This project is part of our confidential client portfolio. Please log in with authorized credentials to view details.', 'ross-portfolio' ) );
		?>
		<textarea name="ross_portfolio_guest_message" rows="3" class="large-text code" style="width: 100%; max-width: 500px;"><?php echo esc_textarea( $val ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Message shown to unauthenticated visitors above the login form.', 'ross-portfolio' ); ?></p>
		<?php
	}

	/**
	 * Render denied message field.
	 */
	public function render_denied_message_field() {
		$val = get_option( 'ross_portfolio_denied_message', __( 'Your current account level does not have clearance to view this confidential portfolio item.', 'ross-portfolio' ) );
		?>
		<textarea name="ross_portfolio_denied_message" rows="3" class="large-text code" style="width: 100%; max-width: 500px;"><?php echo esc_textarea( $val ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Message shown to logged-in users who lack the required access capability.', 'ross-portfolio' ); ?></p>
		<?php
	}

	/**
	 * Render Modern Settings Page HTML with In-Plugin User Instructions.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap ross-admin-wrap">
			<header class="ross-admin-header">
				<div class="ross-header-icon">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
				</div>
				<div>
					<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
					<p class="ross-header-subtitle"><?php esc_html_e( 'Manage role-based membership permissions and custom presentation for your portfolio projects.', 'ross-portfolio' ); ?></p>
				</div>
			</header>

			<style>
				.ross-admin-wrap {
					max-width: 960px;
					margin: 24px auto 30px 20px;
				}
				.ross-admin-header {
					display: flex;
					align-items: center;
					gap: 16px;
					padding: 20px 24px;
					background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
					border-radius: 12px;
					color: #ffffff;
					margin-bottom: 24px;
					box-shadow: 0 4px 12px rgba(0,0,0,0.06);
				}
				.ross-admin-header h1 {
					color: #ffffff;
					font-size: 22px;
					font-weight: 700;
					margin: 0 0 4px 0;
					padding: 0;
				}
				.ross-header-subtitle {
					margin: 0;
					color: #94a3b8;
					font-size: 13px;
				}
				.ross-header-icon {
					display: flex;
					align-items: center;
					justify-content: center;
					width: 52px;
					height: 52px;
					background: rgba(255, 255, 255, 0.1);
					border: 1px solid rgba(255, 255, 255, 0.2);
					border-radius: 10px;
					color: #38bdf8;
					flex-shrink: 0;
				}
				.ross-settings-container {
					background: #ffffff;
					border: 1px solid #e2e8f0;
					border-radius: 12px;
					padding: 28px 32px;
					box-shadow: 0 2px 8px rgba(0,0,0,0.04);
					margin-bottom: 28px;
				}
				.ross-roles-grid {
					display: grid;
					grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
					gap: 12px;
					margin-top: 6px;
				}
				.ross-role-card {
					display: flex;
					align-items: center;
					gap: 12px;
					padding: 12px 16px;
					background: #f8fafc;
					border: 1px solid #e2e8f0;
					border-radius: 8px;
					cursor: pointer;
					transition: all 0.2s ease;
					position: relative;
				}
				.ross-role-card:hover {
					background: #f1f5f9;
					border-color: #cbd5e1;
				}
				.ross-role-card input[type="checkbox"] {
					margin: 0;
					accent-color: #2563eb;
					width: 18px;
					height: 18px;
				}
				.ross-role-info {
					display: flex;
					flex-direction: column;
				}
				.ross-role-name {
					font-weight: 600;
					color: #1e293b;
					font-size: 14px;
				}
				.ross-role-slug code {
					font-size: 11px;
					background: none;
					padding: 0;
					color: #64748b;
				}
				.ross-role-badge {
					position: absolute;
					right: 12px;
					font-size: 10px;
					font-weight: 600;
					text-transform: uppercase;
					padding: 2px 6px;
					background: #e0f2fe;
					color: #0369a1;
					border-radius: 4px;
				}
				.ross-settings-container .form-table th {
					padding: 20px 10px 20px 0;
					font-weight: 600;
					color: #334155;
					width: 220px;
				}
				.ross-settings-container .form-table td {
					padding: 20px 0;
				}
				.ross-settings-container h2 {
					font-size: 16px;
					font-weight: 700;
					color: #0f172a;
					padding-bottom: 8px;
					border-bottom: 1px solid #f1f5f9;
					margin-top: 24px;
					margin-bottom: 12px;
				}
				.ross-settings-container h2:first-of-type {
					margin-top: 0;
				}
				.ross-docs-container {
					background: #ffffff;
					border: 1px solid #e2e8f0;
					border-radius: 12px;
					padding: 28px 32px;
					box-shadow: 0 2px 8px rgba(0,0,0,0.04);
				}
				.ross-docs-header {
					display: flex;
					align-items: center;
					gap: 10px;
					border-bottom: 1px solid #f1f5f9;
					padding-bottom: 12px;
					margin-bottom: 20px;
				}
				.ross-docs-header h2 {
					margin: 0;
					font-size: 18px;
					font-weight: 700;
					color: #0f172a;
				}
				.ross-docs-step {
					margin-bottom: 24px;
				}
				.ross-docs-step h3 {
					font-size: 15px;
					font-weight: 600;
					color: #1e293b;
					margin: 0 0 8px 0;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.ross-docs-step p {
					margin: 0 0 10px 0;
					color: #475569;
					font-size: 13.5px;
					line-height: 1.6;
				}
				.ross-docs-badge {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					width: 22px;
					height: 22px;
					border-radius: 50%;
					background: #2563eb;
					color: #ffffff;
					font-size: 12px;
					font-weight: 700;
				}
				.ross-code-box {
					background: #0f172a;
					color: #e2e8f0;
					padding: 14px 18px;
					border-radius: 8px;
					font-family: Consolas, Monaco, monospace;
					font-size: 12.5px;
					overflow-x: auto;
					line-height: 1.6;
					margin-top: 8px;
				}
			</style>

			<div class="ross-settings-container">
				<form action="options.php" method="post">
					<?php
					settings_fields( 'ross_portfolio_settings_group' );
					do_settings_sections( 'ross-portfolio-settings' );
					submit_button( __( 'Save Access Settings', 'ross-portfolio' ), 'primary large' );
					?>
				</form>
			</div>

			<div class="ross-docs-container">
				<div class="ross-docs-header">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
						<path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
					</svg>
					<h2><?php esc_html_e( 'How to Use This Plugin', 'ross-portfolio' ); ?></h2>
				</div>

				<div class="ross-docs-step">
					<h3><span class="ross-docs-badge">1</span> <?php esc_html_e( 'Creating & Managing Portfolio Projects', 'ross-portfolio' ); ?></h3>
					<p><?php esc_html_e( 'Navigate to Portfolio > Add New to create a project. Enter title, description, cover image, and assign categories/technologies.', 'ross-portfolio' ); ?></p>
					<p><?php esc_html_e( 'Use the Project Specification & Metadata meta box below the editor to configure project type, executive summary, technical solution breakdown, and featured showcase status.', 'ross-portfolio' ); ?></p>
				</div>

				<div class="ross-docs-step">
					<h3><span class="ross-docs-badge">2</span> <?php esc_html_e( 'Managing Access & Memberships', 'ross-portfolio' ); ?></h3>
					<p><?php esc_html_e( 'Single portfolio project pages are automatically protected. Only users logged in with roles that hold the read_private_portfolio capability can view project details.', 'ross-portfolio' ); ?></p>
					<p><?php esc_html_e( 'To grant or revoke access for roles (e.g. Subscriber, Customer, VIP Member), simply toggle their checkbox in the Role Access Permissions section above and click "Save Access Settings".', 'ross-portfolio' ); ?></p>
				</div>

				<div class="ross-docs-step">
					<h3><span class="ross-docs-badge">3</span> <?php esc_html_e( 'How to Display Portfolio Projects on a Page', 'ross-portfolio' ); ?></h3>
					<p><?php esc_html_e( 'There are two primary methods to display your portfolio projects on your site:', 'ross-portfolio' ); ?></p>
					<p><strong><?php esc_html_e( 'Method A: Automatic Archive Page', 'ross-portfolio' ); ?></strong><br>
					<?php
					$archive_url = get_post_type_archive_link( 'portfolio_project' );
					if ( ! $archive_url ) {
						$archive_url = home_url( '/portfolio_project/' );
					}
					/* translators: %s: archive URL */
					printf( esc_html__( 'WordPress automatically registers a project archive at: %s', 'ross-portfolio' ), '<code><a href="' . esc_url( $archive_url ) . '" target="_blank">' . esc_url( $archive_url ) . '</a></code>' );
					?>
					</p>
					<p><strong><?php esc_html_e( 'Method B: Custom Page Template (page-portfolio.php)', 'ross-portfolio' ); ?></strong><br>
					<?php esc_html_e( 'To display the portfolio on any standard page, create a page template (e.g. page-portfolio.php) in your theme and run a WP_Query loop using the public accessor functions:', 'ross-portfolio' ); ?>
					</p>
					<pre class="ross-code-box">&lt;?php
/**
 * Template Name: Portfolio Showcase Page
 */
get_header();

$args = array(
    'post_type'      => 'portfolio_project',
    'posts_per_page' => 12,
    'post_status'    => 'publish',
);

$projects = new WP_Query( $args );

if ( $projects->have_posts() ) : ?&gt;
    &lt;div class="portfolio-grid"&gt;
        &lt;?php while ( $projects->have_posts() ) : $projects->the_post(); ?&gt;
            &lt;article class="portfolio-card"&gt;
                &lt;?php if ( has_post_thumbnail() ) : ?&gt;
                    &lt;a href="&lt;?php the_permalink(); ?&gt;"&gt;&lt;?php the_post_thumbnail( 'medium_large' ); ?&gt;&lt;/a&gt;
                &lt;?php endif; ?&gt;
                &lt;h3&gt;&lt;a href="&lt;?php the_permalink(); ?&gt;"&gt;&lt;?php the_title(); ?&gt;&lt;/a&gt;&lt;/h3&gt;
                &lt;span class="project-type"&gt;&lt;?php echo esc_html( ross_get_project_type() ); ?&gt;&lt;/span&gt;
                &lt;p class="project-summary"&gt;&lt;?php echo esc_html( ross_get_project_summary() ); ?&gt;&lt;/p&gt;
                &lt;?php if ( ross_is_project_featured() ) : ?&gt;
                    &lt;span class="badge-featured"&gt;&lt;?php esc_html_e( 'Featured Project', 'ross-portfolio' ); ?&gt;&lt;/span&gt;
                &lt;?php endif; ?&gt;
                &lt;a class="button" href="&lt;?php the_permalink(); ?&gt;"&gt;&lt;?php esc_html_e( 'View Details', 'ross-portfolio' ); ?&gt;&lt;/a&gt;
            &lt;/article&gt;
        &lt;?php endwhile; wp_reset_postdata(); ?&gt;
    &lt;/div&gt;
&lt;?php endif;

get_footer();
?&gt;</pre>
				</div>

				<div class="ross-docs-step">
					<h3><span class="ross-docs-badge">4</span> <?php esc_html_e( 'REST API Access', 'ross-portfolio' ); ?></h3>
					<p><?php esc_html_e( 'Projects are available via the WordPress REST API endpoint at /wp-json/wp/v2/portfolio_projects. Unauthorized requests automatically receive redacted content, excerpts, and metadata.', 'ross-portfolio' ); ?></p>
				</div>
			</div>
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
		$title          = get_option( 'ross_portfolio_restriction_title', __( 'Private Client Portfolio', 'ross-portfolio' ) );
		$guest_message  = get_option( 'ross_portfolio_guest_message', __( 'This project is part of our confidential client portfolio. Please log in with authorized credentials to view details.', 'ross-portfolio' ) );
		$denied_message = get_option( 'ross_portfolio_denied_message', __( 'Your current account level does not have clearance to view this confidential portfolio item.', 'ross-portfolio' ) );

		ob_start();
		?>
		<style>
			.ross-glassmorphic-card {
				position: relative;
				max-width: 580px;
				margin: 36px auto;
				padding: 40px 32px;
				background: rgba(255, 255, 255, 0.65);
				backdrop-filter: blur(16px) saturate(180%);
				-webkit-backdrop-filter: blur(16px) saturate(180%);
				border: 1px solid rgba(255, 255, 255, 0.8);
				border-radius: 20px;
				box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.08), 0 0 1px 1px rgba(255, 255, 255, 0.9) inset;
				text-align: center;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
				color: #1e293b;
				box-sizing: border-box;
				overflow: hidden;
			}
			.ross-glassmorphic-card::before {
				content: "";
				position: absolute;
				top: -50%;
				left: -50%;
				width: 200%;
				height: 200%;
				background: radial-gradient(circle at center, rgba(59, 130, 246, 0.08) 0%, transparent 60%);
				pointer-events: none;
				z-index: 0;
			}
			.ross-glassmorphic-content {
				position: relative;
				z-index: 1;
			}
			.ross-lock-badge {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 56px;
				height: 56px;
				border-radius: 50%;
				background: rgba(37, 99, 235, 0.1);
				color: #2563eb;
				margin-bottom: 20px;
			}
			.ross-glassmorphic-card h3 {
				margin: 0 0 10px 0;
				font-size: 1.5rem;
				font-weight: 700;
				letter-spacing: -0.02em;
				color: #0f172a;
			}
			.ross-glassmorphic-card p {
				font-size: 0.95rem;
				line-height: 1.6;
				color: #475569;
				margin: 0 0 24px 0;
			}
			.ross-login-form-wrapper {
				max-width: 360px;
				margin: 0 auto;
				text-align: left;
				background: rgba(255, 255, 255, 0.7);
				border: 1px solid rgba(226, 232, 240, 0.8);
				border-radius: 12px;
				padding: 24px;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
			}
			.ross-login-form-wrapper label {
				display: block;
				margin-bottom: 6px;
				font-weight: 600;
				font-size: 0.8125rem;
				color: #334155;
				text-transform: uppercase;
				letter-spacing: 0.03em;
			}
			.ross-login-form-wrapper input[type="text"],
			.ross-login-form-wrapper input[type="password"] {
				width: 100%;
				padding: 11px 14px;
				margin-bottom: 16px;
				background: #ffffff;
				border: 1px solid #cbd5e1;
				border-radius: 8px;
				box-sizing: border-box;
				font-size: 0.95rem;
				color: #0f172a;
				transition: border-color 0.2s, box-shadow 0.2s;
			}
			.ross-login-form-wrapper input[type="text"]:focus,
			.ross-login-form-wrapper input[type="password"]:focus {
				outline: none;
				border-color: #2563eb;
				box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
			}
			.ross-login-form-wrapper .login-remember {
				margin-bottom: 16px;
				font-size: 0.875rem;
				color: #475569;
			}
			.ross-login-form-wrapper .login-remember label {
				display: inline-flex;
				align-items: center;
				font-weight: 400;
				text-transform: none;
				letter-spacing: normal;
				cursor: pointer;
			}
			.ross-login-form-wrapper .login-remember input[type="checkbox"] {
				margin-right: 8px;
			}
			.ross-login-form-wrapper input[type="submit"] {
				width: 100%;
				padding: 12px 18px;
				background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
				color: #ffffff;
				border: none;
				border-radius: 8px;
				font-weight: 600;
				font-size: 0.95rem;
				cursor: pointer;
				box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
				transition: transform 0.15s ease, box-shadow 0.15s ease;
			}
			.ross-login-form-wrapper input[type="submit"]:hover {
				background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
				box-shadow: 0 6px 14px rgba(37, 99, 235, 0.35);
			}
			.ross-login-form-wrapper input[type="submit"]:active {
				transform: translateY(1px);
			}
			.ross-access-badge-denied {
				display: inline-block;
				background: #fef2f2;
				border: 1px solid #fee2e2;
				color: #b91c1c;
				padding: 6px 14px;
				border-radius: 20px;
				font-size: 0.85rem;
				font-weight: 600;
				margin-top: 10px;
			}
		</style>
		<div class="ross-glassmorphic-card" role="alert" aria-live="polite">
			<div class="ross-glassmorphic-content">
				<div class="ross-lock-badge" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
				</div>
				<h3><?php echo esc_html( $title ); ?></h3>
				<?php if ( is_user_logged_in() ) : ?>
					<p><?php echo esc_html( $denied_message ); ?></p>
					<span class="ross-access-badge-denied"><?php esc_html_e( 'Access Level: Unauthorized', 'ross-portfolio' ); ?></span>
				<?php else : ?>
					<p><?php echo esc_html( $guest_message ); ?></p>
					<div class="ross-login-form-wrapper">
						<?php
						wp_login_form(
							array(
								'echo'           => true,
								'redirect'       => get_permalink(),
								'form_id'        => 'ross-portfolio-loginform',
								'label_username' => __( 'Username or Email', 'ross-portfolio' ),
								'label_password' => __( 'Password', 'ross-portfolio' ),
								'label_remember' => __( 'Remember Me', 'ross-portfolio' ),
								'label_log_in'   => __( 'Sign In to View', 'ross-portfolio' ),
								'remember'       => true,
							)
						);
						?>
					</div>
				<?php endif; ?>
			</div>
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
