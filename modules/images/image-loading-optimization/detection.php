<?php
/**
 * Detection for image loading optimization.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Prints the script for detecting loaded images and the LCP element.
 *
 * @todo This script should not be printed if the page was requested with non-removal (non-canonical) query args.
 */
function ilo_print_detection_script() {
	$query_vars = ilo_get_normalized_query_vars();
	$slug       = ilo_get_page_metrics_slug( $query_vars );

	// Abort if we already have all the sample size we need for all breakpoints.
	$needed_minimum_viewport_widths = ilo_get_needed_minimum_viewport_widths( $slug );
	if ( ! ilo_needs_page_metric_for_breakpoint( $needed_minimum_viewport_widths ) ) {
		return;
	}

	// Abort if storage is locked.
	if ( ilo_is_page_metric_storage_locked() ) {
		return;
	}

	$serve_time = ceil( microtime( true ) * 1000 );

	/**
	 * Filters the time window between serve time and run time in which loading detection is allowed to run.
	 *
	 * Allow this amount of milliseconds between when the page was first generated (and perhaps cached) and when the
	 * detect function on the page is allowed to perform its detection logic and submit the request to store the results.
	 * This avoids situations in which there is missing detection metrics in which case a site with page caching which
	 * also has a lot of traffic could result in a cache stampede.
	 *
	 * @since n.e.x.t
	 * @todo The value should probably be something like the 99th percentile of Time To Last Byte (TTLB) for WordPress sites in CrUX.
	 *
	 * @param int $detection_time_window Detection time window in milliseconds.
	 */
	$detection_time_window = apply_filters( 'perflab_image_loading_detection_time_window', 5000 );

	$detect_args = array(
		'serveTime'                   => $serve_time,
		'detectionTimeWindow'         => $detection_time_window,
		'isDebug'                     => WP_DEBUG,
		'restApiEndpoint'             => rest_url( ILO_REST_API_NAMESPACE . ILO_PAGE_METRICS_ROUTE ),
		'restApiNonce'                => wp_create_nonce( 'wp_rest' ),
		'pageMetricsSlug'             => $slug,
		'pageMetricsNonce'            => ilo_get_page_metrics_storage_nonce( $slug ),
		'neededMinimumViewportWidths' => $needed_minimum_viewport_widths,
	);
	wp_print_inline_script_tag(
		sprintf(
			'import detect from %s; detect( %s );',
			wp_json_encode( add_query_arg( 'ver', PERFLAB_VERSION, plugin_dir_url( __FILE__ ) . 'detection/detect.js' ) ),
			wp_json_encode( $detect_args )
		),
		array( 'type' => 'module' )
	);
}
add_action( 'wp_print_footer_scripts', 'ilo_print_detection_script' );
