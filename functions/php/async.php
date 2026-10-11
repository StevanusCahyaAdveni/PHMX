<?php
/**
 * Helper Asynchronous & Deferred Loading untuk PHMX Framework
 * Memungkinkan pemuatan data berat (tabel, widget, grafik) secara asinkronus di latar belakang
 * tanpa menghalangi pergantian halaman awal (0 milidetik transition).
 */

/**
 * Memeriksa apakah request saat ini merupakan panggilan async
 * 
 * @param string $key Parameter penanda di $_GET (default: '_async')
 * @return bool
 */
function is_async($key = '_async') {
    return isset($_GET[$key]) && $_GET[$key] === '1';
}

/**
 * Menghasilkan URL asinkronus lengkap dengan mempertahankan parameter pencarian & paginasi saat ini
 * 
 * @param array $extraParams Parameter tambahan jika ada
 * @param string $key Parameter penanda async
 * @return string
 */
function async_url($extraParams = [], $key = '_async') {
    global $hal;
    $currentRoute = $hal ?? ($_GET['hal'] ?? '');
    
    $params = $_GET;
    $params[$key] = '1';
    unset($params['hal'], $params['act']);
    
    if (!empty($extraParams)) {
        $params = array_merge($params, $extraParams);
    }
    
    return $currentRoute . '?' . http_build_query($params);
}

/**
 * Membuka container async dengan placeholder loading otomatis pada initial load.
 * Saat request async dipanggil, fungsi ini akan membersihkan semua buffer HTML sebelumnya
 * (seperti navbar, layout utama, dan header yang tidak diperlukan) sehingga respons yang dikirim
 * murni hanya isi container async tersebut.
 * 
 * @param string $loadingText Teks loading saat data sedang dimuat
 * @param string $containerId ID unik container HTML
 * @param string $key Parameter penanda async
 * @return string
 */
function async_start($loadingText = 'Sedang memuat data...', $containerId = 'async-wrapper', $key = '_async') {
    $url = htmlspecialchars(async_url([], $key));

    if (is_async($key)) {
        // Lepaskan session lock PHP agar navigasi/request halaman lain tidak terblokir (Non-blocking)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Bersihkan semua output buffer sebelumnya (navbar, header luar, dsb)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        return "";
    }

    return "<div id='{$containerId}' hx-get='{$url}' hx-trigger='load' hx-swap='innerHTML'>
                <div class='p-5 text-center'>
                    <div class='spinner-border spinner-border-sm text-primary me-2' role='status'></div>
                    <span class='text-muted small'>{$loadingText}</span>
                </div>";
}

/**
 * Menutup container async.
 * Jika sedang request async, fungsi ini langsung memanggil exit
 * agar elemen di bawahnya (seperti modal dan script) tidak terduplikasi.
 * 
 * @param string $key Parameter penanda async
 * @return string
 */
function async_end($key = '_async') {
    if (is_async($key)) {
        exit;
    }
    return "</div>";
}
