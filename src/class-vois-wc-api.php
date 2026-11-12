<?php
defined('ABSPATH') || exit;

/**
 * VOIS API client on the WordPress HTTP API. Every method returns
 * ['ok' => bool, 'data' => mixed, 'code' => ?string, 'message' => string, 'status' => int].
 */
final class Vois_WC_Api
{
    const BASE = 'https://api.vois.com.tr/v1/';
    const VERSION = '1.0.0';
    const SMS_SEND_TIMEOUT = 65;  // VOIS waits up to 55 s for the SMS service
    const TIMEOUT = 20;

    private $key;
    private $transport;

    /** @param callable|null $transport fn(string $method, string $url, array $args): array{status?:int, body?:string, error?:string} */
    public function __construct($key, $transport = null)
    {
        $this->key = trim((string) $key);
        $this->transport = $transport ?: [self::class, 'wp_transport'];
    }

    public function me()
    {
        return $this->request('GET', 'me');
    }

    /** data = ['credit' => float, …] */
    public function credit()
    {
        return $this->request('GET', 'sms/credit');
    }

    /** data = approved sender titles (list of strings). */
    public function senders()
    {
        $r = $this->request('POST', 'sms/list-sender', ['pageIndex' => 0, 'pageSize' => 100]);
        if ($r['ok']) {
            $list = [];
            $items = isset($r['data']['list']) && is_array($r['data']['list']) ? $r['data']['list'] : [];
            foreach ($items as $item) {
                if (is_array($item) && isset($item['title']) && (! isset($item['status']) || (int) $item['status'] === 1)) {
                    $list[] = (string) $item['title'];
                }
            }
            $r['data'] = $list;
        }

        return $r;
    }

    /** data = ['pkgID' => int] */
    public function send_sms($number, $content, $sender, $title, $custom_id)
    {
        return $this->request('POST', 'sms/create', self::sms_body($number, $content, $sender, $title, $custom_id), self::SMS_SEND_TIMEOUT);
    }

    /** data = ['call_id' => string, 'status' => string] */
    public function call($extension, $number, $reference)
    {
        return $this->request('POST', 'voice/calls', ['extension' => (string) $extension, 'number' => (string) $number, 'reference' => (string) $reference]);
    }

    public static function sms_body($number, $content, $sender, $title, $custom_id)
    {
        $title = trim(substr(preg_replace('/[^A-Za-z0-9 ._-]/', '', (string) $title), 0, 50));
        if (strlen($title) < 5) {
            $title = 'VOIS SMS';
        }

        return [
            'type'        => 1,
            'sendingType' => 0,
            'title'       => $title,
            'sender'      => (string) $sender,
            'number'      => (string) $number,
            'content'     => (string) $content,
            'encoding'    => preg_match('/[ğüşıöçĞÜŞİÖÇ]/u', (string) $content) ? 1 : 0,
            'commercial'  => false,
            'customID'    => substr((string) $custom_id, 0, 100),
        ];
    }

    private function request($method, $path, $body = null, $timeout = self::TIMEOUT)
    {
        if ($this->key === '') {
            return self::fail('no_key', null, 0);
        }
        $args = [
            'method'  => $method,
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->key,
                'Accept'        => 'application/json',
                'User-Agent'    => 'vois-woocommerce/' . self::VERSION,
            ],
        ];
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $res = call_user_func($this->transport, $method, self::BASE . $path, $args);
        if (! is_array($res) || isset($res['error'])) {
            return self::fail('network', null, 0);
        }
        $status = (int) ($res['status'] ?? 0);
        $json = json_decode((string) ($res['body'] ?? ''), true);
        if (! is_array($json)) {
            return self::fail('bad_response', null, $status);
        }
        if (isset($json['error']) && is_string($json['error'])) {
            return self::fail($json['error'], $json['message'] ?? null, $status);
        }
        if (isset($json['err']) && is_array($json['err'])) {
            return self::fail((string) ($json['err']['code'] ?? 'sms_error'), $json['err']['message'] ?? null, $status);
        }
        if ($status < 200 || $status >= 300 || ! array_key_exists('data', $json)) {
            return self::fail('bad_response', null, $status);
        }

        return ['ok' => true, 'data' => $json['data'], 'code' => null, 'message' => '', 'status' => $status];
    }

    private static function fail($code, $server_message, $status)
    {
        return ['ok' => false, 'data' => null, 'code' => $code, 'message' => Vois_WC_Errors::message($code, $server_message, $status), 'status' => (int) $status];
    }

    /** Default transport: wp_remote_request. */
    public static function wp_transport($method, $url, array $args)
    {
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->warning('VOIS request failed: ' . $res->get_error_message(), ['source' => 'vois']);
            }

            return ['error' => $res->get_error_message()];
        }

        return ['status' => (int) wp_remote_retrieve_response_code($res), 'body' => (string) wp_remote_retrieve_body($res)];
    }
}
