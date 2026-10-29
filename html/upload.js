// File upload on the Directory list (dir.php, the #tf-up box): sends each chosen or dropped file to
// upload.php in 4 MB pieces (raw request bodies, under PHP's default 8 MB post_max_size), retrying a piece
// that fails, with a progress bar, speed and time left. Reloads the list when everything is in.
(function () {
    var PIECE = 4 * 1024 * 1024;
    var box = document.getElementById('tf-up');
    if (!box) return;
    var dir = box.getAttribute('data-dir');
    var input = document.getElementById('tf-up-files');
    var go = document.getElementById('tf-up-go');
    var chosen = [], cancelled = false, busy = false;

    function $(id) { return document.getElementById(id); }

    function fmt(b) {
        var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
        while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
        return (i ? b.toFixed(b < 10 ? 2 : 1) : b) + ' ' + u[i];
    }

    function fmtTime(s) {
        if (!isFinite(s)) return '';
        s = Math.round(s);
        if (s < 60) return s + 's left';
        if (s < 3600) return Math.floor(s / 60) + 'm ' + (s % 60) + 's left';
        return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm left';
    }

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    function message(text, error, ok) {
        var m = $('tf-up-msg');
        m.textContent = text;
        m.className = text ? 'tf-run-notice' + (error ? ' tf-run-notice-error' : '') + (ok ? ' tf-run-notice-ok' : '') : '';
    }

    // live=1: these requests are not counted as page hits
    function post(params, body) {
        params.live = 1;
        return fetch('upload.php?' + new URLSearchParams(params), { method: 'POST', credentials: 'same-origin', body: body || '' })
            .then(function (r) {
                return r.json().catch(function () { return { error: 'The server sent an unexpected reply (HTTP ' + r.status + ').' }; })
                    .then(function (j) {
                        if (!r.ok || j.error) { var e = new Error(j.error || 'HTTP ' + r.status); e.data = j; e.status = r.status; throw e; }
                        return j;
                    });
            });
    }

    function choose(files) {
        if (busy) return;
        chosen = Array.prototype.slice.call(files);
        var total = chosen.reduce(function (s, f) { return s + f.size; }, 0);
        $('tf-up-chosen').textContent = chosen.length == 0 ? 'or drop files here'
            : chosen.length == 1 ? chosen[0].name + ' (' + fmt(total) + ')'
            : chosen.length + ' files (' + fmt(total) + ')';
        go.disabled = chosen.length == 0;
        message('');
    }

    async function uploadOne(file, index, count) {
        $('tf-up-name').textContent = (count > 1 ? (index + 1) + ' of ' + count + ': ' : '') + file.name;
        $('tf-up-bar').style.width = '0%';
        $('tf-up-stats').textContent = 'starting...';

        var s = await post({ op: 'start', dir: dir, name: file.name, size: file.size });
        var off = 0, t0 = Date.now();
        try {
            while (off < file.size) {
                if (cancelled) throw new Error('cancelled');
                var piece = file.slice(off, off + PIECE), tries = 0, r;
                for (;;) {
                    try {
                        r = await post({ op: 'chunk', id: s.id, offset: off }, piece);
                        break;
                    } catch (e) {
                        // the server has a different amount than we think: carry on from there
                        if (e.status == 409 && e.data && typeof e.data.expected == 'number') { r = { received: e.data.expected }; break; }
                        if (cancelled || (e.status && e.status < 500 && e.status != 408) || ++tries >= 4) throw e;
                        $('tf-up-stats').textContent = 'connection problem, retrying...';
                        await sleep(1500 * tries);
                    }
                }
                off = r.received;
                var secs = (Date.now() - t0) / 1000, speed = off / Math.max(secs, 0.001);
                $('tf-up-bar').style.width = (file.size ? off * 100 / file.size : 100).toFixed(1) + '%';
                $('tf-up-stats').textContent = Math.floor(file.size ? off * 100 / file.size : 100) + '% · ' + fmt(off) + ' of ' + fmt(file.size)
                    + ' · ' + fmt(speed) + '/s · ' + fmtTime((file.size - off) / speed);
            }
            var fin = await post({ op: 'finish', id: s.id });
            $('tf-up-bar').style.width = '100%';
            return fin.name;
        } catch (e) {
            post({ op: 'cancel', id: s.id }).catch(function () { });
            throw e;
        }
    }

    async function uploadAll() {
        if (busy || chosen.length == 0) return;
        busy = true; cancelled = false;
        go.disabled = true; input.disabled = true;
        $('tf-up-progress').hidden = false;
        message('');
        var done = [];
        try {
            for (var i = 0; i < chosen.length; i++) {
                done.push(await uploadOne(chosen[i], i, chosen.length));
            }
            busy = false;
            message('Uploaded: ' + done.join(', ') + '. Refreshing the list...', false, true);
            setTimeout(function () { location.reload(); }, 1200);
        } catch (e) {
            $('tf-up-progress').hidden = true;
            message(e.message == 'cancelled' ? 'Upload cancelled.' + (done.length ? ' Finished before that: ' + done.join(', ') + '.' : '')
                                             : 'Upload failed: ' + e.message, e.message != 'cancelled');
            if (done.length) setTimeout(function () { location.reload(); }, 2500);
            busy = false; input.disabled = false; choose([]);
            input.value = '';
        }
    }

    input.addEventListener('change', function () { choose(input.files); });
    go.addEventListener('click', uploadAll);
    $('tf-up-cancel').addEventListener('click', function () { cancelled = true; $('tf-up-stats').textContent = 'cancelling...'; });

    // drag and drop onto the box
    ['dragenter', 'dragover'].forEach(function (ev) {
        box.addEventListener(ev, function (e) { e.preventDefault(); if (!busy) box.classList.add('tf-up-drop'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        box.addEventListener(ev, function (e) { e.preventDefault(); box.classList.remove('tf-up-drop'); });
    });
    box.addEventListener('drop', function (e) { if (!busy && e.dataTransfer.files.length) choose(e.dataTransfer.files); });

    // leaving the page mid-upload would abandon it
    window.addEventListener('beforeunload', function (e) { if (busy && !cancelled) { e.preventDefault(); e.returnValue = ''; } });
})();
