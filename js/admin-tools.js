/* Tools > Bootstrap Shortcodes: drive the batched scan from the browser.
 * Without JavaScript the "Scan next batch" button does the same thing one
 * request at a time. */
(function () {
    if (typeof bsTools === 'undefined') {
        return;
    }
    var controls = document.querySelector('.bs-scan-controls');
    if (!controls) {
        return;
    }
    var form = controls.querySelector('.bs-scan-form');
    var progress = controls.querySelector('.bs-scan-progress');
    var nojs = controls.querySelector('.bs-scan-nojs');
    if (nojs) {
        nojs.textContent = '';
    }

    function step(start) {
        var body = new FormData();
        body.append('action', bsTools.action);
        body.append('_ajax_nonce', bsTools.nonce);
        if (start) {
            body.append('start', '1');
        }
        return fetch(bsTools.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data && json.data.message ? json.data.message : 'scan failed');
                }
                if (progress) {
                    progress.textContent = ' ' + json.data.posts_scanned + ' / ' + json.data.total;
                }
                if (json.data.finished) {
                    window.location.href = bsTools.reload;
                    return;
                }
                return step(false);
            });
    }

    function run(start) {
        var buttons = form.querySelectorAll('button');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = true;
        }
        step(start).catch(function (err) {
            for (var j = 0; j < buttons.length; j++) {
                buttons[j].disabled = false;
            }
            window.alert('Scan stopped: ' + err.message + '. You can continue with the button.');
        });
    }

    form.addEventListener('submit', function (e) {
        var submitter = e.submitter || form.querySelector('button');
        e.preventDefault();
        run(submitter && submitter.name === 'start');
    });

    if (controls.getAttribute('data-running') === '1') {
        run(false);
    }
})();
