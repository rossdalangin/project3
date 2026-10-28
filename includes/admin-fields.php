<?php
/**
 * Admin Meta Boxes for Portfolio Projects
 *
 * @package Ross_Portfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ross_Portfolio_Admin_Fields
 */
class Ross_Portfolio_Admin_Fields {

	/**
	 * Single Instance
	 *
	 * @var Ross_Portfolio_Admin_Fields|null
	 */
	private static $instance = null;

	/**
	 * Main Ross_Portfolio_Admin_Fields Instance.
	 *
	 * @return Ross_Portfolio_Admin_Fields
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_portfolio_project', array( $this, 'save_meta_boxes' ), 10, 2 );
	}

	/**
	 * Register meta box for portfolio_project CPT.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'ross_portfolio_project_details',
			__( 'Project Details', 'ross-portfolio' ),
			array( $this, 'render_meta_box' ),
			'portfolio_project',
			'normal',
			'high'
		);
	}

	/**
	 * Render Meta Box HTML.
	 *
	 * @param WP_Post $post
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'ross_portfolio_save_meta_box', 'ross_portfolio_meta_box_nonce' );

		$project_type     = get_post_meta( $post->ID, '_ross_project_type', true );
		$project_summary  = get_post_meta( $post->ID, '_ross_project_summary', true );
		$project_solution = get_post_meta( $post->ID, '_ross_project_solution', true );
		$project_featured = get_post_meta( $post->ID, '_ross_project_featured', true );
		?>
		<style>
			.ross-meta-field {
				margin-bottom: 15px;
			}
			.ross-meta-field label {
				display: block;
				font-weight: 600;
				margin-bottom: 5px;
			}
			.ross-meta-field input[type="text"],
			.ross-meta-field textarea {
				width: 100%;
			}
		</style>
		<div class="ross-meta-field">
			<label for="ross_project_type"><?php echo esc_html__( 'Project Type:', 'ross-portfolio' ); ?></label>
			<input type="text" id="ross_project_type" name="ross_project_type" value="<?php echo esc_attr( $project_type ); ?>" placeholder="e.g. Web Application, Mobile App, API Integration">
		</div>

		<div class="ross-meta-field">
			<label for="ross_project_summary"><?php echo esc_html__( 'Project Summary:', 'ross-portfolio' ); ?></label>
			<textarea id="ross_project_summary" name="ross_project_summary" rows="3" placeholder="Brief summary of the project..."><?php echo esc_textarea( $project_summary ); ?></textarea>
		</div>

		<div class="ross-meta-field">
			<label for="ross_project_solution"><?php echo esc_html__( 'Project Solution:', 'ross-portfolio' ); ?></label>
			<textarea id="ross_project_solution" name="ross_project_solution" rows="5" placeholder="Detailed solution architecture and features..."><?php echo esc_textarea( $project_solution ); ?></textarea>
		</div>

		<div class="ross-meta-field">
			<label for="ross_project_featured">
				<input type="checkbox" id="ross_project_featured" name="ross_project_featured" value="1" <?php checked( $project_featured, true ); ?>>
				<?php echo esc_html__( 'Featured Project', 'ross-portfolio' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Save Meta Box values on post save.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_meta_boxes( $post_id, $post ) {
		// Verify Nonce
		if ( ! isset( $_POST['ross_portfolio_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ross_portfolio_meta_box_nonce'] ) ), 'ross_portfolio_save_meta_box' ) ) {
			return;
		}

		// Check autosave or revision
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check Capability Authorization
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Sanitize & Save Project Type
		if ( isset( $_POST['ross_project_type'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_POST['ross_project_type'] ) );
			update_post_meta( $post_id, '_ross_project_type', $type );
		}

		// Sanitize & Save Project Summary
		if ( isset( $_POST['ross_project_summary'] ) ) {
			$summary = sanitize_textarea_field( wp_unslash( $_POST['ross_project_summary'] ) );
			update_post_meta( $post_id, '_ross_project_summary', $summary );
		}

		// Sanitize & Save Project Solution
		if ( isset( $_POST['ross_project_solution'] ) ) {
			$solution = wp_kses_post( wp_unslash( $_POST['ross_project_solution'] ) );
			update_post_meta( $post_id, '_ross_project_solution', $solution );
		}

		// Sanitize & Save Featured Status
		$featured = isset( $_POST['ross_project_featured'] ) && '1' === $_POST['ross_project_featured'];
		update_post_meta( $post_id, '_ross_project_featured', $featured );
	}
}
