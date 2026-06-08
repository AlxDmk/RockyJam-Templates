<?php
/**
 * Uninstall RockyJam Templates.
 * Removes all rj_template posts, post meta, and options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete all rj_template posts and their meta.
$post_ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'rj_template'"
);

foreach ( $post_ids as $id ) {
	wp_delete_post( (int) $id, true );
}

// Delete product meta referencing templates.
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_rj_template_id' ], [ '%s' ] );

// Remove cache directory.
$upload_dir = wp_upload_dir();
$cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'rjt-cache/';

if ( is_dir( $cache_dir ) ) {
	array_map( 'unlink', glob( $cache_dir . '*' ) );
	rmdir( $cache_dir );
}
