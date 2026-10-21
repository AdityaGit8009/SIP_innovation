(function () {
    'use strict';
    const token = document.querySelector('meta[name="sip-csrf-token"]').content;
    const safe = method => /^(GET|HEAD|OPTIONS)$/i.test(method || 'GET');
    const sameOrigin = url => new URL(url, location.href).origin === location.origin;
    function mutation(url) {
        const u = new URL(url, location.href);
        if (u.origin !== location.origin) return false;
        const name = u.pathname.split('/').pop();
        return ['remove_address.php', 'delete_supplier.php'].includes(name)
            || (name === 'cart.php' && u.searchParams.has('add'))
            || (u.pathname.includes('/admin/') && ['delete', 'toggle', 'toggle_status', 'status'].some(k => u.searchParams.has(k)));
    }
    const nativeFetch = window.fetch;
    window.fetch = function (input, options) {
        const url = input instanceof Request ? input.url : input;
        const init = Object.assign({}, options);
        let method = init.method || (input instanceof Request ? input.method : 'GET');
        if (safe(method) && mutation(url)) init.method = method = 'POST';
        if (sameOrigin(url) && !safe(method)) {
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            init.headers = headers;
        }
        return nativeFetch.call(this, input, init);
    };
    const open = XMLHttpRequest.prototype.open;
    const send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        if (safe(method) && mutation(url)) method = 'POST';
        this.sipNeedsCsrf = sameOrigin(url) && !safe(method);
        return open.call(this, method, url, ...rest);
    };
    XMLHttpRequest.prototype.send = function (body) {
        if (this.sipNeedsCsrf) this.setRequestHeader('X-CSRF-Token', token);
        return send.call(this, body);
    };
    function addToken(form) {
        if (!sameOrigin(form.action) || safe(form.method)) return;
        let field = form.querySelector('input[name="_csrf"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden'; field.name = '_csrf'; form.appendChild(field);
        }
        field.value = token;
    }
    document.addEventListener('submit', e => addToken(e.target), true);
    document.addEventListener('formdata', e => {
        if (sameOrigin(e.target.action)) e.formData.set('_csrf', token);
    });
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('form').forEach(addToken));
    // Existing admin action links keep their URLs and confirmation dialogs,
    // but the authenticated mutation is submitted as POST.
    document.addEventListener('click', e => {
        const link = e.target.closest('a[href]');
        if (!link || e.defaultPrevented || !mutation(link.href)) return;
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST'; form.action = link.href;
        document.body.appendChild(form); addToken(form); form.submit();
    });
})();
