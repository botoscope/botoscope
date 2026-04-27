<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$tables = [
    'advertising',
    'b2b',
    'booking_reservations',
    'booking_slots',
    'booking_slots_targeted',
    'broadcast',
    'controls',
    'elogios',
    'extensions',
    'interface_translations',
    'marketing_campaigns',
    'marketing_campaigns_products',
    'marketing_campaigns_products_excluded',
    'marketing_campaigns_terms',
    'marketing_strategies',
    'marketing_strategies_formulas',
    'meta',
    'meta_pack',
    'meta_products',
    'payment',
    'products_translations',
    'product_attributes',
    'shipping',
    //'pickup_points',
    //'sizecharts',
    //'sizecharts_tables',
    'support',
    'support_interactions',
    'taxonomies_translations',
    'translations',
];

//dangerous zone
global $wpdb;
foreach ($tables as $table) {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is constructed from internal prefix and hardcoded string, not user input. wpdb->prepare() does not support table name placeholders.
    $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($wpdb->prefix . "botoscope_" . $table) . "`");
    //delete_option("botoscope_botoscope_{$table}_is_installed");
}

$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'botoscope\_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_botoscope\_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_botoscope\_%'");

$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'botoscope\_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'botoscope\_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_botoscope\_%'");
