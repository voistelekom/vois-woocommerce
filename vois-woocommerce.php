<?php
/**
 * Plugin Name:          VOIS for WooCommerce
 * Plugin URI:           https://docs.vois.com.tr/woocommerce/
 * Description:          WooCommerce siparişleri için VOIS SMS bildirimleri ve sipariş ekranından santral ile tıkla-ara.
 * Version:              1.0.0
 * Author:               VOIS
 * Author URI:           https://vois.com.tr
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          vois-woocommerce
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      11.3
 */

defined('ABSPATH') || exit;

define('VOIS_WC_FILE', __FILE__);
define('VOIS_WC_VERSION', '1.0.0');

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', function () {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('VOIS for WooCommerce için WooCommerce eklentisinin kurulu ve etkin olması gerekir.', 'vois-woocommerce') . '</p></div>';
        });

        return;
    }

    foreach (['options', 'phone', 'template', 'errors', 'api', 'notifier', 'admin'] as $part) {
        require_once __DIR__ . '/src/class-vois-wc-' . $part . '.php';
    }

    $notifier = new Vois_WC_Notifier();
    $notifier->register();
    if (is_admin() || wp_doing_ajax()) {
        (new Vois_WC_Admin($notifier))->register();
    }

    add_filter('woocommerce_get_settings_pages', function ($pages) {
        if (! class_exists('WC_Settings_Page')) {
            return $pages;
        }
        require_once __DIR__ . '/src/class-vois-wc-settings.php';
        $pages[] = new Vois_WC_Settings_Page();

        return $pages;
    });

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=vois')) . '">' . esc_html__('Ayarlar', 'vois-woocommerce') . '</a>');

        return $links;
    });
});
