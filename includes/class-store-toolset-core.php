<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core functionality for Store Toolset.
 */
class Store_Toolset_Core {

	private $log_file_path;
	private $log_dir;

	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->log_dir = trailingslashit( $upload_dir['basedir'] ) . 'store-toolset-logs';

		if ( ! file_exists( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );
		}

		$this->log_file_path = trailingslashit( $this->log_dir ) . 'store-toolset-' . gmdate( 'Y-m-d_His' ) . '.log';
	}

	public function get_product_categories() {
		return get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]
		);
	}

	public function run_cleanup( array $term_ids, $dry_run = true ) {
		$dry_run = (bool) $dry_run;

		$log = [];
		$log[] = 'Store Toolset run started at ' . gmdate( 'c' );
		$log[] = 'Mode: ' . ( $dry_run ? 'DRY RUN' : 'LIVE' );
		$log[] = 'Term IDs: ' . implode( ',', $term_ids );
		$log[] = '------------------------------------------------------------';

		foreach ( $term_ids as $term_id ) {
			$log[] = $this->cleanup_category_products( $term_id, $dry_run );
		}

		$log[] = '------------------------------------------------------------';
		$log[] = 'Store Toolset run finished at ' . gmdate( 'c' );

		$log_output = implode( "\n", array_filter( $log ) );
		$this->write_log( $log_output );

		return $log_output;
	}

	private function cleanup_category_products( $term_id, $dry_run ) {
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return "Invalid term_id: {$term_id}";
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => [ $term_id ],
				],
			],
		];

		$product_ids = get_posts( $args );
		$count       = is_array( $product_ids ) ? count( $product_ids ) : 0;

		$out = "Category '{$term->name}' (ID {$term_id}) -> Products found: {$count}";

		if ( $dry_run ) {
			return $out . ' (dry run, no deletions)';
		}

		foreach ( $product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}

		return $out . " (deleted: {$count})";
	}

	private function write_log( $contents ) {
		if ( empty( $contents ) ) {
			return;
		}

		@file_put_contents( $this->log_file_path, $contents );
	}
}
