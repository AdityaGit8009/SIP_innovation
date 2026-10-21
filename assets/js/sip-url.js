(function (global) {
    'use strict';
    global.sipUrl = function (path) {
        path = String(path || '');
        // Callers supply application-relative paths, including query strings.
        if (/^[a-z][a-z0-9+.-]*:/i.test(path) || /^\/\//.test(path) || /\\/.test(path)) {
            throw new Error('Expected an application-relative URL');
        }
        path = path.replace(/^\/+/, '');
        var pathname = path.split(/[?#]/)[0];
        if (pathname.split('/').some(function (part) {
            try {
                var decoded = decodeURIComponent(part);
                return decoded === '.' || decoded === '..' || /[/\\]/.test(decoded);
            } catch (e) {
                return true;
            }
        })) {
            throw new Error('Invalid application-relative URL');
        }
        return (global.SIP_BASE_URL || '') + '/' + path;
    };
})(window);
