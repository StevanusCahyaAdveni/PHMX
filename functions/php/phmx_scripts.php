<?php
/**
 * 3. Fungsi untuk memuat semua Script JS Inti PHMX
 * Panggil fungsi ini tepat sebelum tag penutup </body> di index.php
 */
function load_phmx_scripts() {
    $v = time(); // Untuk mencegah cache saat development
    
    // Skrip routing utama dihapus karena digantikan HTML5 History API (HTMX)
    // Jika Anda punya skrip JS tambahan di masa depan, tambahkan di sini:
    // echo "<script src=\"functions/js/utilities.js?v={$v}\"></script>\n";
}
?>
