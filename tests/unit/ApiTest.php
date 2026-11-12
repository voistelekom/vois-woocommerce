<?php
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    private $calls = [];

    private function api(array $responses)
    {
        $this->calls = [];
        $calls = &$this->calls;

        return new Vois_WC_Api('vois_2361_secret', function ($method, $url, $args) use (&$responses, &$calls) {
            $calls[] = [$method, $url, $args];

            return array_shift($responses);
        });
    }

    public function test_sms_body(): void
    {
        $b = Vois_WC_Api::sms_body('905321234567', 'Siparişiniz hazır', 'MAGAZA', 'WooCommerce siparis A/1007 ğ', 'wc-7-processing');
        $this->assertSame(1, $b['type']);
        $this->assertSame(0, $b['sendingType']);
        $this->assertSame(1, $b['encoding']);
        $this->assertFalse($b['commercial']);
        $this->assertSame('WooCommerce siparis A1007', $b['title']);
        $this->assertSame(['905321234567', 'Siparişiniz hazır', 'MAGAZA', 'wc-7-processing'], [$b['number'], $b['content'], $b['sender'], $b['customID']]);
        $this->assertSame(0, Vois_WC_Api::sms_body('905321234567', 'Siparisiniz hazir', 'S', 'x', 'c')['encoding']);
        $this->assertSame(50, strlen(Vois_WC_Api::sms_body('9', 'a', 'S', 'WooCommerce siparis ' . str_repeat('9', 60), 'c')['title']));
        $this->assertSame('VOIS SMS', Vois_WC_Api::sms_body('9', 'a', 'S', 'ç', 'c')['title']);
    }

    public function test_send_sms_success_returns_pkg_id_and_sends_auth(): void
    {
        $api = $this->api([['status' => 200, 'body' => '{"data":{"pkgID":555},"err":null}']]);
        $r = $api->send_sms('905321234567', 'Merhaba', 'MAGAZA', 'WooCommerce siparis 7', 'wc-7-processing');
        $this->assertTrue($r['ok']);
        $this->assertSame(555, $r['data']['pkgID']);
        [$m, $u, $a] = $this->calls[0];
        $this->assertSame(['POST', 'https://api.vois.com.tr/v1/sms/create'], [$m, $u]);
        $this->assertSame('Bearer vois_2361_secret', $a['headers']['Authorization']);
        $this->assertSame('application/json', $a['headers']['Content-Type']);
        $this->assertGreaterThanOrEqual(60, $a['timeout']);
        $this->assertSame('905321234567', json_decode($a['body'], true)['number']);
    }

    public function test_sms_err_in_200_body_is_failure_with_turkish_message(): void
    {
        $api = $this->api([['status' => 200, 'body' => '{"data":null,"err":{"status":402,"code":"ERR_USER_CREDIT_REQUIRED","message":"x"}}']]);
        $r = $api->send_sms('905321234567', 'a', 'S', 'WooCommerce siparis 7', 'c');
        $this->assertFalse($r['ok']);
        $this->assertSame('ERR_USER_CREDIT_REQUIRED', $r['code']);
        $this->assertStringContainsString('bakiye', $r['message']);
    }

    public function test_vois_errors_mapped(): void
    {
        $api = $this->api([
            ['status' => 403, 'body' => '{"error":"sms_not_configured","message":"SMS hesabı tanımlı değil."}'],
            ['status' => 401, 'body' => '{"error":"unauthorized","message":"x"}'],
            ['status' => 409, 'body' => '{"error":"extension_unavailable","message":"101 numaralı dahiliye bağlı telefon yok."}'],
            ['error' => 'cURL error 28: timed out'],
            ['status' => 500, 'body' => '<html>'],
        ]);
        $r = $api->credit();
        $this->assertSame(['sms_not_configured', 403], [$r['code'], $r['status']]);
        $this->assertStringContainsString('SMS', $r['message']);
        $this->assertStringContainsString('anahtar', $api->me()['message']);
        $r = $api->call('101', '05321234567', 'wc-order-7');
        $this->assertSame('extension_unavailable', $r['code']);
        $this->assertStringContainsString('101', $r['message']);
        $r = $api->senders();
        $this->assertSame('network', $r['code']);
        $this->assertStringNotContainsString('cURL', $r['message']);
        $this->assertSame('bad_response', $api->me()['code']);
    }

    public function test_call_and_reads_hit_documented_paths(): void
    {
        $api = $this->api([
            ['status' => 202, 'body' => '{"data":{"call_id":"abc","status":"ringing_extension"}}'],
            ['status' => 200, 'body' => '{"data":{"credit":120.5},"err":null}'],
            ['status' => 200, 'body' => '{"data":{"list":[{"title":"MAGAZA","status":1},{"title":"ESKI","status":0},{"title":"IKINCI","status":1}]},"err":null}'],
            ['status' => 200, 'body' => '{"data":{"tenant":{"id":2361},"token":{"id":9,"scopes":["sms:send","sms:read"]}}}'],
        ]);
        $r = $api->call('101', '0532 123 45 67', 'wc-order-7');
        $this->assertSame('abc', $r['data']['call_id']);
        $this->assertSame(['POST', 'https://api.vois.com.tr/v1/voice/calls'], [$this->calls[0][0], $this->calls[0][1]]);
        $this->assertSame(['extension' => '101', 'number' => '0532 123 45 67', 'reference' => 'wc-order-7'], json_decode($this->calls[0][2]['body'], true));
        $this->assertSame(120.5, $api->credit()['data']['credit']);
        $this->assertSame(['GET', 'https://api.vois.com.tr/v1/sms/credit'], [$this->calls[1][0], $this->calls[1][1]]);
        $this->assertSame(['MAGAZA', 'IKINCI'], $api->senders()['data']);
        $this->assertSame(['POST', 'https://api.vois.com.tr/v1/sms/list-sender'], [$this->calls[2][0], $this->calls[2][1]]);
        $this->assertSame(['sms:send', 'sms:read'], $api->me()['data']['token']['scopes']);
        $this->assertSame(['GET', 'https://api.vois.com.tr/v1/me'], [$this->calls[3][0], $this->calls[3][1]]);
    }

    public function test_empty_key_fails_without_request(): void
    {
        $api = new Vois_WC_Api('', function () { throw new RuntimeException('no request expected'); });
        $r = $api->credit();
        $this->assertSame('no_key', $r['code']);
    }

    public function test_messages_never_leak_provider_name(): void
    {
        foreach (['ERR_USER_CREDIT_REQUIRED', 'unauthorized', 'sms_blocked', 'rate_limited', 'whatever'] as $c) {
            $this->assertStringNotContainsStringIgnoringCase('voice telekom', Vois_WC_Errors::message($c, 'Voice Telekom hata', 400));
        }
    }
}
