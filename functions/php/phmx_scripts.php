<?php
/**
 * Helper versi aset statis cerdas (Smart Asset Versioning)
 * Menggunakan filemtime() agar browser melakukan caching maksimal,
 * dan hanya mengunduh ulang aset ketika isi file benar-benar diperbarui di server.
 * 
 * @param string $path Path relatif aset dari root proyek (misal: 'assets/css/bootstrap.min.css')
 * @return string URL aset lengkap dengan query string versi (misal: 'assets/css/bootstrap.min.css?v=1712839201')
 */
function asset($path) {
    $cleanPath = ltrim($path, '/\\');
    $realPath = dirname(__DIR__, 2) . '/' . $cleanPath;
    
    if (file_exists($realPath)) {
        $mtime = filemtime($realPath);
        return $path . (strpos($path, '?') !== false ? '&' : '?') . 'v=' . $mtime;
    }
    
    return $path;
}

/**
 * 3. Fungsi untuk memuat semua Script JS Inti PHMX
 * Panggil fungsi ini tepat sebelum tag penutup </body> di index.php
 */
function load_phmx_scripts() {
    $v = filemtime(__FILE__) ?: time(); // Gunakan modifikasi waktu file alih-alih time() setiap request
    
    // Skrip routing utama dihapus karena digantikan HTML5 History API (HTMX)
    // Jika Anda punya skrip JS tambahan di masa depan, tambahkan di sini:
    // echo "<script src=\"functions/js/utilities.js?v={$v}\"></script>\n";
    // Injeksi otomatis CSRF Token ke setiap HTMX Request (termasuk button, anchor, dll)
    echo "<script>
        if (!window._phmx_core_initialized) {
            window._phmx_core_initialized = true;

            // Injeksi otomatis CSRF Token ke setiap HTMX Request
            document.addEventListener('htmx:configRequest', function(evt) {
                var csrfToken = document.querySelector('meta[name=\"csrf-token\"]');
                if (csrfToken) {
                    evt.detail.headers['X-CSRF-Token'] = csrfToken.content;
                }
            });

            // Set untuk melacak request XHR asinkronus (latar belakang) yang aktif
            const activeAsyncXhrs = new Set();

            if (typeof NProgress !== 'undefined') {
                NProgress.configure({ showSpinner: false });
            }

            // Top Progress Bar (NProgress) & Instant Async Abort Saat Navigasi
            document.addEventListener('htmx:beforeRequest', function(evt) {
                const isBoostedNav = evt.detail && evt.detail.boosted;
                const targetEl = evt.target;
                const requestPath = (evt.detail && evt.detail.pathInfo && evt.detail.pathInfo.requestPath) || 
                                    (evt.detail && evt.detail.requestConfig && evt.detail.requestConfig.path) || '';
                const isAsync = (targetEl && targetEl.id === 'async-wrapper') || 
                                (targetEl && targetEl.getAttribute && targetEl.getAttribute('hx-get') && targetEl.getAttribute('hx-get').includes('_async=')) ||
                                requestPath.includes('_async=');

                if (isBoostedNav) {
                    // Jika pengguna berpindah ke halaman baru (klik link/navbar),
                    // batalkan SEMUA pemuatan data async latar belakang seketika agar koneksi & thread langsung bebas!
                    activeAsyncXhrs.forEach(function(xhr) {
                        try { xhr.abort(); } catch(e) {}
                    });
                    activeAsyncXhrs.clear();

                    if (typeof NProgress !== 'undefined') {
                        NProgress.start();
                    }
                } else if (isAsync && evt.detail && evt.detail.xhr) {
                    // Catat XHR async latar belakang yang aktif (tanpa menyalakan NProgress)
                    activeAsyncXhrs.add(evt.detail.xhr);
                }
            });

            // Selesaikan NProgress saat konten halaman baru berhasil di-swap ke dalam DOM
            document.addEventListener('htmx:afterSwap', function(evt) {
                if (evt.detail && evt.detail.boosted) {
                    if (typeof NProgress !== 'undefined') {
                        NProgress.done();
                    }
                }
            });

            document.addEventListener('htmx:afterRequest', function(evt) {
                if (evt.detail && evt.detail.xhr) {
                    activeAsyncXhrs.delete(evt.detail.xhr);
                }
                if (evt.detail && evt.detail.boosted) {
                    if (typeof NProgress !== 'undefined') {
                        NProgress.done();
                    }
                }
            });

            document.addEventListener('htmx:sendError', function(evt) {
                if (typeof NProgress !== 'undefined') {
                    NProgress.done();
                }
            });

            document.addEventListener('htmx:sendAbort', function(evt) {
                if (evt.detail && evt.detail.xhr) {
                    activeAsyncXhrs.delete(evt.detail.xhr);
                }
                if (typeof NProgress !== 'undefined') {
                    NProgress.done();
                }
            });

            document.addEventListener('htmx:historyRestore', function(evt) {
                if (typeof NProgress !== 'undefined') {
                    NProgress.done();
                }
            });
        }
    </script>\n";

    // Auto-tampilkan pesan flash sesi jika ada (misal redirect dari middleware auth)
    if (!empty($_SESSION['flash_message'])) {
        $msgJson = json_encode($_SESSION['flash_message'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $iconJson = json_encode($_SESSION['flash_type'] ?? 'info');
        echo "<script>
            (function(){
                const showToast = function() {
                    if (typeof Swal !== 'undefined') {
                        const Toast = Swal.mixin({
                          toast: true,
                          position: 'bottom-end',
                          showConfirmButton: false,
                          timer: 3000,
                          timerProgressBar: true
                        });
                        Toast.fire({
                          icon: {$iconJson},
                          title: {$msgJson}
                        });
                    }
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showToast);
                } else {
                    showToast();
                }
            })();
        </script>\n";
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}
?>
