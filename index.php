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
    <!-- Base URL sangat penting untuk SPA dengan HTML5 History API -->
    <base href="<?= $base_url ?>">
    
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <!-- HTMX CDN  -->
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@1.9.10/dist/htmx.js"></script>
    
    <!-- SweetAlert2 CDN untuk Notifikasi Toast -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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