<script>
(function() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ route("pwa.serviceworker") }}', { scope: '/' });
    }
    var deferredPrompt;

    /**
     * When viewing in browser (not PWA), target="_blank" links should open in browser.
     * When viewing in PWA, target="_blank" links open in PWA (default behavior).
     */
    function isPwaDisplayMode() {
        return window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            window.matchMedia('(display-mode: minimal-ui)').matches ||
            (typeof navigator.standalone === 'boolean' && navigator.standalone);
    }
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[target="_blank"]');
        if (!link || !link.href || link.getAttribute('href') === '#') return;
        if (isPwaDisplayMode()) return; // In PWA: use default behavior (opens in PWA)
        e.preventDefault();
        window.open(link.href, '_blank', 'noopener,noreferrer');
    }, true);

    function showInstallButton() {
        var wrapper = document.getElementById('installAppWrapper');
        if (wrapper) {
            wrapper.classList.remove('d-none');
        } else {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'installApp';
            btn.className = 'btn btn-sm btn-primary position-fixed bottom-0 end-0 m-3';
            btn.innerHTML = '<i class="icon-base ti tabler-download me-1"></i>{{ __("Install App") }}';
            btn.onclick = function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function() { deferredPrompt = null; });
                }
            };
            document.body.appendChild(btn);
        }
    }
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        showInstallButton();
    });
    window.addEventListener('load', function() {
        var btn = document.getElementById('installApp');
        if (btn && !btn.onclick) {
            btn.addEventListener('click', function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function() { deferredPrompt = null; });
                }
            });
        }
    });
})();
</script>
