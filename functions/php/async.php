<?php
/**
 * Helper Asynchronous, Deferred Loading & Smart Component Polling untuk PHMX Framework
 * Memungkinkan pemuatan data berat (tabel, widget, grafik) secara asinkronus di latar belakang
 * serta pembaruan berkala (polling) khusus komponen tertentu tanpa me-reload body halaman.
 */

/**
 * Memeriksa apakah request saat ini merupakan panggilan async atau polling komponen
 * 
 * @param string $key Parameter penanda di $_GET (default: '_async')
 * @return bool
 */
function is_async($key = '_async') {
    return isset($_GET[$key]) && $_GET[$key] === '1';
}

/**
 * Memeriksa apakah request saat ini merupakan panggilan polling komponen
 * 
 * @param string $key Parameter penanda di $_GET (default: '_poll')
 * @return bool
 */
function is_poll($key = '_poll') {
    return is_async($key);
}

/**
 * Normalisasi format interval waktu polling HTMX (contoh: 5 -> '5s', '10s' -> '10s')
 * 
 * @param string|int|null $interval Interval polling
 * @return string|null
 */
function _phmx_normalize_interval($interval) {
    if ($interval === null || $interval === '' || $interval === false) {
        return null;
    }
    $interval = trim((string)$interval);
    if ($interval === '') {
        return null;
    }
    if (is_numeric($interval)) {
        return $interval . 's';
    }
    return $interval;
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
 * Menghasilkan URL polling komponen dengan mempertahankan query string halaman saat ini
 * 
 * @param array $extraParams Parameter tambahan jika ada
 * @param string $key Parameter penanda polling (default: '_poll')
 * @return string
 */
function poll_url($extraParams = [], $key = '_poll') {
    return async_url($extraParams, $key);
}

/**
 * Membuka container async (dan opsional polling berkala) dengan placeholder loading otomatis pada initial load.
 * Saat request async/poll dipanggil, fungsi ini membersihkan semua buffer HTML sebelumnya
 * (seperti navbar, layout utama, dan header yang tidak diperlukan) sehingga respons yang dikirim
 * murni hanya isi container komponen tersebut.
 * 
 * @param string|null $loadingText Teks loading saat data pertama kali dimuat (kosongkan/null jika tanpa spinner)
 * @param string $containerId ID unik container HTML
 * @param string $key Parameter penanda async/poll
 * @param string|int|null $pollInterval Interval polling opsional (misal: '5s', '10s', atau 5)
 * @return string
 */
function async_start($loadingText = 'Sedang memuat data...', $containerId = 'async-wrapper', $key = '_async', $pollInterval = null) {
    $url = htmlspecialchars(async_url([], $key), ENT_QUOTES);

    if (is_async($key)) {
        // Catat key aktif agar helper lain (seperti paginationQuery) dapat membersihkan parameter ini dari URL
        $GLOBALS['_phmx_active_async_key'] = $key;

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

    $normInterval = _phmx_normalize_interval($pollInterval);
    $keyAttr = htmlspecialchars($key, ENT_QUOTES);
    $containerIdAttr = htmlspecialchars($containerId, ENT_QUOTES);

    if ($normInterval !== null) {
        $intervalAttr = htmlspecialchars($normInterval, ENT_QUOTES);
        // Gunakan filter [!document.hidden] bawaan HTMX agar otomatis pause saat tab tidak aktif
        $trigger = "load, every {$intervalAttr} [!document.hidden]";
        $dataAttrs = " data-phmx-async='1' data-phmx-poll='{$intervalAttr}' data-phmx-key='{$keyAttr}'";
    } else {
        $trigger = "load";
        $dataAttrs = " data-phmx-async='1' data-phmx-key='{$keyAttr}'";
    }

    $placeholderHtml = "";
    if ($loadingText !== null && $loadingText !== '') {
        $placeholderHtml = "
                <div class='p-5 text-center'>
                    <div class='spinner-border spinner-border-sm text-primary me-2' role='status'></div>
                    <span class='text-muted small'>{$loadingText}</span>
                </div>";
    }

    return "<div id='{$containerIdAttr}' hx-get='{$url}' hx-trigger='{$trigger}' hx-swap='innerHTML' hx-disinherit='hx-swap hx-target'{$dataAttrs}>{$placeholderHtml}";
}

/**
 * Membuka container khusus Smart Component Polling (Hanya me-refresh komponen ini secara berkala, bukan body).
 * Dilengkapi fitur:
 * - Smart DOM Diff (HTTP 204 No Content via X-PHMX-Hash jika isi HTML tidak berubah)
 * - Auto-Pause saat tab browser tidak aktif (Page Visibility API) atau user sedang mengetik di dalam komponen
 * - Silent Polling (tanpa memicu NProgress bar) & Auto-Abort saat navigasi SPA
 * 
 * @param string|int $interval Interval polling (misal: '5s', '10s', '30s', atau angka detik 5)
 * @param string $key Parameter unik penanda komponen (default: '_poll')
 * @param string $containerId ID unik container HTML (otomatis menyesuaikan $key jika dibiarkan default)
 * @param string|null $loadingText Teks loading saat pemuatan pertama (default: 'Memuat komponen...', null untuk tanpa spinner)
 * @return string
 */
function poll_start($interval = '5s', $key = '_poll', $containerId = 'poll-wrapper', $loadingText = 'Memuat komponen...') {
    if ($containerId === 'poll-wrapper' && $key !== '_poll') {
        $containerId = 'poll-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $key);
    }
    return async_start($loadingText, $containerId, $key, $interval);
}

/**
 * Menutup container async/polling.
 * Jika sedang melayani request async/poll, fungsi ini melakukan:
 * 1. Auto-inject CSRF token jika terdapat <form> di dalam komponen
 * 2. Komputasi hash MD5 (X-PHMX-Hash) untuk Smart DOM Diff (mengembalikan 204 No Content jika identik)
 * 3. Menghentikan eksekusi halaman (exit) agar elemen di bawahnya tidak ikut ter-render.
 * 
 * @param string $key Parameter penanda async
 * @return string
 */
function async_end($key = '_async') {
    if (is_async($key)) {
        $html = ob_get_level() > 0 ? ob_get_clean() : '';

        // Auto-inject CSRF Token jika di dalam komponen async/poll terdapat form POST/PUT/DELETE
        if (stripos($html, '<form') !== false && !empty($_SESSION['csrf_token'])) {
            $token = $_SESSION['csrf_token'];
            $csrf_input = "\n<input type='hidden' name='csrf_token' value='{$token}'>\n";
            $html = preg_replace('/(<form[^>]*\b(?:method=[\'"]?POST[\'"]?|hx-post|hx-put|hx-delete)\b[^>]*>)/i', '$1' . $csrf_input, $html);
        }

        // Smart DOM Diff: Hitung hash konten komponen
        $hash = md5($html);
        header("X-PHMX-Hash: {$hash}");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

        // Jika konten tidak berubah sejak polling terakhir, kirim 204 No Content (hemat bandwidth & cegah DOM reset)
        $clientHash = $_SERVER['HTTP_X_PHMX_HASH'] ?? '';
        if (!empty($clientHash) && hash_equals($hash, $clientHash)) {
            http_response_code(204);
            exit;
        }

        echo $html;
        exit;
    }
    return "</div>";
}

/**
 * Menutup container Smart Component Polling.
 * 
 * @param string $key Parameter unik penanda komponen (default: '_poll')
 * @return string
 */
function poll_end($key = '_poll') {
    return async_end($key);
}
