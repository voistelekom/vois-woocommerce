<?php
// Minimal WordPress / WooCommerce stand-ins for unit tests (no WordPress install).
define('ABSPATH', __DIR__ . '/');
$GLOBALS['wp_options'] = [];
$GLOBALS['wp_actions'] = [];
$GLOBALS['as_jobs'] = [];

function __($t, $d = null) { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['wp_options']) ? $GLOBALS['wp_options'][$k] : $d; }
function update_option($k, $v) { $GLOBALS['wp_options'][$k] = $v; return true; }
function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function get_bloginfo($k) { return $k === 'name' ? 'Test Mağaza' : ''; }
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['wp_actions'][$h][] = $cb; }
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['wp_actions'][$h][] = $cb; }
function wc_get_order_status_name($s) { $m = ['processing' => 'Hazırlanıyor', 'completed' => 'Tamamlandı']; return $m[$s] ?? $s; }
function wc_get_order($id) { return $GLOBALS['wc_orders'][$id] ?? false; }
function as_enqueue_async_action($hook, $args = [], $group = '') { $GLOBALS['as_jobs'][] = [$hook, $args, $group]; return count($GLOBALS['as_jobs']); }
function wc_get_logger() { return new class { public $lines = []; public function log($l, $m, $c = []) { $GLOBALS['wc_log'][] = [$l, $m]; } }; }

final class Fake_Date { private $ts; public function __construct($ts) { $this->ts = $ts; } public function date_i18n($f) { return date($f, $this->ts); } }

final class Fake_Order
{
    public $id; public $data; public $meta = []; public $notes = []; public $saved = 0;
    public function __construct($id, array $data = []) {
        $this->id = $id;
        $this->data = $data + ['number' => (string) $id, 'phone' => '0532 123 45 67', 'first' => 'Ayşe', 'last' => 'Yılmaz',
            'total' => '1250.00', 'currency' => 'TRY', 'status' => 'processing', 'created' => mktime(10, 0, 0, 11, 12, 2026), 'payment' => 'Kapıda ödeme'];
    }
    public function get_id() { return $this->id; }
    public function get_order_number() { return $this->data['number']; }
    public function get_billing_phone() { return $this->data['phone']; }
    public function get_billing_first_name() { return $this->data['first']; }
    public function get_billing_last_name() { return $this->data['last']; }
    public function get_total() { return $this->data['total']; }
    public function get_currency() { return $this->data['currency']; }
    public function get_status() { return $this->data['status']; }
    public function get_date_created() { return $this->data['created'] ? new Fake_Date($this->data['created']) : null; }
    public function get_payment_method_title() { return $this->data['payment']; }
    public function get_meta($k, $single = true) { return $this->meta[$k] ?? ''; }
    public function update_meta_data($k, $v) { $this->meta[$k] = $v; }
    public function add_order_note($n) { $this->notes[] = $n; return count($this->notes); }
    public function save() { $this->saved++; return $this->id; }
}

foreach (glob(__DIR__ . '/../src/class-vois-wc-{options,phone,template,errors,api,notifier,admin}.php', GLOB_BRACE) as $f) {
    require_once $f;
}
$GLOBALS['wp_usermeta'] = [];
function get_user_meta($uid, $k, $single = true) { return $GLOBALS['wp_usermeta'][$uid][$k] ?? ''; }

// ---- AJAX / settings stand-ins ----
final class Json_Sent extends Exception { public $success; public $data; public $code; public function __construct($s, $d, $c) { parent::__construct('json'); $this->success = $s; $this->data = $d; $this->code = $c; } }
$GLOBALS['nonce_ok'] = true; $GLOBALS['caps'] = []; $GLOBALS['current_user'] = 5; $GLOBALS['transients'] = [];
function check_ajax_referer($a, $f) { if (! $GLOBALS['nonce_ok']) { throw new Json_Sent(false, ['message' => 'nonce'], 403); } return 1; }
function current_user_can($c, ...$a) { return in_array($c, $GLOBALS['caps'], true); }
function wp_send_json_success($d = null, $c = null) { throw new Json_Sent(true, $d, $c); }
function wp_send_json_error($d = null, $c = null) { throw new Json_Sent(false, $d, $c); }
function absint($v) { return abs((int) $v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function get_current_user_id() { return $GLOBALS['current_user']; }
function wp_get_current_user() { return (object) ['ID' => $GLOBALS['current_user'], 'display_name' => 'Ali Personel']; }
function set_transient($k, $v, $t) { $GLOBALS['transients'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['transients'][$k] ?? false; }
if (! defined('MINUTE_IN_SECONDS')) { define('MINUTE_IN_SECONDS', 60); }
class WC_Settings_Page { public $id; public $label; public function __construct() {} }
class WC_Admin_Settings { public static $errors = []; public static function add_error($m) { self::$errors[] = $m; } }
class WC_Order {}
require_once __DIR__ . '/../src/class-vois-wc-settings.php';
function esc_html__($t, $d = null) { return esc_html($t); }
function esc_attr__($t, $d = null) { return esc_attr($t); }
