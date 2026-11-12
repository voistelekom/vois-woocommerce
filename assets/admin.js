/* VOIS for WooCommerce — order box and settings test button. No dependencies. */
(function () {
  'use strict';
  var cfg = window.voisWc || {};

  function post(data) {
    var body = new URLSearchParams(data);
    return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); });
  }

  function show(el, ok, text) {
    el.textContent = text;
    el.style.color = ok ? '#1e7e34' : '#b32d2e';
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    var box = t.closest && t.closest('.vois-wc-box');

    if (box && (t.classList.contains('vois-wc-sms') || t.classList.contains('vois-wc-call'))) {
      var isSms = t.classList.contains('vois-wc-sms');
      var out = box.querySelector('.vois-wc-result');
      var data = { action: isSms ? 'vois_wc_manual_sms' : 'vois_wc_call', nonce: box.dataset.nonce, order_id: box.dataset.order };
      if (isSms) {
        data.text = box.querySelector('.vois-wc-text').value;
        if (!data.text.trim()) { return; }
      }
      t.disabled = true;
      show(out, true, isSms ? cfg.sending : cfg.calling);
      post(data).then(function (r) {
        show(out, !!r.success, (r.data && r.data.message) || cfg.failed);
        if (r.success && isSms) { box.querySelector('.vois-wc-text').value = ''; }
      }).catch(function () { show(out, false, cfg.failed); })
        .then(function () { t.disabled = false; });
    }

    if (t.classList && t.classList.contains('vois-wc-test')) {
      var res = document.querySelector('.vois-wc-test-result');
      var key = document.getElementById('vois_wc_api_key');
      var c2c = document.getElementById('vois_wc_click_to_call');
      t.disabled = true;
      show(res, true, cfg.testing);
      post({ action: 'vois_wc_test_connection', nonce: cfg.settingsNonce, key: key ? key.value : '', c2c: c2c && c2c.checked ? 'yes' : 'no' })
        .then(function (r) {
          if (!r.success) { show(res, false, (r.data && r.data.message) || cfg.failed); return; }
          res.innerHTML = '';
          res.style.color = r.data.ok ? '#1e7e34' : '#b32d2e';
          r.data.lines.forEach(function (line) { var p = document.createElement('div'); p.textContent = line; res.appendChild(p); });
          var list = document.getElementById('vois-wc-senders');
          if (list && r.data.senders.length) {
            list.innerHTML = '';
            r.data.senders.forEach(function (s) { var o = document.createElement('option'); o.value = s; list.appendChild(o); });
            var sender = document.getElementById('vois_wc_sender');
            if (sender && !sender.value) { sender.value = r.data.senders[0]; }
          }
        }).catch(function () { show(res, false, cfg.failed); })
        .then(function () { t.disabled = false; });
    }
  });
})();
