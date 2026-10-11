<?php
// function redirectWithMessage($url, $message = '', $type = 'success')
// {
//     $_SESSION['message'] = $message;
//     $_SESSION['message_type'] = $type;
//     echo "<script>
//                 alert('{$message}');
//                 window.location.href = '{$url}';
//               </script>";
//     exit;
// }

// function showAlert($message = null, $type = 'success')
// {
//     if ($message === null) {
//         if (!isset($_SESSION['message'])) {
//             return '';
//         }

//         $message = $_SESSION['message'];
//         $type = $_SESSION['message_type'] ?? 'success';
//         unset($_SESSION['message'], $_SESSION['message_type']);
//     }

//     $alertClass = ($type === 'error') ? 'alert-danger' : 'alert-success';
//     return "<div class='alert {$alertClass}' role='alert'>{$message}</div>";
// }

/**
 * HTMX Khusus: Redirect dengan Hash dan Pesan (Bypass Full Reload)
 * Membersihkan form yang aktif secara otomatis (kecuali field tertentu).
 */
function htmxRedirectWithMessage($hash, $message, $type = 'success', $delay = 1500, $keepFields = [])
{
    $icon = ($type === 'danger') ? 'error' : $type;
    $msgJson = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $iconJson = json_encode($icon);
    $keepJson = json_encode($keepFields);
    $cleanHash = ltrim($hash, '#');
    $cleanHashJson = json_encode($cleanHash);
    
    echo "<script>
            (function(){
                let keep = {$keepJson};
                document.querySelectorAll('form').forEach(f => {
                    Array.from(f.elements).forEach(el => {
                        if (el.name && !keep.includes(el.name) && el.type !== 'hidden') {
                            if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
                            else el.value = '';
                        }
                    });
                });
                
                const Toast = Swal.mixin({
                  toast: true,
                  position: 'bottom-end',
                  showConfirmButton: false,
                  timer: {$delay},
                  timerProgressBar: true,
                  didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                  }
                });
                Toast.fire({
                  icon: {$iconJson},
                  title: {$msgJson}
                }).then(() => {
                    window.location.href = {$cleanHashJson};
                });
            })();
          </script>";
    exit;
}

/**
 * HTMX Khusus: Tampilkan Pesan Saja (Berhenti eksekusi)
 * Membersihkan form yang aktif (kecuali field tertentu).
 */
function htmxMessage($message, $type = 'danger', $keepFields = [])
{
    $icon = ($type === 'danger') ? 'error' : $type;
    $msgJson = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $iconJson = json_encode($icon);
    $keepJson = json_encode($keepFields);
    
    echo "<script>
            (function(){
                let keep = {$keepJson};
                document.querySelectorAll('form').forEach(f => {
                    Array.from(f.elements).forEach(el => {
                        if (el.name && !keep.includes(el.name) && el.type !== 'hidden') {
                            if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
                            else el.value = '';
                        }
                    });
                });
                
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
            })();
          </script>";
    exit;
}

/**
 * HTMX Khusus: Reload Halaman Saat Ini dengan Pesan
 * Berguna untuk mempertahankan parameter URL (seperti ?page=4 atau ?search=xxx) 
 * setelah melakukan action seperti Update atau Delete tanpa me-reset posisi scroll layar.
 */
function htmxReloadWithMessage($message, $type = 'success', $delay = 1500, $target = '#content')
{
    $icon = ($type === 'danger') ? 'error' : $type;
    $msgJson = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $iconJson = json_encode($icon);
    
    echo "<script>
            (function(){
                // Catat posisi scroll saat ini agar layar tidak loncat ke atas
                const currentScrollY = window.scrollY;

                // Tampilkan Toast Notifikasi
                const Toast = Swal.mixin({
                  toast: true,
                  position: 'bottom-end',
                  showConfirmButton: false,
                  timer: {$delay},
                  timerProgressBar: true,
                  didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                  }
                });
                Toast.fire({
                  icon: {$iconJson},
                  title: {$msgJson}
                });

                // Deteksi otomatis apakah di halaman ini ada kontainer data async (#async-wrapper)
                const asyncEl = document.querySelector('#async-wrapper') || document.querySelector('[hx-get*=\"_async=1\"]');
                let reloadUrl = window.location.href;
                let reloadTarget = '{$target}';
                let reloadSwap = 'innerHTML';
                let reloadSelect = (reloadTarget === '#content') ? '#content' : null;

                if (reloadTarget === '#content' && asyncEl) {
                    try {
                        const urlObj = new URL(window.location.href);
                        urlObj.searchParams.set('_async', '1');
                        reloadUrl = urlObj.toString();
                    } catch(e) {
                        const separator = reloadUrl.includes('?') ? '&' : '?';
                        reloadUrl = reloadUrl + separator + '_async=1';
                    }
                    reloadTarget = '#' + (asyncEl.id || 'async-wrapper');
                    reloadSwap = 'innerHTML';
                    reloadSelect = null; // Respons _async murni berisi konten tabel, tidak perlu di-filter dengan select
                }

                const ajaxOptions = {
                    target: reloadTarget,
                    swap: reloadSwap
                };
                if (reloadSelect) {
                    ajaxOptions.select = reloadSelect;
                }

                // Muat ulang data secara asynchronous tanpa merusak layout atau posisi scroll
                htmx.ajax('GET', reloadUrl, ajaxOptions).then(() => {
                    // Pulihkan posisi scroll secara mulus dan presisi
                    window.scrollTo({ top: currentScrollY, behavior: 'instant' });
                    
                    // Pastikan event listener HTMX pada elemen yang baru dimuat tetap aktif (misal tombol Hapus)
                    const targetEl = document.querySelector(reloadTarget);
                    if (targetEl && window.htmx) {
                        htmx.process(targetEl);
                    }

                    // Reset input form di modal
                    document.querySelectorAll('.modal form').forEach(f => f.reset());

                    // Bersihkan sisa backdrop modal jika ada
                    document.querySelectorAll('.modal.show').forEach(m => {
                        const modalInstance = bootstrap.Modal.getInstance(m);
                        if (modalInstance) modalInstance.hide();
                    });
                    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                });
            })();
          </script>";
    exit;
}
