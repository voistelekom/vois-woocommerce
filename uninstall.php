<?php
// Runs when the plugin is deleted from WordPress: removes its settings, user extensions and queued jobs.
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('vois_wc_') . '%', $wpdb->esc_like('_transient_vois_wc_') . '%', $wpdb->esc_like('_transient_timeout_vois_wc_') . '%'));
delete_metadata('user', 0, 'vois_extension', '', true);
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('vois_wc_send_sms');
}
