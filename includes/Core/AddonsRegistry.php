<?php

namespace RockyJamTemplates\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects hook declarations from active RockyJam Addons.
 *
 * Each addon that wants to expose its WC-hook callbacks to the Hook Manager
 * must add its declarations via the WordPress filter:
 *
 *   add_filter( 'rockyjam_addon_hooks', function ( array $hooks ): array {
 *       $hooks[] = [
 *           'addon_id'   => 'my-addon',          // slug
 *           'addon_name' => 'My Addon',           // human label
 *           'hook'       => 'woocommerce_single_product_summary',
 *           'function'   => 'my_addon_render_block',
 *           'priority'   => 25,
 *           'label'      => 'My Custom Block',    // optional
 *       ];
 *       return $hooks;
 *   } );
 *
 * AddonsRegistry::get() fires the filter and returns the merged, sanitized list.
 *
 * @package RockyJamTemplates
 */
class AddonsRegistry {

	/**
	 * Returns all addon hook declarations.
	 *
	 * Fires the `rockyjam_addon_hooks` filter so each addon can append its items.
	 * Also falls back to scanning WP's global $wp_filter to find any functions
	 * with naming convention `rockyjam_{addonSlug}_*` that are hooked to
	 * woocommerce_* actions — this covers addons that haven't adopted the filter yet.
	 *
	 * @return array[] Each item: { addon_id, addon_name, hook, function, priority, label }
	 */
	public static function get(): array {
		// 1. Explicit declarations via filter (preferred, accurate).
		$declared = apply_filters( 'rockyjam_addon_hooks', [] );
		if ( ! is_array( $declared ) ) {
			$declared = [];
		}

		$results = [];
		foreach ( $declared as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$hook     = sanitize_key( $item['hook']        ?? '' );
			$function = sanitize_key( $item['function']    ?? '' );
			$addon_id = sanitize_key( $item['addon_id']    ?? '' );
			if ( ! $hook || ! $function || ! $addon_id ) {
				continue;
			}
			$results[] = [
				'addon_id'   => $addon_id,
				'addon_name' => sanitize_text_field( $item['addon_name'] ?? $addon_id ),
				'hook'       => $hook,
				'function'   => $function,
				'priority'   => max( 1, min( 999, (int) ( $item['priority'] ?? 10 ) ) ),
				'label'      => sanitize_text_field( $item['label'] ?? $function ),
				'source'     => 'declared',
			];
		}

		// 2. Auto-discovery: scan $wp_filter for woocommerce_* hooks,
		//    find callbacks whose names start with "rockyjam_" and that
		//    were NOT already declared above.
		$discovered = self::autodiscover( $results );
		$results    = array_merge( $results, $discovered );

		return $results;
	}

	/**
	 * Scans global $wp_filter for functions matching the rockyjam naming
	 * convention that are attached to woocommerce_* hooks.
	 *
	 * Convention: function name starts with "rockyjam_" or matches an
	 * enabled addon's slug converted to snake_case prefix.
	 *
	 * @param array[] $already Already declared entries (to skip duplicates).
	 * @return array[]
	 */
	private static function autodiscover( array $already ): array {
		global $wp_filter;

		// Build set of (hook, function) pairs already declared.
		$seen = [];
		foreach ( $already as $item ) {
			$seen[ $item['hook'] . '|' . $item['function'] ] = true;
		}

		// Get list of enabled addon IDs for name mapping (if RJA is active).
		$addon_map = self::get_addon_name_map();

		$discovered = [];

		foreach ( $wp_filter as $hook_name => $hook_obj ) {
			if ( 0 !== strpos( $hook_name, 'woocommerce_' ) ) {
				continue;
			}

			// $hook_obj is a WP_Hook instance.
			$callbacks = method_exists( $hook_obj, 'offsetGet' ) ? [] : $hook_obj->callbacks ?? [];
			if ( empty( $callbacks ) ) {
				continue;
			}

			foreach ( $callbacks as $priority => $cbs ) {
				foreach ( $cbs as $cb ) {
					$func = $cb['function'] ?? null;
					if ( ! is_string( $func ) ) {
						continue;
					}
					// Only pick up functions that start with rockyjam_ prefix.
					if ( 0 !== strpos( $func, 'rockyjam_' ) ) {
						continue;
					}
					// Skip already-declared.
					if ( isset( $seen[ $hook_name . '|' . $func ] ) ) {
						continue;
					}

					// Try to map to an addon slug.
					$addon_id   = self::guess_addon_id( $func, $addon_map );
					$addon_name = $addon_map[ $addon_id ] ?? $addon_id;

					$discovered[]                          = [
						'addon_id'   => $addon_id,
						'addon_name' => $addon_name,
						'hook'       => $hook_name,
						'function'   => $func,
						'priority'   => (int) $priority,
						'label'      => $func,
						'source'     => 'autodiscovered',
					];
					$seen[ $hook_name . '|' . $func ] = true;
				}
			}
		}

		return $discovered;
	}

	/**
	 * Returns [ addon_id => addon_name ] for all enabled addons (if RJA active).
	 *
	 * @return array<string, string>
	 */
	private static function get_addon_name_map(): array {
		if ( ! function_exists( 'rockyjam_addons' ) ) {
			return [];
		}
		try {
			$addons = rockyjam_addons()->addon_manager()->get_addons();
		} catch ( \Throwable $e ) {
			return [];
		}
		$map = [];
		foreach ( $addons as $id => $data ) {
			$map[ (string) $id ] = $data['name'] ?? $id;
		}
		return $map;
	}

	/**
	 * Attempts to guess which addon a function belongs to by matching
	 * the function name prefix against known addon slugs.
	 *
	 * e.g. rockyjam_my_addon_render_block → addon_id = "my-addon"
	 *
	 * @param string                $func      Function name.
	 * @param array<string, string> $addon_map Known addon id => name map.
	 * @return string addon_id or "unknown"
	 */
	private static function guess_addon_id( string $func, array $addon_map ): string {
		// Strip leading "rockyjam_"
		$rest = substr( $func, strlen( 'rockyjam_' ) );

		// Try each known addon slug (converted to snake_case) as prefix.
		foreach ( $addon_map as $id => $name ) {
			$prefix = str_replace( '-', '_', $id ) . '_';
			if ( 0 === strpos( $rest, $prefix ) ) {
				return $id;
			}
		}

		// Fallback: treat the first word after rockyjam_ as the addon id.
		$parts = explode( '_', $rest, 2 );
		if ( ! empty( $parts[0] ) ) {
			return $parts[0];
		}

		return 'unknown';
	}

	/**
	 * Returns addon hook declarations grouped by hook name.
	 * Useful for rendering in the UI.
	 *
	 * @return array<string, array[]>  hook_name => [ ...callbacks ]
	 */
	public static function get_by_hook(): array {
		$grouped = [];
		foreach ( self::get() as $item ) {
			$grouped[ $item['hook'] ][] = $item;
		}
		return $grouped;
	}

	/**
	 * Returns true if RockyJam Addons plugin is active and loaded.
	 */
	public static function is_addons_active(): bool {
		return function_exists( 'rockyjam_addons' );
	}
}
