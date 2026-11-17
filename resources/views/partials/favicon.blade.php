@php($iconV = @filemtime(public_path('favicon.svg')) ?: 1)
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v={{ $iconV }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}?v={{ $iconV }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $iconV }}">
<meta name="theme-color" content="#2a2e2b">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="DMS v2">
<script>
    // PWA: service worker hanya berjalan di HTTPS / localhost (aturan browser).
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => navigator.serviceWorker.register(@json(asset('sw.js'))).catch(() => {}));
    }
</script>
