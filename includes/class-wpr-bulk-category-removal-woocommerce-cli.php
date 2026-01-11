<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI integration for WPRepublic Bulk Category Removal.
 */
class WPR_Bulk_Category_Removal_CLI extends WP_CLI_Command {

	/**
	 * List product categories.
	 *
	 * @subcommand list-categories
	 *
	 * ## EXAMPLES
	 * wp wpr-bulk-category-removal-woocommerce list-categories
	 */
	public function list_categories( $args, $assoc_args ) {
		$categories = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]
		);

		if ( empty( $categories ) ) {
			WP_CLI::success( 'No product categories found.' );
			return;
		}

		$items = array_map(
			function ( $term ) {
				return [
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
					'count'   => $term->count,
				];
			},
			$categories
		);

		WP_CLI\Utils\format_items( 'table', $items, [ 'term_id', 'name', 'slug', 'count' ] );
	}

	/**
	 * Run cleanup for selected categories.
	 *
	 * ## OPTIONS
	 *
	 * [--term-id=<ids>]
	 * : Comma-separated term IDs.
	 *
	 * [--category-slug=<slugs>]
	 * : Comma-separated category slugs.
	 *
	 * [--dry-run]
	 * : Simulate cleanup (no deletions).
	 *
	 * ## EXAMPLES
	 * wp wpr-bulk-category-removal-woocommerce run --term-id=12,44 --dry-run
	 * wp wpr-bulk-category-removal-woocommerce run --category-slug=clothing,accessories
	 */
	public function run( $args, $assoc_args ) {
		$core = new WPR_Bulk_Category_Removal_Core();

		$dry_run = isset( $assoc_args['dry-run'] );

		$term_ids = [];

		if ( ! empty( $assoc_args['term-id'] ) ) {
			$term_ids = array_filter( array_map( 'absint', explode( ',', $assoc_args['term-id'] ) ) );
		} elseif ( ! empty( $assoc_args['category-slug'] ) ) {
			$slugs = array_filter( array_map( 'sanitize_title', explode( ',', $assoc_args['category-slug'] ) ) );
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term->term_id;
				}
			}
		}

		if ( empty( $term_ids ) ) {
			WP_CLI::error( 'No valid categories provided. Use --term-id or --category-slug.' );
		}

		$log = $core->run_cleanup( $term_ids, $dry_run );

		WP_CLI::success( 'Cleanup finished.' );
		WP_CLI::line( $log );
	}
}