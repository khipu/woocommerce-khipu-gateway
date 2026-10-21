/**
 * Khipu embebido (KWS) en la pagina de pago del pedido (receipt_page).
 * Sirve para checkout clasico y Blocks: ambos llegan aqui via process_payment().
 *
 * OK / WARNING / CONTINUE -> pagina de pedido recibido (el webhook firmado decide el estado).
 * ERROR / abandono        -> muestra el motivo, cancela el cobro en Khipu en segundo plano (AJAX) y
 *                            ofrece volver al carrito; no navega sola para dar tiempo de leer el error.
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

    // Mensaje bajo el modal: queda a la vista aunque Khipu cierre su ventana, para
    // que el comprador pueda leer por qué falló el pago. Todo va como texto.
    function showError(res) {
        var msg = document.querySelector('.khipu-embedded-msg');
        if (!msg) {
            msg = document.createElement('p');
            msg.className = 'khipu-embedded-msg';
            root.parentNode.insertBefore(msg, root.nextSibling);
        }
        while (msg.firstChild) { msg.removeChild(msg.firstChild); }
        var reason = (res && (res.exitMessage || res.failureReason)) || '';
        msg.appendChild(document.createTextNode('El pago no se completó' + (reason ? ': ' + reason : '.') + ' '));
        var slot = document.createElement('span');
        slot.id = 'khipu-embedded-after';
        msg.appendChild(slot);
    }

    function setLink(url, label) {
        var slot = document.getElementById('khipu-embedded-after');
        if (!slot || !url) { return; }
        var a = document.createElement('a');
        a.href = url;
        a.textContent = label;
        slot.appendChild(a);
    }

    // No se navega sola: se cancela el cobro en segundo plano y el comprador
    // vuelve cuando termine de leer.
    function cancel(res) {
        showError(res);
        var body = new URLSearchParams();
        body.append('action', d.cancelAction);
        body.append('order_id', d.orderId);
        body.append('order_key', d.orderKey);
        body.append('nonce', d.nonce);
        fetch(d.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j && j.success && j.data && j.data.redirect) { setLink(j.data.redirect, 'Volver al carrito'); return; }
                // Khipu no acepto borrar o el pedido ya no esta abierto: no cancelamos,
                // el pedido/webhook deciden. Lo llevamos a la pagina del pedido.
                setLink((j && j.data && j.data.redirect) || d.returnUrl, 'Ver mi pedido');
            })
            .catch(function () { setLink(d.returnUrl, 'Ver mi pedido'); });
    }

    function start() {
        if (started) { return; }
        started = true;
        try {
            var khipu = new Khipu();
            khipu.startOperation(d.paymentId, function (res) {
                var r = res && res.result;
                if (r === 'ERROR') { cancel(res); } else { go(d.returnUrl); }
            }, {
                mountElement: root,
                modal: true,
                modalOptions: { maxWidth: 450, maxHeight: 860 },
                options: { style: { primaryColor: '#8347AD' }, skipExitPage: false }
            });
        } catch (e) {
            if (window.console) { console.error('Khipu KWS', e); }
            fallback();
        }
    }

    loadKws(function (ok) { if (ok) { start(); } else { fallback(); } });
})();
