<?php
// Muat semua fungsi inti PHMX (termasuk htmx_request)
require_once __DIR__ . '/functions/index.php';

// Inisialisasi Routing dan Middleware (dengan halaman default)
phmx_setup('welcome');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title>PHMX Framework</title>
    <!-- Favicon / Logo Browser Tab -->
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/phmx-mark.svg">
    <link rel="alternate icon" type="image/png" href="assets/images/logo/phmx-logo.png">

    <?php if (!empty($pwa_enabled)): ?>
    <!-- PWA Meta & Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#18181b">
    <link rel="apple-touch-icon" href="assets/images/logo/phmx-logo.png">
    <?php endif; ?>

    <!-- Base URL sangat penting untuk SPA dengan HTML5 History API -->
    <base href="<?= $base_url ?>">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <!-- HTMX CDN  -->
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@1.9.10/dist/htmx.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- NProgress CDN untuk Top Progress Bar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.js"></script>

    <!-- Silent Login (Remember Me) Detection -->
    <script>
        const PHMX_IS_LOGGED_IN = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
    </script>
    <script src="assets/js/auth.js"></script>

    <!-- PWA & Web Push Notification Client -->
    <script>
        window.PHMX_PWA_ENABLED = <?= !empty($pwa_enabled) ? 'true' : 'false' ?>;
        window.PHMX_VAPID_PUBLIC_KEY = "<?= $vapid_config['public_key'] ?? '' ?>";
    </script>
    <script src="assets/js/pwa.js"></script>

    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

  </head>
  <body hx-boost="true">
    
    <!-- Navbar langsung di-include oleh PHP demi performa dan SEO -->
    <?php include __DIR__ . '/navbar.php'; ?>

    <!-- Bagian ini yang akan ditukar oleh HTMX -->
    <main id="content">
        <?php
        // Me-render file yang sesuai dengan rute ($hal) beserta auto-inject CSRF
        render_page($hal);
        ?>
    </main>

    <!-- PHMX Core Script -->
    <?php load_phmx_scripts(); ?>
  </body>
</html>