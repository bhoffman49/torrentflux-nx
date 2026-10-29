// Run Torrent, Download Details and Make Torrent open in a dialog over the page instead of a separate
// browser window. The page inside the dialog's iframe calls back through tfRunClose / tfRunDone / tfRunFit.
(function () {
    var dlg, frame;

    function build() {
        dlg = document.createElement('dialog');
        dlg.className = 'tf-dialog';
        frame = document.createElement('iframe');
        frame.title = 'Run Torrent';
        dlg.appendChild(frame);
        document.body.appendChild(dlg);
        // a click on the dimmed backdrop lands on the dialog element itself
        dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
        // the close event arrives a moment later: don't blank a page that was opened again since
        dlg.addEventListener('close', function () { if (!dlg.open) frame.src = 'about:blank'; });
        frame.addEventListener('load', fit);
        window.addEventListener('resize', fit);
    }

    // size the dialog to its content, up to 90% of the window (the page scrolls inside past that)
    function fit() {
        if (!dlg || !dlg.open) return;
        try {
            var h = frame.contentWindow.tfRunHeight ? frame.contentWindow.tfRunHeight() : 0;
            if (h) frame.style.height = Math.min(h, Math.floor(window.innerHeight * 0.9)) + 'px';
        } catch (e) { }
    }

    function close() {
        if (dlg && dlg.open) dlg.close();
    }

    window.tfRunClose = close;
    window.tfRunFit = fit;

    // the torrent was started: close and show it in the list
    window.tfRunDone = function () {
        close();
        if (window.tfLiveUpdate) window.tfLiveUpdate();
        else location.href = 'index.php';
    };

    function open(url, narrow, w, h) {
        if (!window.HTMLDialogElement) {
            window.open(url, '_blank', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=' + w + ',height=' + h);
            return;
        }
        if (!dlg) build();
        dlg.classList.toggle('tf-dialog-narrow', narrow);
        frame.style.height = '320px';
        frame.src = url;
        dlg.showModal();
    }

    // Run Torrent (startpop.php), Download Details (downloaddetails.php, from a torrent's status link)
    // and Make Torrent (maketorrent.php, from the Directory list)
    window.StartTorrent = function (url) { open(url, false, 720, 640); };
    window.ShowDetails = function (url) { open(url, true, 480, 460); };
    window.MakeTorrent = function (url) { open(url, false, 720, 640); };
})();
