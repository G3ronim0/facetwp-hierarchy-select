<?php
/*
Plugin Name: FacetWP - Hierarchy Select
Description: Hierarchy select facet type
Version: 0.4.1
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-hierarchy-select
Text Domain: facetwp-hierarchy-select
Domain Path: /languages
*/

defined( 'ABSPATH' ) or exit;

define( 'FWPHS_VERSION', '0.4.1' );
define( 'FWPHS_DIR', dirname( __FILE__ ) );
define( 'FWPHS_URL', plugins_url( '', __FILE__ ) );
define( 'FWPHS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Internationalization
 */
 function facetwp_hierarchy_select_i18n() {
   load_plugin_textdomain( 'facetwp-hierarchy-select', false, basename( dirname( __FILE__ ) ) . '/languages' );
 }
 add_action( 'init', 'facetwp_hierarchy_select_i18n' );

/**
 * FacetWP registration hook
 */
add_filter( 'facetwp_facet_types', function( $types ) {
    include( dirname( __FILE__ ) . '/class-hierarchy-select.php' );
    $types['hierarchy_select'] = new FacetWP_Facet_Hierarchy_Select_Addon();
    return $types;
});
