<?php
/* E2E runner: open http://127.0.0.1:9400/vois-e2e.php in the Playground server. Uses the mock in mock-vois.php. */
define('DOING_AJAX', true);
ini_set('display_errors', '1');
error_reporting(E_ALL);
register_shutdown_function(function () { $e = error_get_last(); if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) { echo "FATAL: {$e['message']} @ {$e['file']}:{$e['line']}\n"; } });
require __DIR__ . '/wp-load.php';
header('Content-Type: text/plain; charset=utf-8');
if (isset($_GET['hpos'])) { // switch order storage, then run the suite in the next request
    foreach (wc_get_orders(['limit' => -1, 'status' => 'any']) as $o) { $o->delete(true); } // throwaway playground orders
    wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class)->create_database_tables();
    update_option('woocommerce_custom_orders_table_created', 'yes');
    update_option('woocommerce_custom_orders_table_enabled', $_GET['hpos'] === 'on' ? 'yes' : 'no');
    update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
    exit("HPOS set {$_GET['hpos']}\n");
}

final class E2E_Die extends Exception {}
add_filter('wp_die_ajax_handler', function () { return function ($m = '') { throw new E2E_Die((string) $m); }; });

$fails = 0; $oks = 0;
function ok($c, $name) { global $fails, $oks; if ($c) { $oks++; echo "  ok   $name\n"; } else { $fails++; echo "  FAIL $name\n"; } }
function reqs() { return get_option('vois_e2e_requests', []); }
function reset_reqs() { update_option('vois_e2e_requests', [], false); }
function run_jobs() {
    $ids = as_get_scheduled_actions(['hook' => 'vois_wc_send_sms', 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => -1], 'ids');
    foreach ($ids as $id) { ActionScheduler::runner()->process_action($id, 'e2e'); }
    return count($ids);
}
function ajax(callable $fn, array $post) {
    $_POST = $_REQUEST = $post;
    ob_start();
    try { $fn(); } catch (E2E_Die $e) { }
    return json_decode(ob_get_clean(), true);
}
function notes($order_id) { return array_map(function ($n) { return $n->content; }, wc_get_order_notes(['order_id' => $order_id])); }

echo 'PHP ', PHP_VERSION, ' · WP ', get_bloginfo('version'), ' · WC ', WC_VERSION, ' · HPOS ', (get_option('woocommerce_custom_orders_table_enabled') === 'yes' ? 'on' : 'off'), "\n";
wp_set_current_user(1);
delete_user_meta(1, 'vois_extension'); // state from an earlier run
update_option('vois_e2e_mode', 'ok');
ok(is_plugin_active('vois-woocommerce/vois-woocommerce.php') && class_exists('Vois_WC_Notifier'), 'plugin active, classes loaded');

// ---- settings page: render + save through WooCommerce itself ----
require_once WC()->plugin_path() . '/includes/admin/class-wc-admin-settings.php';
require_once WC()->plugin_path() . '/includes/admin/settings/class-wc-settings-page.php';
$pages = apply_filters('woocommerce_get_settings_pages', []);
$page = null;
foreach ($pages as $p) { if ($p->get_id() === 'vois') { $page = $p; } }
ok($page instanceof Vois_WC_Settings_Page, 'VOIS tab registered');
$settings = $page->get_settings();
$ids = array_filter(array_column($settings, 'id'));
ok(in_array('vois_wc_on_processing', $ids, true) && in_array('vois_wc_tpl_completed', $ids, true) && ! in_array('vois_wc_on_checkout-draft', $ids, true), 'per-status fields present, checkout-draft skipped');

$key = 'vois_2361_' . str_repeat('ab12', 10);
$post = ['vois_wc_api_key' => $key, 'vois_wc_sender' => 'MAGAZA', 'vois_wc_on_processing' => '1', 'vois_wc_on_completed' => '1',
    'vois_wc_tpl_processing' => 'Sayın {musteri_adi}, {siparis_no} hazırlanıyor & %20 indirim. {magaza_adi}',
    'vois_wc_admin_on' => '1', 'vois_wc_admin_numbers' => "0544 111 22 33\n0212 000 00 00", 'vois_wc_click_to_call' => '1', 'vois_wc_default_extension' => '100'];
foreach ($settings as $s) { if (! empty($s['id']) && strpos($s['id'], 'vois_wc_tpl_') === 0 && ! isset($post[$s['id']])) { $post[$s['id']] = $s['default']; } }
WC_Admin_Settings::save_fields($settings, $post);
ok(get_option('vois_wc_api_key') === $key, 'key saved');
ok(get_option('vois_wc_tpl_processing') === 'Sayın {musteri_adi}, {siparis_no} hazırlanıyor & %20 indirim. {magaza_adi}', 'template saved raw (& and %20 kept)');
ok(get_option('vois_wc_on_processing') === 'yes' && get_option('vois_wc_on_failed') === 'no', 'checkboxes yes/no');

$post['vois_wc_api_key'] = '';
WC_Admin_Settings::save_fields($settings, $post);
ok(get_option('vois_wc_api_key') === $key, 'empty key field keeps saved key');

ob_start();
WC_Admin_Settings::output_fields($page->get_settings());
$html = ob_get_clean();
ok(strpos($html, 'ab12ab12ab12ab12') === false && strpos($html, 'id="vois_wc_api_key" value=""') !== false, 'settings HTML never prints the saved key');
ok(strpos($html, 'vois-wc-test') !== false && strpos($html, 'list="vois-wc-senders"') !== false, 'test button + sender datalist rendered');

// ---- test connection (AJAX) ----
$admin = new Vois_WC_Admin(new Vois_WC_Notifier());
reset_reqs();
$j = ajax([$admin, 'ajax_test_connection'], ['nonce' => wp_create_nonce('vois_wc_settings')]);
ok(! empty($j['success']) && $j['data']['ok'] === true && $j['data']['senders'] === ['MAGAZA'], 'test connection ok, approved senders only');
ok(strpos(implode(' ', $j['data']['lines']), 'Demo Test') !== false, 'report names the account');
ok(reqs()[0]['auth'] === 'Bearer ' . $key, 'Bearer key sent');
$j = ajax([$admin, 'ajax_test_connection'], ['nonce' => 'bad']);
ok(empty($j['success']), 'bad nonce rejected');

// ---- order flow ----
reset_reqs();
$order = wc_create_order();
$order->set_billing_first_name('Ayşe');
$order->set_billing_last_name('Yılmaz');
$order->set_billing_phone('0532 123 45 67');
$order->set_total(1250);
$order->save();
$oid = $order->get_id();
ok(count(reqs()) === 0, 'creating a pending order sends nothing');

$order->update_status('processing');
ok(count(reqs()) === 0, 'status change does not call the API synchronously');
$queued = array_map(function ($a) { return $a->get_args(); }, array_values(as_get_scheduled_actions(['hook' => 'vois_wc_send_sms', 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => -1])));
ok(in_array([$oid, 'customer', 'processing'], $queued, true) && in_array([$oid, 'admin', 'new'], $queued, true), 'customer + admin jobs queued in Action Scheduler: ' . wp_json_encode($queued));
run_jobs();
$r = reqs();
$cust = array_values(array_filter($r, function ($x) { return $x['path'] === '/sms/create' && $x['body']['number'] === '905321234567'; }));
$adm = array_values(array_filter($r, function ($x) { return $x['path'] === '/sms/create' && $x['body']['number'] === '905441112233'; }));
ok(count($cust) === 1 && count($adm) === 1 && count($r) === 2, 'one customer SMS + one shop SMS (landline skipped)');
$b = $cust[0]['body'];
ok($b['content'] === "Sayın Ayşe, {$oid} hazırlanıyor & %20 indirim. Ayşe'nin Butiği", 'rendered text, entities decoded: ' . $b['content']);
ok($b['sender'] === 'MAGAZA' && $b['encoding'] === 1 && $b['commercial'] === false && $b['type'] === 1 && $b['sendingType'] === 0, 'SMS body fields');
ok(preg_match('/^wc-[0-9a-f]{6}-' . $oid . '-processing$/', $b['customID']) === 1 && $cust[0]['timeout'] >= 60, 'site-scoped customID, long timeout');
$n = implode("\n", notes($oid));
ok(strpos($n, 'VOIS SMS gönderildi → 0532***4567 (paket #') !== false && strpos($n, 'mağazaya gönderildi') !== false, 'order notes written');

reset_reqs();
$order = wc_get_order($oid);
$order->update_status('on-hold');
$order->update_status('processing');
run_jobs();
ok(count(reqs()) === 0, 'processing again → no duplicate SMS, admin SMS not repeated');

$order->update_status('completed');
run_jobs();
ok(count(reqs()) === 1 && reqs()[0]['body']['content'] !== '', 'completed SMS sent with default template');

update_option('vois_wc_api_key', $key);
reset_reqs();
update_option('vois_e2e_mode', 'nocredit');
$o2 = wc_create_order(); $o2->set_billing_phone('05551234567'); $o2->save();
$o2->update_status('processing');
run_jobs();
ok(strpos(implode("\n", notes($o2->get_id())), 'SMS bakiyeniz yetersiz') !== false, 'provider error → Turkish order note');
update_option('vois_e2e_mode', 'ok');

$o3 = wc_create_order(); $o3->set_billing_phone('+49 151 1234567'); $o3->save();
reset_reqs();
$o3->update_status('processing');
run_jobs();
ok(count(array_filter(reqs(), function ($x) { return strpos((string) ($x['body']['number'] ?? ''), '49') === 0; })) === 0 && strpos(implode("\n", notes($o3->get_id())), 'Türkiye cep') !== false, 'foreign number → note, no customer SMS');

// ---- order screen box + AJAX ----
ob_start();
$admin->render_box(wc_get_order($oid));
$box = ob_get_clean();
ok(strpos($box, 'vois-wc-call') !== false && strpos($box, 'dahili 100') !== false && strpos($box, $key) === false, 'meta box renders call button with default extension');

reset_reqs();
$nonce = wp_create_nonce('vois_wc_order');
$j = ajax([$admin, 'ajax_manual_sms'], ['nonce' => $nonce, 'order_id' => (string) $oid, 'text' => 'Size özel %20 indirim, kargo & teslimat']);
ok(! empty($j['success']) && reqs()[0]['body']['content'] === 'Size özel %20 indirim, kargo & teslimat', 'manual SMS keeps % and &');

update_user_meta(1, 'vois_extension', '101');
$j = ajax([$admin, 'ajax_call'], ['nonce' => $nonce, 'order_id' => (string) $oid]);
$call = array_values(array_filter(reqs(), function ($x) { return $x['path'] === '/voice/calls'; }));
ok(! empty($j['success']) && $call && $call[0]['body'] === ['extension' => '101', 'number' => '0532 123 45 67', 'reference' => 'wc-order-' . $oid], 'click-to-call uses profile extension');
update_option('vois_e2e_mode', 'busy');
$j = ajax([$admin, 'ajax_call'], ['nonce' => $nonce, 'order_id' => (string) $oid]);
ok(empty($j['success']) && strpos($j['data']['message'], '101') !== false, 'extension_unavailable shown in Turkish');
update_option('vois_e2e_mode', 'ok');

wp_set_current_user(0);
$j = ajax([$admin, 'ajax_manual_sms'], ['nonce' => $nonce, 'order_id' => (string) $oid, 'text' => 'x']);
ok(empty($j['success']), 'logged-out user rejected');

echo "SONUC: $oks ok, $fails fail\n";
