<?php
use PHPUnit\Framework\TestCase;

final class TemplateTest extends TestCase
{
    public function test_render_replaces_known_and_keeps_unknown(): void
    {
        $this->assertSame('Merhaba Ayşe, #42 {bilinmeyen}', Vois_WC_Template::render('Merhaba {musteri_adi}, #{siparis_no} {bilinmeyen}', ['musteri_adi' => 'Ayşe', 'siparis_no' => '42']));
    }

    public function test_order_vars(): void
    {
        $o = new Fake_Order(7, ['number' => 'A-1007']);
        $o->meta['_kargo_no'] = 'YK123';
        $v = Vois_WC_Template::order_vars($o, '_kargo_no');
        $this->assertSame('Ayşe', $v['musteri_adi']);
        $this->assertSame('Yılmaz', $v['musteri_soyadi']);
        $this->assertSame('A-1007', $v['siparis_no']);
        $this->assertSame('1.250,00 TL', $v['siparis_tutari']);
        $this->assertSame('Hazırlanıyor', $v['siparis_durumu']);
        $this->assertSame('12.11.2026', $v['siparis_tarihi']);
        $this->assertSame('Kapıda ödeme', $v['odeme_yontemi']);
        $this->assertSame('Test Mağaza', $v['magaza_adi']);
        $this->assertSame('YK123', $v['kargo_takip']);
    }

    public function test_foreign_currency_and_missing_tracking_key(): void
    {
        $v = Vois_WC_Template::order_vars(new Fake_Order(8, ['total' => '99.5', 'currency' => 'EUR']), '');
        $this->assertSame('99,50 EUR', $v['siparis_tutari']);
        $this->assertSame('', $v['kargo_takip']);
    }
}
