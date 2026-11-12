<?php
defined('ABSPATH') || exit;

/** WooCommerce › Ayarlar › VOIS tab. Loaded from the woocommerce_get_settings_pages filter. */
final class Vois_WC_Settings_Page extends WC_Settings_Page
{
    public function __construct()
    {
        $this->id = 'vois';
        $this->label = 'VOIS';
        parent::__construct();
        add_action('woocommerce_admin_field_vois_api_key', [$this, 'field_api_key']);
        add_action('woocommerce_admin_field_vois_test', [$this, 'field_test']);
        add_filter('woocommerce_admin_settings_sanitize_option_vois_wc_api_key', [$this, 'sanitize_api_key'], 10, 3);
        add_filter('woocommerce_admin_settings_sanitize_option_vois_wc_default_extension', [$this, 'sanitize_extension'], 10, 3);
    }

    /** WooCommerce 5.5+ */
    protected function get_settings_for_default_section()
    {
        $vars = '{' . implode('}, {', Vois_WC_Template::PLACEHOLDERS) . '}';
        $s = [
            ['type' => 'title', 'id' => 'vois_wc_connection', 'title' => __('VOIS bağlantısı', 'vois-woocommerce'),
                'desc' => __('API anahtarını VOIS panelinizde oluşturun: Ayarlar › Açık REST API & Webhook › API & Webhook Yönetimi. Gerekli yetkiler: sms:send, sms:read ve tıkla-ara için voice:call.', 'vois-woocommerce')],
            ['type' => 'vois_api_key', 'id' => 'vois_wc_api_key', 'title' => __('API anahtarı', 'vois-woocommerce')],
            ['type' => 'vois_test', 'title' => __('Bağlantı', 'vois-woocommerce')],
            ['type' => 'text', 'id' => 'vois_wc_sender', 'title' => __('Gönderen başlığı', 'vois-woocommerce'),
                'desc' => __('SMS\'in kimden geldiği (ör. MAGAZAADI). Bağlantıyı sınayınca onaylı başlıklarınız listelenir.', 'vois-woocommerce'),
                'custom_attributes' => ['list' => 'vois-wc-senders', 'autocomplete' => 'off'], 'default' => ''],
            ['type' => 'sectionend', 'id' => 'vois_wc_connection'],

            ['type' => 'title', 'id' => 'vois_wc_customer', 'title' => __('Müşteriye sipariş SMS\'leri', 'vois-woocommerce'),
                'desc' => sprintf(__('Sipariş bu duruma geçince müşterinin fatura telefonuna SMS gider (yalnız Türkiye cep numaraları). Her durum için bir sipariş en fazla bir kez SMS alır. Kullanılabilen alanlar: %s', 'vois-woocommerce'), $vars)],
        ];
        foreach (wc_get_order_statuses() as $key => $label) {
            $slug = strpos($key, 'wc-') === 0 ? substr($key, 3) : $key;
            $s[] = ['type' => 'checkbox', 'id' => 'vois_wc_on_' . $slug, 'title' => $label,
                'desc' => __('Bu durumda müşteriye SMS gönder', 'vois-woocommerce'), 'default' => $slug === 'processing' ? 'yes' : 'no'];
            $s[] = ['type' => 'textarea', 'id' => 'vois_wc_tpl_' . $slug, 'title' => '',
                'default' => Vois_WC_Options::template($slug), 'css' => 'width:100%;max-width:640px;height:64px'];
        }
        $s[] = ['type' => 'sectionend', 'id' => 'vois_wc_customer'];

        $s[] = ['type' => 'title', 'id' => 'vois_wc_admin', 'title' => __('Mağazaya yeni sipariş SMS\'i', 'vois-woocommerce')];
        $s[] = ['type' => 'checkbox', 'id' => 'vois_wc_admin_on', 'title' => __('Yeni sipariş bildirimi', 'vois-woocommerce'),
            'desc' => __('Her yeni siparişte aşağıdaki numaralara SMS gönder', 'vois-woocommerce'), 'default' => 'no'];
        $s[] = ['type' => 'textarea', 'id' => 'vois_wc_admin_numbers', 'title' => __('Numaralar', 'vois-woocommerce'),
            'desc' => __('Virgülle ya da alt alta yazın (ör. 0532 123 45 67).', 'vois-woocommerce'), 'default' => '', 'css' => 'width:320px;height:52px'];
        $s[] = ['type' => 'textarea', 'id' => 'vois_wc_tpl_admin', 'title' => __('Metin', 'vois-woocommerce'),
            'default' => Vois_WC_Options::template('admin'), 'css' => 'width:100%;max-width:640px;height:52px'];
        $s[] = ['type' => 'sectionend', 'id' => 'vois_wc_admin'];

        $s[] = ['type' => 'title', 'id' => 'vois_wc_c2c', 'title' => __('Tıkla-ara', 'vois-woocommerce'),
            'desc' => __('Sipariş ekranında "Ara" düğmesi: önce sizin dahilinizi çaldırır, açınca müşteriyi arar. Her kullanıcı kendi dahilisini Kullanıcılar › Profil sayfasından girer.', 'vois-woocommerce')];
        $s[] = ['type' => 'checkbox', 'id' => 'vois_wc_click_to_call', 'title' => __('Tıkla-ara', 'vois-woocommerce'),
            'desc' => __('Sipariş ekranında "Ara" düğmesini göster', 'vois-woocommerce'), 'default' => 'yes'];
        $s[] = ['type' => 'text', 'id' => 'vois_wc_default_extension', 'title' => __('Varsayılan dahili', 'vois-woocommerce'),
            'desc' => __('Profilinde dahili olmayan kullanıcılar için (boş bırakılabilir).', 'vois-woocommerce'), 'default' => '', 'css' => 'width:8em'];
        $s[] = ['type' => 'sectionend', 'id' => 'vois_wc_c2c'];

        $s[] = ['type' => 'title', 'id' => 'vois_wc_advanced', 'title' => __('Gelişmiş', 'vois-woocommerce')];
        $s[] = ['type' => 'text', 'id' => 'vois_wc_tracking_meta', 'title' => __('Kargo takip alanı', 'vois-woocommerce'),
            'desc' => __('{kargo_takip} için okunacak sipariş meta anahtarı (kargo eklentinizin kullandığı, ör. _tracking_number). Boşsa {kargo_takip} boş kalır.', 'vois-woocommerce'), 'default' => ''];
        $s[] = ['type' => 'sectionend', 'id' => 'vois_wc_advanced'];

        return $s;
    }

    /** Older WooCommerce (< 5.5) entry point. */
    public function get_settings($current_section = '')
    {
        return method_exists(get_parent_class($this), 'get_settings_for_section') ? parent::get_settings($current_section) : $this->get_settings_for_default_section();
    }

    /** Masked: the saved key is never printed back into the page. */
    public function field_api_key($value)
    {
        $saved = Vois_WC_Options::api_key();
        $placeholder = $saved !== '' ? sprintf(__('Kayıtlı: %1$s…%2$s (değiştirmek için yenisini yazın)', 'vois-woocommerce'), substr($saved, 0, 10), substr($saved, -4)) : 'vois_…';
        echo '<tr valign="top"><th scope="row" class="titledesc"><label for="vois_wc_api_key">' . esc_html($value['title']) . '</label></th><td class="forminp">';
        echo '<input type="password" name="vois_wc_api_key" id="vois_wc_api_key" value="" autocomplete="new-password" spellcheck="false" placeholder="' . esc_attr($placeholder) . '" style="width:420px;max-width:100%">';
        if ($saved !== '') {
            echo '<p><label><input type="checkbox" name="vois_wc_api_key_clear" value="1"> ' . esc_html__('Kayıtlı anahtarı sil', 'vois-woocommerce') . '</label></p>';
        }
        echo '</td></tr>';
    }

    public function field_test($value)
    {
        $cached = get_transient(Vois_WC_Admin::senders_cache_key(Vois_WC_Options::api_key()));
        echo '<tr valign="top"><th scope="row" class="titledesc">' . esc_html($value['title']) . '</th><td class="forminp">';
        echo '<button type="button" class="button vois-wc-test">' . esc_html__('Bağlantıyı sına', 'vois-woocommerce') . '</button>';
        echo '<div class="vois-wc-test-result" role="status" style="margin-top:8px"></div>';
        echo '<datalist id="vois-wc-senders">';
        foreach (is_array($cached) ? $cached : [] as $title) {
            echo '<option value="' . esc_attr($title) . '"></option>';
        }
        echo '</datalist></td></tr>';
    }

    public function sanitize_api_key($value, $option, $raw)
    {
        if (! empty($_POST['vois_wc_api_key_clear'])) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verified the settings nonce
            return '';
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return Vois_WC_Options::api_key();
        }
        if (! preg_match('/^vois_\d+_[A-Za-z0-9]+$/', $raw)) {
            WC_Admin_Settings::add_error(__('API anahtarı "vois_" ile başlamalı (ör. vois_1234_…). Kayıtlı anahtar değiştirilmedi.', 'vois-woocommerce'));

            return Vois_WC_Options::api_key();
        }

        return $raw;
    }

    public function sanitize_extension($value, $option, $raw)
    {
        $raw = trim((string) $raw);

        return preg_match('/^\d{0,10}$/', $raw) ? $raw : (string) Vois_WC_Options::get('default_extension');
    }
}
