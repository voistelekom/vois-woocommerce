<?php
defined('ABSPATH') || exit;

/** Order screen box (manual SMS + click-to-call), AJAX endpoints, user profile extension field. */
final class Vois_WC_Admin
{
    const ORDER_NONCE = 'vois_wc_order';
    const SETTINGS_NONCE = 'vois_wc_settings';
    const SCOPE_LABELS = [
        'sms:send'   => 'sms:send (SMS gönderme)',
        'sms:read'   => 'sms:read (bakiye ve gönderen başlıkları)',
        'voice:call' => 'voice:call (tıkla-ara)',
    ];

    private $notifier;

    public function __construct(Vois_WC_Notifier $notifier)
    {
        $this->notifier = $notifier;
    }

    public function register()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_vois_wc_manual_sms', [$this, 'ajax_manual_sms']);
        add_action('wp_ajax_vois_wc_call', [$this, 'ajax_call']);
        add_action('wp_ajax_vois_wc_test_connection', [$this, 'ajax_test_connection']);
        add_action('show_user_profile', [$this, 'profile_field']);
        add_action('edit_user_profile', [$this, 'profile_field']);
        add_action('personal_options_update', [$this, 'save_profile_field']);
        add_action('edit_user_profile_update', [$this, 'save_profile_field']);
    }

    /* ---------- pure helpers (unit tested) ---------- */

    /**
     * Plain SMS text: no tags, valid UTF-8, trimmed. Unlike sanitize_textarea_field it keeps "%20" (percent figures).
     * Output is escaped wherever it is shown.
     */
    public static function clean_text($raw)
    {
        return trim(wp_strip_all_tags(wp_check_invalid_utf8((string) $raw)));
    }

    /** The caller's extension: user profile first, then the shop default. */
    public static function extension_for_user($user_id)
    {
        $ext = trim((string) get_user_meta((int) $user_id, 'vois_extension', true));

        return $ext !== '' ? $ext : trim((string) Vois_WC_Options::get('default_extension'));
    }

    /**
     * Runs /me, then credit and senders when the key can read SMS.
     *
     * @return array{ok: bool, lines: string[], senders: string[]}
     */
    public static function connection_report(Vois_WC_Api $api, $click_to_call)
    {
        $me = $api->me();
        if (! $me['ok']) {
            return ['ok' => false, 'lines' => [$me['message']], 'senders' => []];
        }
        $lines = [];
        $company = isset($me['data']['tenant']['company_name']) ? (string) $me['data']['tenant']['company_name'] : '';
        $lines[] = $company !== '' ? sprintf(__('Bağlantı tamam. Hesap: %s', 'vois-woocommerce'), $company) : __('Bağlantı tamam.', 'vois-woocommerce');

        $scopes = isset($me['data']['token']['scopes']) && is_array($me['data']['token']['scopes']) ? $me['data']['token']['scopes'] : [];
        $need = $click_to_call ? ['sms:send', 'sms:read', 'voice:call'] : ['sms:send', 'sms:read'];
        $missing = array_values(array_diff($need, $scopes));
        $ok = ! $missing;
        if ($missing) {
            $labels = [];
            foreach ($missing as $s) {
                $labels[] = self::SCOPE_LABELS[$s];
            }
            $lines[] = sprintf(__('Anahtarda eksik yetki: %s. Panelde (Ayarlar › API & Webhook) bu yetkilerle yeni bir anahtar oluşturun.', 'vois-woocommerce'), implode(', ', $labels));
            if (! array_intersect(['sms:send', 'sms:read'], $scopes)) {
                $lines[] = __('Panelde SMS yetkileri görünmüyorsa hesabınızda SMS hizmeti tanımlı değildir. VOIS destek ile görüşün.', 'vois-woocommerce');
            }
        }

        $senders = [];
        if (in_array('sms:read', $scopes, true)) {
            $credit = $api->credit();
            if ($credit['ok']) {
                $amount = isset($credit['data']['credit']) ? (float) $credit['data']['credit'] : 0.0;
                $lines[] = sprintf(__('SMS bakiyesi: %s', 'vois-woocommerce'), number_format($amount, 2, ',', '.'));
                $s = $api->senders();
                if ($s['ok']) {
                    $senders = $s['data'];
                    $lines[] = $senders ? sprintf(__('Onaylı gönderen başlıkları: %s', 'vois-woocommerce'), implode(', ', $senders)) : __('Hesapta onaylı gönderen başlığı yok. VOIS destek ile görüşün.', 'vois-woocommerce');
                    $ok = $ok && (bool) $senders;
                } else {
                    $lines[] = $s['message'];
                }
            } else {
                $ok = false;
                $lines[] = $credit['message'];
            }
        }

        return ['ok' => $ok, 'lines' => $lines, 'senders' => $senders];
    }

    /* ---------- order screen ---------- */

    public function add_meta_box()
    {
        add_meta_box('vois-wc-box', 'VOIS', [$this, 'render_box'], ['shop_order', 'woocommerce_page_wc-orders'], 'side', 'high');
    }

    /** @param WP_Post|WC_Order $object */
    public function render_box($object)
    {
        $order = $object instanceof WC_Order ? $object : wc_get_order($object->ID);
        if (! $order) {
            return;
        }
        $phone = (string) $order->get_billing_phone();
        $c2c = Vois_WC_Options::get('click_to_call', 'yes') === 'yes';
        $ext = self::extension_for_user(get_current_user_id());
        echo '<div class="vois-wc-box" data-order="' . esc_attr($order->get_id()) . '" data-nonce="' . esc_attr(wp_create_nonce(self::ORDER_NONCE)) . '">';
        echo '<p><strong>' . esc_html__('Müşteri telefonu', 'vois-woocommerce') . ':</strong> ' . ($phone !== '' ? esc_html($phone) : '—') . '</p>';
        if ($c2c && $phone !== '') {
            if ($ext !== '') {
                echo '<p><button type="button" class="button vois-wc-call">' . esc_html(sprintf(__('Ara (dahili %s)', 'vois-woocommerce'), $ext)) . '</button></p>';
            } else {
                echo '<p class="description">' . esc_html__('Aramak için profilinize VOIS dahili numaranızı ekleyin (Kullanıcılar › Profil).', 'vois-woocommerce') . '</p>';
            }
        }
        echo '<p><textarea class="vois-wc-text" rows="4" style="width:100%" maxlength="1000" placeholder="' . esc_attr__('Müşteriye SMS…', 'vois-woocommerce') . '"></textarea></p>';
        echo '<p><button type="button" class="button button-primary vois-wc-sms">' . esc_html__('SMS gönder', 'vois-woocommerce') . '</button></p>';
        echo '<p class="vois-wc-result" role="status"></p>';
        echo '</div>';
    }

    public function enqueue($hook)
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $id = $screen ? $screen->id : '';
        $is_settings = $id === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'vois'; // phpcs:ignore WordPress.Security.NonceVerification
        if (! in_array($id, ['shop_order', 'woocommerce_page_wc-orders'], true) && ! $is_settings) {
            return;
        }
        wp_enqueue_script('vois-wc-admin', plugins_url('assets/admin.js', VOIS_WC_FILE), [], VOIS_WC_VERSION, true);
        wp_localize_script('vois-wc-admin', 'voisWc', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'settingsNonce' => wp_create_nonce(self::SETTINGS_NONCE),
            'sending'       => __('Gönderiliyor…', 'vois-woocommerce'),
            'calling'       => __('Aranıyor…', 'vois-woocommerce'),
            'testing'       => __('Sınanıyor…', 'vois-woocommerce'),
            'failed'        => __('Yanıt alınamadı. SMS yine de gönderilmiş olabilir: tekrar denemeden önce sayfayı yenileyip sipariş notlarına bakın.', 'vois-woocommerce'),
        ]);
    }

    /* ---------- AJAX ---------- */

    private function order_from_request()
    {
        check_ajax_referer(self::ORDER_NONCE, 'nonce');
        if (! current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => __('Bu işlem için yetkiniz yok.', 'vois-woocommerce')], 403);
        }
        $order = wc_get_order(isset($_POST['order_id']) ? absint($_POST['order_id']) : 0);
        if (! $order) {
            wp_send_json_error(['message' => __('Sipariş bulunamadı.', 'vois-woocommerce')], 404);
        }

        return $order;
    }

    public function ajax_manual_sms()
    {
        $order = $this->order_from_request();
        $text = isset($_POST['text']) ? self::clean_text(wp_unslash($_POST['text'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned by clean_text
        if (mb_strlen($text) > 1000) {
            wp_send_json_error(['message' => __('SMS metni en fazla 1000 karakter olabilir.', 'vois-woocommerce')]);
        }
        $r = $this->notifier->send_manual($order, $text);
        if ($r['ok']) {
            wp_send_json_success(['message' => __('SMS gönderildi. Sipariş notlarına eklendi.', 'vois-woocommerce')]);
        }
        wp_send_json_error(['message' => $r['message']]);
    }

    public function ajax_call()
    {
        $order = $this->order_from_request();
        if (Vois_WC_Options::get('click_to_call', 'yes') !== 'yes') {
            wp_send_json_error(['message' => __('Tıkla-ara ayarlardan kapatılmış.', 'vois-woocommerce')]);
        }
        $ext = self::extension_for_user(get_current_user_id());
        if ($ext === '') {
            wp_send_json_error(['message' => __('Profilinizde VOIS dahili numarası yok.', 'vois-woocommerce')]);
        }
        $phone = trim((string) $order->get_billing_phone());
        if ($phone === '') {
            wp_send_json_error(['message' => __('Siparişte telefon numarası yok.', 'vois-woocommerce')]);
        }
        $r = (new Vois_WC_Api(Vois_WC_Options::api_key()))->call($ext, $phone, 'wc-order-' . $order->get_id());
        if (! $r['ok']) {
            wp_send_json_error(['message' => $r['message']]);
        }
        $user = wp_get_current_user();
        $order->add_order_note(sprintf(__('VOIS: %1$s (dahili %2$s) müşteriyi aradı.', 'vois-woocommerce'), $user->display_name, $ext));
        wp_send_json_success(['message' => sprintf(__('Dahili %s çalıyor. Açınca müşteri aranacak.', 'vois-woocommerce'), $ext)]);
    }

    public function ajax_test_connection()
    {
        check_ajax_referer(self::SETTINGS_NONCE, 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Bu işlem için yetkiniz yok.', 'vois-woocommerce')], 403);
        }
        $typed = isset($_POST['key']) ? trim(sanitize_text_field(wp_unslash($_POST['key']))) : '';
        $key = $typed !== '' ? $typed : Vois_WC_Options::api_key();
        $c2c = isset($_POST['c2c']) ? $_POST['c2c'] === 'yes' : Vois_WC_Options::get('click_to_call', 'yes') === 'yes'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $report = self::connection_report(new Vois_WC_Api($key), $c2c);
        if ($report['senders']) {
            set_transient(self::senders_cache_key($key), $report['senders'], 10 * MINUTE_IN_SECONDS);
        }
        wp_send_json_success($report);
    }

    public static function senders_cache_key($key)
    {
        return 'vois_wc_senders_' . substr(md5((string) $key), 0, 12);
    }

    /* ---------- user profile ---------- */

    public function profile_field($user)
    {
        if (! user_can($user, 'edit_shop_orders')) {
            return;
        }
        $v = (string) get_user_meta($user->ID, 'vois_extension', true);
        echo '<h2>VOIS</h2><table class="form-table" role="presentation"><tr><th><label for="vois_extension">' . esc_html__('VOIS dahili numarası', 'vois-woocommerce') . '</label></th><td>';
        echo '<input type="text" name="vois_extension" id="vois_extension" value="' . esc_attr($v) . '" inputmode="numeric" pattern="[0-9]{1,10}" class="regular-text" style="width:8em">';
        echo '<p class="description">' . esc_html__('Siparişlerdeki "Ara" düğmesi önce bu dahiliyi çaldırır, siz açınca müşteriyi arar.', 'vois-woocommerce') . '</p></td></tr></table>';
    }

    public function save_profile_field($user_id)
    {
        if (! current_user_can('edit_user', $user_id) || ! isset($_POST['vois_extension'])) { // phpcs:ignore WordPress.Security.NonceVerification -- core verified update-user_ nonce
            return;
        }
        $v = trim(sanitize_text_field(wp_unslash($_POST['vois_extension']))); // phpcs:ignore WordPress.Security.NonceVerification
        if ($v === '') {
            delete_user_meta($user_id, 'vois_extension');
        } elseif (preg_match('/^\d{1,10}$/', $v)) {
            update_user_meta($user_id, 'vois_extension', $v);
        }
    }
}
