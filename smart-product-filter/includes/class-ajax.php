<?php
/**
 * Kept as a compatibility file for the plugin structure.
 *
 * Filtering is intentionally performed against the normal WooCommerce
 * product archive URL instead of a separate WP_Query. This lets WooCommerce,
 * WoodMart and other archive integrations build the same main query.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
