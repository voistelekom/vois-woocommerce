<?php
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    private function api(array $responses)
    {
        return new Vois_WC_Api('vois_1_k', function () use (&$responses) { return array_shift($responses); });
    }

    public function test_report_all_good(): void
    {
        $r = Vois_WC_Admin::connection_report($this->api([
            ['status' => 200, 'body' => '{"data":{"tenant":{"id":1,"company_name":"Demo A.Ş."},"token":{"id":9,"scopes":["sms:send","sms:read","voice:call"]}}}'],
            ['status' => 200, 'body' => '{"data":{"credit":1250.5},"err":null}'],
            ['status' => 200, 'body' => '{"data":{"list":[{"title":"MAGAZA","status":1}]},"err":null}'],
        ]), true);
        $this->assertTrue($r['ok']);
        $this->assertSame(['MAGAZA'], $r['senders']);
        $text = implode("\n", $r['lines']);
        $this->assertStringContainsString('Demo A.Ş.', $text);
        $this->assertStringContainsString('1.250,5', $text);
        $this->assertStringContainsString('MAGAZA', $text);
    }

    public function test_report_names_missing_scopes_and_skips_sms_reads(): void
    {
        $r = Vois_WC_Admin::connection_report($this->api([
            ['status' => 200, 'body' => '{"data":{"tenant":{"id":1,"company_name":"X"},"token":{"id":9,"scopes":["voice:read"]}}}'],
        ]), true);
        $this->assertFalse($r['ok']);
        $text = implode("\n", $r['lines']);
        foreach (['sms:send', 'sms:read', 'voice:call'] as $s) {
            $this->assertStringContainsString($s, $text);
        }
        $this->assertStringContainsString('SMS hizmeti', $text, 'tenants without SMS cannot pick SMS scopes: say so');
    }

    public function test_report_voice_scope_not_required_when_click_to_call_off(): void
    {
        $r = Vois_WC_Admin::connection_report($this->api([
            ['status' => 200, 'body' => '{"data":{"tenant":{"id":1},"token":{"id":9,"scopes":["sms:send","sms:read"]}}}'],
            ['status' => 200, 'body' => '{"data":{"credit":3},"err":null}'],
            ['status' => 200, 'body' => '{"data":{"list":[]},"err":null}'],
        ]), false);
        $this->assertFalse($r['ok'], 'no approved sender means SMS cannot go out');
        $this->assertStringNotContainsString('voice:call', implode("\n", $r['lines']));
        $this->assertStringContainsString('onaylı gönderen başlığı yok', implode("\n", $r['lines']));
    }

    public function test_report_bad_key_and_sms_not_configured(): void
    {
        $r = Vois_WC_Admin::connection_report($this->api([['status' => 401, 'body' => '{"error":"unauthorized","message":"x"}']]), true);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('geçersiz', $r['lines'][0]);
        $r = Vois_WC_Admin::connection_report($this->api([
            ['status' => 200, 'body' => '{"data":{"tenant":{"id":1},"token":{"id":9,"scopes":["sms:send","sms:read"]}}}'],
            ['status' => 403, 'body' => '{"error":"sms_not_configured","message":"x"}'],
        ]), false);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('SMS hizmeti tanımlı değil', implode("\n", $r['lines']));
    }

    public function test_extension_from_profile_then_default(): void
    {
        $GLOBALS['wp_options']['vois_wc_default_extension'] = '100';
        $GLOBALS['wp_usermeta'][5]['vois_extension'] = '101';
        $this->assertSame('101', Vois_WC_Admin::extension_for_user(5));
        $this->assertSame('100', Vois_WC_Admin::extension_for_user(6));
        $GLOBALS['wp_options']['vois_wc_default_extension'] = '';
        $this->assertSame('', Vois_WC_Admin::extension_for_user(6));
    }
}
