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
    $keepJson = json_encode($keepFields);
    
    echo "<script>
            (function(){
                let keep = {$keepJson};
                document.querySelectorAll('form').forEach(f => {
                    Array.from(f.elements).forEach(el => {
                        if (el.name && !keep.includes(el.name)) {
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
                  icon: '{$icon}',
                  title: '{$message}'
                }).then(() => {
                    htmx.ajax('GET', '{$hash}', {target: 'body'});
                    window.history.pushState(null, '', '{$hash}');
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
    $keepJson = json_encode($keepFields);
    
    echo "<script>
            (function(){
                let keep = {$keepJson};
                document.querySelectorAll('form').forEach(f => {
                    Array.from(f.elements).forEach(el => {
                        if (el.name && !keep.includes(el.name)) {
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
                  icon: '{$icon}',
                  title: '{$message}'
                });
            })();
          </script>";
    exit;
}
