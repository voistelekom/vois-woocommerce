<?php
defined('ABSPATH') || exit;

/**
 * Order SMS: status changes → customer, new order → shop numbers.
 * Hooks only queue an Action Scheduler job; the send runs in the background (the SMS API can take up to a minute).
 */
final class Vois_WC_Notifier
{
    const ACTION = 'vois_wc_send_sms';
    const SENT_META = '_vois_wc_sent';

    private $api_factory;

    /** @param callable|null $api_factory fn(string $key): Vois_WC_Api */
    public function __construct($api_factory = null)
    {
        $this->api_factory = $api_factory ?: function ($key) {
            return new Vois_WC_Api($key);
        };
    }

    public function register()
    {
        add_action('woocommerce_order_status_changed', [$this, 'on_status_changed'], 10, 3);
        add_action('woocommerce_checkout_order_processed', [$this, 'on_new_order'], 10, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'on_store_api_order'], 10, 1);
        add_action(self::ACTION, [$this, 'handle'], 10, 3);
    }

    public function on_status_changed($order_id, $from, $to)
    {
        if (Vois_WC_Options::api_key() !== '' && Vois_WC_Options::status_enabled($to)) {
            $this->enqueue((int) $order_id, 'customer', (string) $to);
        }
    }

    public function on_new_order($order_id)
    {
        if (Vois_WC_Options::api_key() !== '' && Vois_WC_Options::get('admin_on', 'no') === 'yes') {
            $this->enqueue((int) $order_id, 'admin', 'new');
        }
    }

    /** @param WC_Order $order */
    public function on_store_api_order($order)
    {
        if (is_object($order) && method_exists($order, 'get_id')) {
            $this->on_new_order($order->get_id());
        }
    }

    private function enqueue($order_id, $kind, $status)
    {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::ACTION, [$order_id, $kind, $status], 'vois');
        } else {
            $this->handle($order_id, $kind, $status);
        }
    }

    /** Action Scheduler job. */
    public function handle($order_id, $kind, $status)
    {
        $order = wc_get_order((int) $order_id);
        if (! $order) {
            return;
        }
        $tag = $kind === 'admin' ? 'admin:new' : 'customer:' . $status;
        $sent = $order->get_meta(self::SENT_META, true);
        $sent = is_array($sent) ? $sent : [];
        if (in_array($tag, $sent, true)) {
            return;
        }

        $text = trim(Vois_WC_Template::render(Vois_WC_Options::template($kind === 'admin' ? 'admin' : $status), Vois_WC_Template::order_vars($order, (string) Vois_WC_Options::get('tracking_meta'))));
        if ($text === '') {
            return;
        }

        if ($kind === 'admin') {
            $numbers = Vois_WC_Phone::parse_list(Vois_WC_Options::get('admin_numbers'));
            if (! $numbers) {
                $order->add_order_note(__('VOIS: yeni sipariş SMS\'i için geçerli bir yönetici numarası yok.', 'vois-woocommerce'));

                return;
            }
            $ok = [];
            $errors = [];
            foreach ($numbers as $n) {
                $r = $this->send($order, $n, $text, 'wc-' . $order->get_id() . '-admin-' . $n);
                if ($r['ok']) {
                    $ok[] = self::mask($n);
                } else {
                    $errors[] = self::mask($n) . ': ' . $r['message'];
                }
            }
            if ($ok) {
                $this->mark_sent($order, $sent, $tag);
            }
            $note = $ok ? sprintf(__('VOIS: yeni sipariş SMS\'i mağazaya gönderildi (%s).', 'vois-woocommerce'), implode(', ', $ok)) : '';
            if ($errors) {
                $note .= ($note ? ' ' : '') . sprintf(__('VOIS: gönderilemedi — %s', 'vois-woocommerce'), implode('; ', $errors));
            }
            $order->add_order_note($note);

            return;
        }

        $number = Vois_WC_Phone::normalize($order->get_billing_phone());
        if ($number === null) {
            $order->add_order_note(__('VOIS SMS gönderilmedi: müşterinin telefonu Türkiye cep numarası değil.', 'vois-woocommerce'));

            return;
        }
        $r = $this->send($order, $number, $text, 'wc-' . $order->get_id() . '-' . $status);
        if ($r['ok']) {
            $this->mark_sent($order, $sent, $tag);
        }
        $this->note_result($order, $number, $text, $r);
    }

    /** Manual SMS from the order screen: no dedupe. */
    public function send_manual($order, $text)
    {
        $text = trim((string) $text);
        $number = Vois_WC_Phone::normalize($order->get_billing_phone());
        if ($number === null) {
            return ['ok' => false, 'code' => 'bad_number', 'message' => __('Müşterinin telefonu Türkiye cep numarası değil.', 'vois-woocommerce')];
        }
        if ($text === '') {
            return ['ok' => false, 'code' => 'empty', 'message' => __('SMS metni boş.', 'vois-woocommerce')];
        }
        $r = $this->send($order, $number, $text, 'wc-' . $order->get_id() . '-manual-' . time());
        $this->note_result($order, $number, $text, $r);

        return $r;
    }

    private function send($order, $number, $text, $custom_id)
    {
        $sender = (string) Vois_WC_Options::get('sender');
        if ($sender === '') {
            return ['ok' => false, 'code' => 'no_sender', 'message' => Vois_WC_Errors::message('no_sender')];
        }
        $api = call_user_func($this->api_factory, Vois_WC_Options::api_key());
        $r = $api->send_sms($number, $text, $sender, 'WooCommerce siparis ' . $order->get_order_number(), $custom_id);
        if (! $r['ok'] && function_exists('wc_get_logger')) {
            wc_get_logger()->log('warning', sprintf('SMS failed for order %d: %s (%s)', $order->get_id(), $r['code'], $r['message']), ['source' => 'vois']);
        }

        return $r;
    }

    private function note_result($order, $number, $text, array $r)
    {
        if ($r['ok']) {
            $pkg = isset($r['data']['pkgID']) ? (string) $r['data']['pkgID'] : '-';
            $order->add_order_note(sprintf(__('VOIS SMS gönderildi → %1$s (paket #%2$s): %3$s', 'vois-woocommerce'), self::mask($number), $pkg, $text));
        } else {
            $order->add_order_note(sprintf(__('VOIS SMS gönderilemedi → %1$s: %2$s', 'vois-woocommerce'), self::mask($number), $r['message']));
        }
    }

    private function mark_sent($order, array $sent, $tag)
    {
        $sent[] = $tag;
        $order->update_meta_data(self::SENT_META, array_values(array_unique($sent)));
        $order->save();
    }

    /** 905321234567 → 0532***4567 */
    public static function mask($n)
    {
        return '0' . substr($n, 2, 3) . '***' . substr($n, -4);
    }
}
