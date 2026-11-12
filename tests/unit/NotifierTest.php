<?php
use PHPUnit\Framework\TestCase;

final class NotifierTest extends TestCase
{
    private $sent;
    private $reply;

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = ['vois_wc_api_key' => 'vois_2361_k', 'vois_wc_sender' => 'MAGAZA'];
        $GLOBALS['as_jobs'] = [];
        $GLOBALS['wc_orders'] = [7 => new Fake_Order(7)];
        $this->sent = [];
        $this->reply = ['status' => 200, 'body' => '{"data":{"pkgID":555},"err":null}'];
    }

    private function notifier()
    {
        $sent = &$this->sent;
        $reply = &$this->reply;

        return new Vois_WC_Notifier(function ($key) use (&$sent, &$reply) {
            return new Vois_WC_Api($key, function ($m, $u, $a) use (&$sent, &$reply) {
                $sent[] = json_decode($a['body'], true);

                return $reply;
            });
        });
    }

    public function test_enabled_status_is_queued_in_background(): void
    {
        $n = $this->notifier();
        $n->on_status_changed(7, 'pending', 'processing');
        $this->assertSame([['vois_wc_send_sms', [7, 'customer', 'processing'], 'vois']], $GLOBALS['as_jobs']);
        $this->assertSame([], $this->sent, 'nothing sent during checkout request');
    }

    public function test_disabled_status_or_missing_key_not_queued(): void
    {
        $n = $this->notifier();
        $n->on_status_changed(7, 'processing', 'completed');
        $GLOBALS['wp_options']['vois_wc_api_key'] = '';
        $n->on_status_changed(7, 'pending', 'processing');
        $this->assertSame([], $GLOBALS['as_jobs']);
    }

    public function test_handle_sends_rendered_template_and_notes(): void
    {
        $GLOBALS['wp_options']['vois_wc_tpl_processing'] = 'Sayın {musteri_adi}, {siparis_no} hazırlanıyor.';
        $this->notifier()->handle(7, 'customer', 'processing');
        $this->assertCount(1, $this->sent);
        $b = $this->sent[0];
        $this->assertSame(['905321234567', 'Sayın Ayşe, 7 hazırlanıyor.', 'MAGAZA', 'WooCommerce siparis 7', 'wc-7-processing'], [$b['number'], $b['content'], $b['sender'], $b['title'], $b['customID']]);
        $o = $GLOBALS['wc_orders'][7];
        $this->assertStringContainsString('paket #555', $o->notes[0]);
        $this->assertStringContainsString('0532***4567', $o->notes[0]);
        $this->assertStringNotContainsString('905321234567', $o->notes[0]);
        $this->assertSame(['customer:processing'], $o->meta['_vois_wc_sent']);
        $this->assertGreaterThan(0, $o->saved);
    }

    public function test_default_template_used_when_option_missing(): void
    {
        $this->notifier()->handle(7, 'customer', 'processing');
        $this->assertStringContainsString('Ayşe', $this->sent[0]['content']);
        $this->assertStringNotContainsString('{', $this->sent[0]['content']);
    }

    public function test_same_status_not_sent_twice(): void
    {
        $n = $this->notifier();
        $n->handle(7, 'customer', 'processing');
        $n->handle(7, 'customer', 'processing');
        $this->assertCount(1, $this->sent);
    }

    public function test_invalid_phone_notes_and_skips(): void
    {
        $GLOBALS['wc_orders'][7] = new Fake_Order(7, ['phone' => '0212 111 22 33']);
        $this->notifier()->handle(7, 'customer', 'processing');
        $this->assertSame([], $this->sent);
        $this->assertStringContainsString('Türkiye cep', $GLOBALS['wc_orders'][7]->notes[0]);
    }

    public function test_api_failure_notes_and_does_not_mark_sent(): void
    {
        $this->reply = ['status' => 200, 'body' => '{"data":null,"err":{"code":"ERR_USER_CREDIT_REQUIRED"}}'];
        $this->notifier()->handle(7, 'customer', 'processing');
        $o = $GLOBALS['wc_orders'][7];
        $this->assertStringContainsString('gönderilemedi', $o->notes[0]);
        $this->assertStringContainsString('bakiye', $o->notes[0]);
        $this->assertArrayNotHasKey('_vois_wc_sent', $o->meta);
    }

    public function test_missing_sender_notes_without_request(): void
    {
        $GLOBALS['wp_options']['vois_wc_sender'] = '';
        $this->notifier()->handle(7, 'customer', 'processing');
        $this->assertSame([], $this->sent);
        $this->assertStringContainsString('gönderen başlığı', $GLOBALS['wc_orders'][7]->notes[0]);
    }

    public function test_admin_new_order_goes_to_each_number_once(): void
    {
        $GLOBALS['wp_options'] += ['vois_wc_admin_on' => 'yes', 'vois_wc_admin_numbers' => "05441112233, 0555 999 88 77\n0212 000", 'vois_wc_tpl_admin' => 'Yeni sipariş {siparis_no}'];
        $n = $this->notifier();
        $n->on_new_order(7);
        $this->assertSame([['vois_wc_send_sms', [7, 'admin', 'new'], 'vois']], $GLOBALS['as_jobs']);
        $n->handle(7, 'admin', 'new');
        $n->handle(7, 'admin', 'new');
        $this->assertSame(['905441112233', '905559998877'], array_column($this->sent, 'number'));
        $this->assertSame('wc-7-admin-905441112233', $this->sent[0]['customID']);
        $this->assertCount(1, $GLOBALS['wc_orders'][7]->notes);
    }

    public function test_admin_disabled_not_queued_and_unknown_order_ignored(): void
    {
        $n = $this->notifier();
        $n->on_new_order(7);
        $this->assertSame([], $GLOBALS['as_jobs']);
        $n->handle(999, 'customer', 'processing');
        $this->assertSame([], $this->sent);
    }

    public function test_blank_template_sends_nothing(): void
    {
        $GLOBALS['wp_options']['vois_wc_tpl_processing'] = '   ';
        $this->notifier()->handle(7, 'customer', 'processing');
        $this->assertSame([], $this->sent);
    }

    public function test_manual_send_ignores_dedupe_and_notes(): void
    {
        $n = $this->notifier();
        $n->handle(7, 'customer', 'processing');
        $r = $n->send_manual($GLOBALS['wc_orders'][7], 'Kargonuz yarın çıkıyor.');
        $this->assertTrue($r['ok']);
        $this->assertCount(2, $this->sent);
        $this->assertStringStartsWith('wc-7-manual-', $this->sent[1]['customID']);
        $this->assertSame('Kargonuz yarın çıkıyor.', $this->sent[1]['content']);
    }
}
