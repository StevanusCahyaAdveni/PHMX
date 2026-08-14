<?php
/**
 * Fungsi untuk me-render halaman (termasuk auto-inject CSRF)
 */
function render_page($hal) {
    global $con, $base_url;
    $file = __DIR__ . '/../../pages/' . $hal . '.php';
    
    if (file_exists($file)) {
        // Gunakan Output Buffering untuk Auto-Inject CSRF Token ke dalam Form
        ob_start();
        require $file;
        $html = ob_get_clean();
        
        // Jika halaman mengandung form POST/PUT/DELETE, sisipkan input hidden CSRF
        if (stripos($html, '<form') !== false) {
            $token = $_SESSION['csrf_token'] ?? '';
            $csrf_input = "\n<input type='hidden' name='csrf_token' value='{$token}'>\n";
            // Sisipkan HANYA pada form yang memiliki method POST atau atribut hx-post/put/delete
            $html = preg_replace('/(<form[^>]*\b(?:method=[\'"]?POST[\'"]?|hx-post|hx-put|hx-delete)\b[^>]*>)/i', '$1' . $csrf_input, $html);
        }
        
        echo $html;
    } else {
        http_response_code(404);
        echo "<div class='container py-5 text-center'>
                <h1 class='display-1 text-danger fw-bold'>404</h1>
                <p class='lead'>Halaman tidak ditemukan.</p>
                <a href='welcome' class='btn btn-primary mt-3'>Kembali ke Beranda</a>
              </div>";
    }
}
?>
