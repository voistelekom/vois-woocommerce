=== VOIS for WooCommerce ===
Contributors: voistelekom
Tags: sms, woocommerce, sipariş, santral, tıkla ara
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 11.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce siparişleri için VOIS SMS bildirimleri ve sipariş ekranından santral ile tıkla-ara.

== Description ==

* Sipariş durumu değişince müşteriye SMS (her durum için ayrı metin, aç/kapa).
* Yeni siparişte mağaza numaralarına SMS.
* Sipariş ekranından müşteriye elle SMS.
* Tıkla-ara: "Ara" düğmesi önce sizin VOIS dahilinizi çaldırır, açınca müşteriyi arar.
* SMS'ler arka planda gönderilir; ödeme sayfası beklemez. Her gönderim sipariş notlarına düşer.

Bir VOIS hesabı ve VOIS panelinden oluşturulmuş bir API anahtarı gerekir (yetkiler: sms:send, sms:read, tıkla-ara için voice:call).

Kurulum kılavuzu: https://docs.vois.com.tr/woocommerce/
Destek: destek@vois.com.tr · 0850 340 6 340

== Installation ==

1. Eklentiler › Yeni ekle › Eklenti yükle ile vois-woocommerce.zip dosyasını yükleyip etkinleştirin.
2. WooCommerce › Ayarlar › VOIS sekmesinde API anahtarınızı girin ve "Bağlantıyı sına"ya basın.
3. Gönderen başlığını seçin, SMS gönderilecek sipariş durumlarını işaretleyip kaydedin.
4. Tıkla-ara için her kullanıcı Kullanıcılar › Profil sayfasına kendi VOIS dahili numarasını yazar.

== Changelog ==

= 1.0.0 =
* İlk sürüm.
