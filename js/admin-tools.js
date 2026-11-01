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

/* Migration: select-all boxes and batched "Migrate selected". */
(function () {
    if (typeof bsTools === 'undefined') {
        return;
    }
    var checkAlls = document.querySelectorAll('.bs-check-all');
    for (var i = 0; i < checkAlls.length; i++) {
        checkAlls[i].addEventListener('change', function (e) {
            var boxes = e.target.closest('form').querySelectorAll('input[name="post_ids[]"]');
            for (var j = 0; j < boxes.length; j++) {
                boxes[j].checked = e.target.checked;
            }
            sync();
        });
    }
    var form = document.querySelector('.bs-migrate-batch');
    var button = form ? form.querySelector('.bs-migrate-selected') : null;
    var progress = form ? form.querySelector('.bs-migrate-progress') : null;

    function selected() {
        if (!form) {
            return [];
        }
        var boxes = form.querySelectorAll('input[name="post_ids[]"]:checked');
        var ids = [];
        for (var i = 0; i < boxes.length; i++) {
            ids.push(boxes[i].value);
        }
        return ids;
    }
    function sync() {
        if (button) {
            button.disabled = selected().length === 0;
        }
    }
    if (!form) {
        return;
    }
    form.addEventListener('change', sync);
    sync();

    function mark(id, text, ok) {
        var row = form.querySelector('tr[data-post-id="' + id + '"] .bs-migrate-status');
        if (row) {
            var strong = document.createElement('strong');
            strong.style.color = ok ? '#00a32a' : '#d63638';
            strong.textContent = text;
            row.textContent = '';
            row.appendChild(strong);
        }
    }

    function sendBatch(ids) {
        var body = new FormData();
        body.append('action', form.getAttribute('data-action'));
        body.append('_ajax_nonce', form.getAttribute('data-nonce'));
        body.append('mode', form.querySelector('input[name="mode"]').value);
        body.append('style', form.querySelector('input[name="style"]').value);
        for (var i = 0; i < ids.length; i++) {
            body.append('post_ids[]', ids[i]);
        }
        return fetch(bsTools.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data && json.data.message ? json.data.message : 'request failed');
                }
                var results = json.data.results || {};
                Object.keys(results).forEach(function (id) {
                    var r = results[id];
                    mark(id, r.ok ? bsTools.i18n.migrated + ' (' + r.converted + ')' : bsTools.i18n.failed + ': ' + r.message, r.ok);
                });
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var ids = selected();
        if (!ids.length || !window.confirm(bsTools.i18n.confirm)) {
            return;
        }
        button.disabled = true;
        var size = parseInt(form.getAttribute('data-batch'), 10) || 10;
        var done = 0;
        function next() {
            if (!ids.length) {
                progress.textContent = ' ' + done + ' done. Re-run the scan to refresh the report.';
                return;
            }
            var chunk = ids.splice(0, size);
            return sendBatch(chunk).then(function () {
                done += chunk.length;
                progress.textContent = ' ' + done + ' processed…';
                return next();
            });
        }
        next().catch(function (err) {
            progress.textContent = ' stopped: ' + err.message;
            button.disabled = false;
        });
    });
})();
