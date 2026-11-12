<?php
defined('ABSPATH') || exit;

/** SMS template placeholders: {musteri_adi}, {siparis_no} … */
final class Vois_WC_Template
{
    const PLACEHOLDERS = ['musteri_adi', 'musteri_soyadi', 'siparis_no', 'siparis_tutari', 'siparis_durumu', 'siparis_tarihi', 'odeme_yontemi', 'magaza_adi', 'kargo_takip'];

    /** Replaces {name} with $vars[name]; unknown placeholders stay as written. */
    public static function render($template, array $vars)
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, (string) $template);
    }

    /** @param WC_Order $order */
    public static function order_vars($order, $tracking_meta_key)
    {
        $date = $order->get_date_created();
        $total = number_format((float) $order->get_total(), 2, ',', '.');
        $currency = (string) $order->get_currency();

        return [
            'musteri_adi'    => (string) $order->get_billing_first_name(),
            'musteri_soyadi' => (string) $order->get_billing_last_name(),
            'siparis_no'     => (string) $order->get_order_number(),
            'siparis_tutari' => $total . ' ' . ($currency === 'TRY' ? 'TL' : $currency),
            'siparis_durumu' => (string) wc_get_order_status_name($order->get_status()),
            'siparis_tarihi' => $date ? $date->date_i18n('d.m.Y') : '',
            'odeme_yontemi'  => (string) $order->get_payment_method_title(),
            'magaza_adi'     => (string) get_bloginfo('name'),
            'kargo_takip'    => $tracking_meta_key !== '' ? (string) $order->get_meta($tracking_meta_key, true) : '',
        ];
    }
}
