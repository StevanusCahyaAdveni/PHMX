<?php
require_once __DIR__ . '/validation.php';

/**
 * Sanitasi input XSS, plus validasi rules opsional dalam satu baris.
 *
 * Tanpa rules (backward compatible, return string bersih):
 *   $nama = sani($_POST['nama'] ?? '');
 *
 * Dengan rules (return string bersih jika valid):
 *   $nama = sani($_POST['nama'] ?? '', 'required|minChar:8', 'Nama', 'nama');
 *
 * Jika validasi gagal: otomatis htmxMessage() (toast SweetAlert + bersihkan
 * form kecuali field yang invalid) lalu stop eksekusi (exit).
 *
 * @param mixed       $data      Nilai input mentah
 * @param string|null $rules     Rules dipisah pipe: 'required|email|unique:users,email'
 * @param string|null $label     Label ramah-pengguna untuk pesan error (default: fieldName)
 * @param string|null $fieldName Nama field untuk keepFields htmxMessage (default: label)
 * @return mixed                 Nilai yang sudah disanitasi
 */
function sani($data, $rules = null, $label = null, $fieldName = null)
{
    if (is_array($data)) {
        $data = array_map('sani', $data);
    } elseif (is_string($data)) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8', false);
    }

    // Validasi rules (jika disediakan) — auto htmxMessage + exit saat gagal
    if ($rules !== null && $rules !== '') {
        $errors = validateRules($data, $rules, $label, $fieldName);
        if ($errors) {
            htmxMessage(implode(' ', $errors), 'danger', [], [$fieldName ?: $label]);
        }
    }

    return $data;
}
