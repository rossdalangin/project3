<?php
/**
 * Helper Functions
 *
 * @package Ross_Portfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get project type meta value.
 *
 * @param int $post_id Post ID. Optional.
 * @return string
 */
function ross_get_project_type( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	return (string) get_post_meta( $post_id, '_ross_project_type', true );
}

/**
 * Get project summary meta value.
 *
 * @param int $post_id Post ID. Optional.
 * @return string
 */
function ross_get_project_summary( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	return (string) get_post_meta( $post_id, '_ross_project_summary', true );
}

/**
 * Get project solution meta value.
 *
 * @param int $post_id Post ID. Optional.
 * @return string
 */
function ross_get_project_solution( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	return (string) get_post_meta( $post_id, '_ross_project_solution', true );
}

/**
 * Check if project is marked as featured.
 *
 * @param int $post_id Post ID. Optional.
 * @return bool
 */
function ross_is_project_featured( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}
	return (bool) get_post_meta( $post_id, '_ross_project_featured', true );
}

/**
 * Check whether current user has access to view restricted content.
 * Checks for 'read_private_portfolio' capability or user role match against allowed roles setting.
 *
 * @param int|WP_User $user Optional user ID or WP_User object. Defaults to current user.
 * @return bool
 */
function ross_user_has_access( $user = null ) {
	if ( null === $user ) {
		$user_id = get_current_user_id();
	} elseif ( $user instanceof WP_User ) {
		$user_id = $user->ID;
	} else {
		$user_id = (int) $user;
	}

	if ( ! $user_id ) {
		return false;
	}

	// First check explicit capability read_private_portfolio
	if ( user_can( $user_id, 'read_private_portfolio' ) ) {
		return true;
	}

	// Administrators always have full access
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	// Check user roles against allowed roles setting
	$allowed_roles = get_option( 'ross_portfolio_allowed_roles', array( 'administrator', 'editor' ) );
	if ( ! is_array( $allowed_roles ) ) {
		$allowed_roles = array();
	}

	$user_obj = get_userdata( $user_id );
	if ( ! $user_obj || empty( $user_obj->roles ) ) {
		return false;
	}

	foreach ( $user_obj->roles as $role ) {
		if ( in_array( $role, $allowed_roles, true ) ) {
			return true;
		}
	}

	return false;
}
