<?php
/**
 * Pusat Registrasi Fungsi PHMX (PHP & JS)
 */

// 1. Load Middleware & Core PHP
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/php/generate_uuid.php';
require_once __DIR__ . '/php/sanitasi.php';
require_once __DIR__ . '/php/secure_query.php';
require_once __DIR__ . '/php/redirect.php';
require_once __DIR__ . '/php/upload_file.php';
require_once __DIR__ . '/php/pagination.php';
require_once __DIR__ . '/php/search_form.php';


// Jika Anda punya fungsi PHP lain di masa depan, include di sini:
// require_once __DIR__ . '/php/database.php';
// require_once __DIR__ . '/php/auth.php';

// Load Core Functionalities
require_once __DIR__ . '/php/htmx_request.php';
require_once __DIR__ . '/php/phmx_setup.php';
require_once __DIR__ . '/php/phmx_scripts.php';
require_once __DIR__ . '/php/render_page.php';
?>
