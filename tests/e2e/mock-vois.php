<?php
/* E2E only: answers api.vois.com.tr requests locally and records them. Never ships in the zip. */
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, 'https://api.vois.com.tr/') !== 0) {
        return $pre;
    }
    $path = substr(parse_url($url, PHP_URL_PATH), 3); // strip /v1
    $log = get_option('vois_e2e_requests', []);
    $log[] = ['path' => $path, 'method' => $args['method'], 'auth' => $args['headers']['Authorization'] ?? '', 'timeout' => $args['timeout'], 'body' => isset($args['body']) ? json_decode($args['body'], true) : null];
    update_option('vois_e2e_requests', $log, false);
    $mode = get_option('vois_e2e_mode', 'ok');
    $r = function ($code, $body) { return ['headers' => [], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null]; };
    if ($mode === 'nocredit' && $path === '/sms/create') {
        return $r(200, ['data' => null, 'err' => ['status' => 402, 'code' => 'ERR_USER_CREDIT_REQUIRED', 'message' => 'x']]);
    }
    if ($mode === 'busy' && $path === '/voice/calls') {
        return $r(409, ['error' => 'extension_unavailable', 'message' => '101 numaralı dahiliye bağlı telefon yok.']);
    }
    switch ($path) {
        case '/me': return $r(200, ['data' => ['tenant' => ['id' => 2361, 'company_name' => 'Demo Test'], 'token' => ['id' => 9, 'scopes' => ['sms:send', 'sms:read', 'voice:call']]]]);
        case '/sms/credit': return $r(200, ['data' => ['credit' => 1250.5], 'err' => null]);
        case '/sms/list-sender': return $r(200, ['data' => ['list' => [['title' => 'MAGAZA', 'status' => 1], ['title' => 'ESKI', 'status' => 0]]], 'err' => null]);
        case '/sms/create': return $r(200, ['data' => ['pkgID' => 1000 + count($log)], 'err' => null]);
        case '/voice/calls': return $r(202, ['data' => ['call_id' => 'c-1', 'status' => 'ringing_extension']]);
    }
    return $r(404, ['error' => 'not_found', 'message' => 'x']);
}, 10, 3);
