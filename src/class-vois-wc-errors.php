<?php
defined('ABSPATH') || exit;

/** Turns VOIS / SMS error codes into Turkish text for shop staff. */
final class Vois_WC_Errors
{
    const MAP = [
        'no_key'                            => 'VOIS API anahtarı girilmemiş. WooCommerce › Ayarlar › VOIS sayfasından ekleyin.',
        'no_sender'                         => 'SMS gönderen başlığı seçilmemiş. WooCommerce › Ayarlar › VOIS sayfasından seçin.',
        'unauthorized'                      => 'VOIS API anahtarı geçersiz ya da iptal edilmiş. Panelden yeni bir anahtar oluşturun.',
        'insufficient_scope'                => 'VOIS API anahtarının bu işlem için yetkisi yok. Panelde anahtarı yeniden oluştururken gerekli yetkiyi işaretleyin.',
        'sms_not_configured'                => 'Bu VOIS hesabında SMS hizmeti tanımlı değil. VOIS destek ile görüşün.',
        'sms_blocked'                       => 'SMS gönderimi şu an hesabınızda durdurulmuş. VOIS destek ile görüşün.',
        'rate_limited'                      => 'Kısa sürede çok fazla istek gönderildi. Biraz bekleyip tekrar deneyin.',
        'voice_not_enabled'                 => 'Bu VOIS hesabında tıkla-ara (Ses API) açık değil. VOIS destek ile görüşün.',
        'extension_unavailable'             => 'Dahiliye bağlı bir telefon yok. Telefonun açık ve bağlı olduğunu kontrol edin.',
        'extension_busy'                    => 'Dahili şu an başka bir görüşmede.',
        'extension_not_found'               => 'Bu dahili numarası santralde bulunamadı. Profilinizdeki VOIS dahili numarasını kontrol edin.',
        'number_not_allowed'                => 'Bu numara aranamıyor (yurt dışı ya da özel ücretli numaralar kapalı).',
        'number_blocked'                    => 'Bu numaranın aranması engellenmiş.',
        'too_many_calls'                    => 'Aynı anda çok fazla çağrı var. Biraz sonra tekrar deneyin.',
        'daily_limit'                       => 'Günlük tıkla-ara sınırına ulaşıldı.',
        'pbx_unavailable'                   => 'Santrale şu an ulaşılamıyor. Biraz sonra tekrar deneyin.',
        'invalid_request'                   => 'İstek eksik ya da hatalı.',
        'network'                           => 'VOIS\'e ulaşılamadı. Sitenizin internet bağlantısını kontrol edip tekrar deneyin.',
        'bad_response'                      => 'VOIS\'ten beklenmeyen bir yanıt geldi. Sürerse VOIS destek ile görüşün.',
        'ERR_USER_CREDIT_REQUIRED'          => 'SMS bakiyeniz yetersiz.',
        'ERR_PARENT_USER_CREDIT_REQUIRED'   => 'SMS bakiyeniz yetersiz. VOIS destek ile görüşün.',
        'ERR_INVALID_SMS_SENDER'            => 'Gönderen başlığı hatalı ya da onaylı değil. Ayarlardan onaylı bir başlık seçin.',
        'ERR_EMPTY_SMS_PACKAGE'             => 'Numara geçersiz ya da kara listede olduğu için SMS gönderilmedi.',
        'ERR_INVALID_PARAM'                 => 'SMS isteğinde eksik ya da geçersiz bir alan var.',
        'ERR_BAD_REQUEST'                   => 'SMS isteği hatalı.',
        'ERR_SMS_PKG_CUSTOM_ID_DUPLICATION' => 'Bu SMS daha önce gönderilmiş.',
        'ERR_SMS_PKG_DUPLICATION'           => 'Aynı mesaj bu numaraya son 30 dakika içinde zaten gönderilmiş.',
        'ERR_RATE_LIMIT'                    => 'Kısa sürede çok fazla SMS isteği gönderildi. Biraz bekleyip tekrar deneyin.',
        'ERR_CONCURRENT_REQUEST_LIMIT'      => 'Aynı anda çok fazla SMS isteği gönderildi. Biraz bekleyip tekrar deneyin.',
        'ERR_SYSTEM_MAINTENANCE'            => 'SMS servisi bakımda. Bir süre sonra tekrar deneyin.',
        'ERR_SERVICE_UNAVAILABLE'           => 'SMS servisine şu an ulaşılamıyor. Birkaç dakika sonra tekrar deneyin.',
        'ERR_INTERNAL_SERVER'               => 'SMS servisinde bir hata oldu. Tekrar deneyin, sürerse VOIS destek ile görüşün.',
    ];

    /** Codes whose server text names the detail (scope, extension) and is written by VOIS itself. */
    const USE_SERVER_TEXT = ['insufficient_scope', 'extension_unavailable', 'extension_busy', 'extension_not_found', 'invalid_request', 'number_not_allowed'];

    public static function message($code, $server_message = null, $status = 0)
    {
        $code = (string) $code;
        $server_message = self::clean($server_message);
        if ($server_message !== '' && in_array($code, self::USE_SERVER_TEXT, true)) {
            return $server_message;
        }
        if (isset(self::MAP[$code])) {
            return self::MAP[$code];
        }
        if (strpos($code, 'ERR_') === 0) {
            return sprintf('SMS gönderilemedi (%s).', $code);
        }
        if ($server_message !== '') {
            return $server_message;
        }

        return sprintf('İşlem yapılamadı (HTTP %d).', (int) $status);
    }

    /** Drops anything that would name the upstream provider. */
    private static function clean($text)
    {
        $text = trim((string) $text);
        if ($text === '' || preg_match('/voice\s*telekom|voicetelekom/i', $text)) {
            return '';
        }

        return mb_substr($text, 0, 300);
    }
}
