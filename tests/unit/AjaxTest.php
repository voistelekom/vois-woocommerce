<?php
use PHPUnit\Framework\TestCase;

final class AjaxTest extends TestCase
{
    private $requests;

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = ['vois_wc_api_key' => 'vois_2361_abc', 'vois_wc_sender' => 'MAGAZA'];
        $GLOBALS['wc_orders'] = [7 => new Fake_Order(7)];
        $GLOBALS['nonce_ok'] = true;
        $GLOBALS['caps'] = ['edit_shop_orders', 'manage_woocommerce'];
        $GLOBALS['wp_usermeta'] = [5 => ['vois_extension' => '101']];
        $_POST = ['order_id' => '7', 'nonce' => 'x'];
        $this->requests = [];
    }

    private function admin()
    {
        $req = &$this->requests;

        return new Vois_WC_Admin(new Vois_WC_Notifier(function ($key) use (&$req) {
            return new Vois_WC_Api($key, function ($m, $u, $a) use (&$req) {
                $req[] = [$u, json_decode($a['body'], true)];

                return ['status' => 200, 'body' => '{"data":{"pkgID":9},"err":null}'];
            });
        }));
    }

    private function run_ajax(callable $fn)
    {
        try {
            $fn();
        } catch (Json_Sent $j) {
            return $j;
        }
        $this->fail('no JSON response');
    }

    public function test_manual_sms_rejects_bad_nonce_and_missing_capability(): void
    {
        $GLOBALS['nonce_ok'] = false;
        $_POST['text'] = 'Merhaba';
        $this->assertFalse($this->run_ajax([$this->admin(), 'ajax_manual_sms'])->success);
        $GLOBALS['nonce_ok'] = true;
        $GLOBALS['caps'] = [];
        $j = $this->run_ajax([$this->admin(), 'ajax_manual_sms']);
        $this->assertSame([false, 403], [$j->success, $j->code]);
        $this->assertSame([], $this->requests);
    }

    public function test_manual_sms_sends_and_notes(): void
    {
        $_POST['text'] = 'Kargonuz yarın çıkıyor.';
        $j = $this->run_ajax([$this->admin(), 'ajax_manual_sms']);
        $this->assertTrue($j->success);
        $this->assertSame('Kargonuz yarın çıkıyor.', $this->requests[0][1]['content']);
        $this->assertStringContainsString('paket #9', $GLOBALS['wc_orders'][7]->notes[0]);
    }

    public function test_manual_sms_unknown_order_and_too_long(): void
    {
        $_POST['order_id'] = '999';
        $_POST['text'] = 'x';
        $this->assertSame(404, $this->run_ajax([$this->admin(), 'ajax_manual_sms'])->code);
        $_POST['order_id'] = '7';
        $_POST['text'] = str_repeat('a', 1001);
        $this->assertFalse($this->run_ajax([$this->admin(), 'ajax_manual_sms'])->success);
        $this->assertSame([], $this->requests);
    }

    public function test_call_requires_extension_and_respects_toggle(): void
    {
        $GLOBALS['wp_usermeta'] = [];
        $j = $this->run_ajax([$this->admin(), 'ajax_call']);
        $this->assertFalse($j->success);
        $this->assertStringContainsString('dahili', $j->data['message']);
        $GLOBALS['wp_usermeta'] = [5 => ['vois_extension' => '101']];
        $GLOBALS['wp_options']['vois_wc_click_to_call'] = 'no';
        $this->assertFalse($this->run_ajax([$this->admin(), 'ajax_call'])->success);
    }

    public function test_test_connection_needs_manage_woocommerce(): void
    {
        $GLOBALS['caps'] = ['edit_shop_orders'];
        $this->assertSame(403, $this->run_ajax([$this->admin(), 'ajax_test_connection'])->code);
    }

    public function test_api_key_sanitize_keeps_saved_on_empty_and_rejects_garbage(): void
    {
        $p = new Vois_WC_Settings_Page();
        $this->assertSame('vois_2361_abc', $p->sanitize_api_key('', null, ''));
        $this->assertSame('vois_2361_def0', $p->sanitize_api_key('', null, ' vois_2361_def0 '));
        WC_Admin_Settings::$errors = [];
        $this->assertSame('vois_2361_abc', $p->sanitize_api_key('', null, 'kullanici:sifre'));
        $this->assertCount(1, WC_Admin_Settings::$errors);
        $_POST['vois_wc_api_key_clear'] = '1';
        $this->assertSame('', $p->sanitize_api_key('', null, ''));
    }

    public function test_api_key_field_never_prints_saved_key(): void
    {
        $GLOBALS['wp_options']['vois_wc_api_key'] = 'vois_2361_0123456789abcdef0123456789abcdef01234567';
        ob_start();
        (new Vois_WC_Settings_Page())->field_api_key(['title' => 'API anahtarı']);
        $html = ob_get_clean();
        $this->assertStringNotContainsString('0123456789abcdef0123456789abcdef', $html);
        $this->assertStringContainsString('value=""', $html);
    }

    public function test_manual_sms_keeps_percent_figures(): void
    {
        $_POST['text'] = 'Size özel %20 indirim, %50\'ye varan <b>fırsat</b>';
        $this->assertTrue($this->run_ajax([$this->admin(), 'ajax_manual_sms'])->success);
        $this->assertSame("Size özel %20 indirim, %50'ye varan fırsat", $this->requests[0][1]['content']);
    }

    public function test_template_and_numbers_saved_raw_without_entities(): void
    {
        $p = new Vois_WC_Settings_Page();
        $this->assertSame("Kargo & teslimat %20 'x'", $p->sanitize_text_option('Kargo &amp; teslimat', ['id' => 'vois_wc_tpl_completed'], "  Kargo & teslimat %20 'x' <script>"));
        $this->assertSame('keep', $p->sanitize_text_option('keep', ['id' => 'woocommerce_other'], 'raw'));
    }

    public function test_bad_default_extension_reports_error(): void
    {
        WC_Admin_Settings::$errors = [];
        $p = new Vois_WC_Settings_Page();
        $this->assertSame('', $p->sanitize_extension('', null, '10a'));
        $this->assertCount(1, WC_Admin_Settings::$errors);
    }
}
