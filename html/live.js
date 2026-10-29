// Live updates for the main page: every few seconds fetch index.php in the background and swap
// the torrent list and drive-space bar in place, instead of reloading the whole page.
// index.php marks the parts to swap with id="tf-live" and id="tf-drivespace".
(function () {
    var INTERVAL = 5000;     // ms between updates
    var busy = false, last = Date.now(), failures = 0;

    function setStatus() {
        var s = document.getElementById('tf-live-status');
        if (!s) return;
        if (document.hidden) s.textContent = 'Live updates paused';
        else if (failures >= 2) s.textContent = 'Live updates: server not responding, retrying';
        else s.textContent = 'Live updates on · updated ' + Math.round((Date.now() - last) / 1000) + 's ago';
    }

    // an overlib "Torrent Status" popup is open: swapping its row would leave it stuck on screen
    function popupOpen() {
        var o = document.getElementById('overDiv');
        return o && o.style.visibility == 'visible';
    }

    function swap(doc, id) {
        var mine = document.getElementById(id), fresh = doc.getElementById(id);
        if (mine && fresh) mine.innerHTML = fresh.innerHTML;
    }

    function update() {
        if (busy || document.hidden || popupOpen()) return;
        busy = true;
        fetch('index.php?live=1', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                // signed out, or refresh turned off in another tab: let the full page take over
                if (!doc.getElementById('tf-live-status')) { location.href = 'index.php'; return; }
                swap(doc, 'tf-live');
                swap(doc, 'tf-drivespace');
                last = Date.now();
                failures = 0;
            })
            .catch(function () { failures++; })
            .then(function () { busy = false; setStatus(); });
    }

    // rundialog.js asks for an update right after a torrent is started
    window.tfLiveUpdate = update;

    // the page no longer reloads to plain index.php, so drop action parameters (?kill=..., ?delfile=...)
    // from the address bar: F5 must not repeat a stop or delete
    if (location.search && window.history.replaceState) history.replaceState(null, '', 'index.php');

    document.addEventListener('visibilitychange', function () {
        setStatus();
        if (!document.hidden) update();
    });
    setInterval(update, INTERVAL);
    setInterval(setStatus, 1000);
    setStatus();
})();
