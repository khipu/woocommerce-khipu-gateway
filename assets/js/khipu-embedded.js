/**
 * Khipu embebido (KWS) en la pagina de pago del pedido (receipt_page).
 * Sirve para checkout clasico y Blocks: ambos llegan aqui via process_payment().
 *
 * OK / WARNING / CONTINUE -> pagina de pedido recibido (el webhook firmado decide el estado).
 * ERROR / abandono        -> AJAX de cancelacion (borra el cobro en Khipu) y vuelve al carrito.
 * KWS no carga            -> redireccion de respaldo al sitio de Khipu (comportamiento original).
 */
(function () {
    'use strict';

    var root = document.getElementById('khipu-web-root');
    if (!root) { return; }
    var d = root.dataset;
    var started = false;

    function go(url) { if (url) { window.location.href = url; } }
    function fallback() { go(d.fallbackUrl); }

    function loadKws(done) {
        if (typeof Khipu !== 'undefined') { done(true); return; }
        var finished = false;
        function end(ok) { if (!finished) { finished = true; clearTimeout(timer); done(ok); } }
        var timer = setTimeout(function () { end(false); }, 10000);
        var s = document.createElement('script');
        s.src = d.kwsUrl || 'https://js.khipu.com/v1/kws.js';
        s.onload = function () { end(typeof Khipu !== 'undefined'); };
        s.onerror = function () { end(false); };
        document.head.appendChild(s);
    }

    function cancel() {
        var body = new URLSearchParams();
        body.append('action', d.cancelAction);
        body.append('order_id', d.orderId);
        body.append('order_key', d.orderKey);
        body.append('nonce', d.nonce);
        fetch(d.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j && j.success && j.data && j.data.redirect) { go(j.data.redirect); return; }
                // Khipu no acepto borrar o el pedido ya no esta abierto: no cancelamos,
                // el pedido/webhook deciden. Lo llevamos a la pagina del pedido.
                go((j && j.data && j.data.redirect) || d.returnUrl);
            })
            .catch(function () { go(d.returnUrl); });
    }

    function start() {
        if (started) { return; }
        started = true;
        try {
            var khipu = new Khipu();
            khipu.startOperation(d.paymentId, function (res) {
                var r = res && res.result;
                if (r === 'ERROR') { cancel(); } else { go(d.returnUrl); }
            }, {
                mountElement: root,
                modal: true,
                modalOptions: { maxWidth: 450, maxHeight: 860 },
                options: { style: { primaryColor: '#8347AD' }, skipExitPage: true }
            });
        } catch (e) {
            if (window.console) { console.error('Khipu KWS', e); }
            fallback();
        }
    }

    loadKws(function (ok) { if (ok) { start(); } else { fallback(); } });
})();
