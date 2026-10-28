<?php
// Mock WP functions for unit testing plugin logic without full WordPress installation
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname(__DIR__) . '/' );
}

$wp_options = array();
$wp_roles = (object) array(
	'roles' => array(
		'administrator' => array( 'name' => 'Administrator' ),
		'editor'        => array( 'name' => 'Editor' ),
		'author'        => array( 'name' => 'Author' ),
		'subscriber'    => array( 'name' => 'Subscriber' ),
	),
);

class Mock_Role {
	public $key;
	public $capabilities = array();
	public function __construct( $key ) {
		$this->key = $key;
	}
	public function add_cap( $cap ) {
		$this->capabilities[ $cap ] = true;
	}
	public function remove_cap( $cap ) {
		unset( $this->capabilities[ $cap ] );
	}
}

$mock_roles_objs = array(
	'administrator' => new Mock_Role( 'administrator' ),
	'editor'        => new Mock_Role( 'editor' ),
	'author'        => new Mock_Role( 'author' ),
	'subscriber'    => new Mock_Role( 'subscriber' ),
);

function plugin_dir_path( $file ) { return dirname(__DIR__) . '/'; }
function plugin_dir_url( $file ) { return 'http://localhost/wp-content/plugins/ross-portfolio/'; }
function add_action( $hook, $cb ) {}
function add_filter( $hook, $cb ) {}
function register_activation_hook( $file, $cb ) {}
function register_deactivation_hook( $file, $cb ) {}
function __( $str, $domain = '' ) { return $str; }
function _x( $str, $ctx, $domain = '' ) { return $str; }
function esc_html__( $str, $domain = '' ) { return $str; }
function esc_html( $str ) { return htmlspecialchars( $str, ENT_QUOTES ); }
function esc_attr( $str ) { return htmlspecialchars( $str, ENT_QUOTES ); }
function esc_textarea( $str ) { return htmlspecialchars( $str, ENT_QUOTES ); }
function sanitize_text_field( $str ) { return trim( strip_tags( $str ) ); }
function sanitize_textarea_field( $str ) { return trim( strip_tags( $str ) ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function wp_kses_post( $content ) { return $content; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function register_post_type( $type, $args ) {}
function register_taxonomy( $tax, $object_type, $args ) {}
function register_post_meta( $post_type, $meta_key, $args ) {}
function add_meta_box( $id, $title, $callback, $screen, $context, $priority ) {}
function wp_nonce_field( $action, $name ) {}
function wp_verify_nonce( $nonce, $action ) { return true; }
function checked( $val, $target ) { if ( $val == $target ) echo 'checked="checked"'; }
function update_post_meta( $id, $key, $val ) { global $mock_post_meta; $mock_post_meta[$id][$key] = $val; }
function get_post_meta( $id, $key, $single = false ) { global $mock_post_meta; return isset($mock_post_meta[$id][$key]) ? $mock_post_meta[$id][$key] : ''; }
function get_the_ID() { return 101; }
function is_user_logged_in() { global $mock_user_logged_in; return !empty($mock_user_logged_in); }
function get_current_user_id() { global $mock_current_user_id; return isset($mock_current_user_id) ? $mock_current_user_id : 0; }
function user_can( $user_id, $cap ) {
	global $mock_roles_objs, $mock_user_roles;
	if ( isset($mock_user_roles[$user_id]) ) {
		foreach ( $mock_user_roles[$user_id] as $r ) {
			if ( isset($mock_roles_objs[$r]) && !empty($mock_roles_objs[$r]->capabilities[$cap]) ) {
				return true;
			}
		}
	}
	return false;
}
function get_userdata( $user_id ) {
	global $mock_user_roles;
	if ( isset($mock_user_roles[$user_id]) ) {
		return (object) array( 'ID' => $user_id, 'roles' => $mock_user_roles[$user_id] );
	}
	return false;
}
function get_option( $opt, $default = false ) { global $wp_options; return isset($wp_options[$opt]) ? $wp_options[$opt] : $default; }
function update_option( $opt, $val ) { global $wp_options; $wp_options[$opt] = $val; return true; }
function wp_roles() { global $wp_roles; return $wp_roles; }
function get_role( $role ) { global $mock_roles_objs; return isset($mock_roles_objs[$role]) ? $mock_roles_objs[$role] : null; }
function translate_user_role( $name ) { return $name; }
function flush_rewrite_rules() {}

// Include core plugin and helpers
require_once dirname(__DIR__) . '/ross-portfolio-core.php';

// Execution of tests
echo "=== RUNNING UNIT TESTS ===\n";

// Test 1: Helper functions with mock meta
global $mock_post_meta;
$mock_post_meta[101] = array(
	'_ross_project_type' => 'Fullstack SaaS',
	'_ross_project_summary' => 'A web app for metrics.',
	'_ross_project_solution' => '<p>Built with PHP</p>',
	'_ross_project_featured' => true,
);

assert( ross_get_project_type(101) === 'Fullstack SaaS' );
assert( ross_get_project_summary(101) === 'A web app for metrics.' );
assert( ross_get_project_solution(101) === '<p>Built with PHP</p>' );
assert( ross_is_project_featured(101) === true );
echo "Test 1 Passed: Helper data accessors work correctly.\n";

// Test 2: Role sync capabilities & access control
global $mock_user_roles, $mock_current_user_id;

// Setup user 1 as Subscriber, user 2 as Editor
$mock_user_roles = array(
	1 => array( 'subscriber' ),
	2 => array( 'editor' ),
);

// Initially default roles are admin & editor
Ross_Portfolio_Core::sync_capabilities();

$mock_current_user_id = 1; // Subscriber
assert( ross_user_has_access(1) === false );

$mock_current_user_id = 2; // Editor
assert( ross_user_has_access(2) === true );

echo "Test 2 Passed: Access control based on roles and capabilities works as expected.\n";

// Test 3: Updating allowed roles via Settings API
$core = Ross_Portfolio_Core::get_instance();
$core->sanitize_allowed_roles( array( 'subscriber', 'editor' ) );

// Now subscriber should have access
assert( ross_user_has_access(1) === true );
echo "Test 3 Passed: Role capability synchronization updates permissions correctly.\n";

echo "ALL TESTS PASSED SUCCESSFULLY!\n";
