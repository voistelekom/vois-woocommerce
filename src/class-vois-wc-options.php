<?php
defined('ABSPATH') || exit;

/** Option names (all prefixed vois_wc_) and default SMS texts. */
final class Vois_WC_Options
{
    const DEFAULT_TEMPLATES = [
        'pending'    => 'Sayın {musteri_adi}, {siparis_no} numaralı siparişiniz ödeme bekliyor. {magaza_adi}',
        'processing' => 'Sayın {musteri_adi}, {siparis_no} numaralı siparişiniz alındı ve hazırlanıyor. Tutar: {siparis_tutari}. {magaza_adi}',
        'on-hold'    => 'Sayın {musteri_adi}, {siparis_no} numaralı siparişiniz beklemede. Ödemeniz onaylanınca hazırlanacak. {magaza_adi}',
        'completed'  => 'Sayın {musteri_adi}, {siparis_no} numaralı siparişiniz tamamlandı. Bizi tercih ettiğiniz için teşekkürler. {magaza_adi}',
        'cancelled'  => 'Sayın {musteri_adi}, {siparis_no} numaralı siparişiniz iptal edildi. {magaza_adi}',
        'refunded'   => 'Sayın {musteri_adi}, {siparis_no} numaralı siparişinizin iadesi yapıldı. {magaza_adi}',
        'failed'     => 'Sayın {musteri_adi}, {siparis_no} numaralı siparişinizin ödemesi tamamlanamadı. {magaza_adi}',
        'admin'      => 'Yeni sipariş: {siparis_no} - {musteri_adi} {musteri_soyadi} - {siparis_tutari}',
    ];
    const CUSTOM_STATUS_TEMPLATE = 'Sayın {musteri_adi}, {siparis_no} numaralı siparişinizin durumu: {siparis_durumu}. {magaza_adi}';

    public static function get($name, $default = '')
    {
        $v = get_option('vois_wc_' . $name, $default);

        return $v === false || $v === null ? $default : $v;
    }

    public static function api_key()
    {
        return trim((string) self::get('api_key'));
    }

    public static function status_enabled($status)
    {
        return self::get('on_' . $status, $status === 'processing' ? 'yes' : 'no') === 'yes';
    }

    /** Saved template, or the built-in default when the option was never saved. */
    public static function template($key)
    {
        $default = isset(self::DEFAULT_TEMPLATES[$key]) ? self::DEFAULT_TEMPLATES[$key] : self::CUSTOM_STATUS_TEMPLATE;

        return (string) self::get('tpl_' . $key, $default);
    }
}
