<?php
/**
 * PHMX Validation Engine
 * Mesin validasi rule-based untuk fungsi sani() dan saniFields().
 *
 * Daftar rules yang didukung:
 *   required          : Wajib diisi (tidak boleh kosong setelah trim)
 *   minChar:n         : Minimal n karakter
 *   maxChar:n         : Maksimal n karakter
 *   min:n             : Nilai angka minimal n
 *   max:n             : Nilai angka maksimal n
 *   email             : Format email valid
 *   numeric           : Hanya angka (desimal diperbolehkan)
 *   int               : Hanya bilangan bulat
 *   alpha             : Hanya huruf
 *   alphaNum          : Hanya huruf dan angka
 *   url               : Format URL valid
 *   phone             : Format nomor telepon (angka, spasi, +, -, ())
 *   regex:/pattern/   : Cocok dengan pola regex custom (delimiter / wajib)
 *   in:a,b,c          : Nilai harus salah satu dari daftar
 *   confirmed         : Harus sama dengan field <nama>_confirmation
 *   unique:table,col  : Nilai belum ada di tabel database (butuh $con global)
 */

/**
 * Validasi satu nilai terhadap daftar rules.
 *
 * @param mixed  $value     Nilai yang sudah disanitasi
 * @param string $rules     Rules dipisah pipe, contoh: 'required|minChar:8'
 * @param string $label     Nama ramah-pengguna untuk pesan error (default: nama field)
 * @param string $fieldName Nama field untuk keepFields htmxMessage (default: label)
 * @return array            Daftar pesan error (kosong = valid)
 */
function validateRules($value, $rules, $label = null, $fieldName = null)
{
    $label = $label ?: ($fieldName ?: 'Field');
    $fieldName = $fieldName ?: $label;
    $errors = [];
    $valueStr = is_scalar($value) ? (string) $value : '';
    $trimmed = trim($valueStr);

    foreach (explode('|', $rules) as $rule) {
        $rule = trim($rule);
        if ($rule === '') continue;

        // Pisahkan nama rule dan parameternya (contoh: minChar:8)
        $param = null;
        if (strpos($rule, ':') !== false) {
            [$rule, $param] = explode(':', $rule, 2);
        }

        // Rule "required" — boleh lanjut cek rules lain hanya jika terisi
        if ($rule === 'required') {
            if ($trimmed === '') {
                $errors[] = "{$label} wajib diisi.";
            }
            continue;
        }

        // Rules selain required di-skip jika nilai kosong (kecuali required gagal sudah dicatat)
        if ($trimmed === '') continue;

        switch ($rule) {
            case 'minChar':
                if (mb_strlen($trimmed) < (int) $param) {
                    $errors[] = "{$label} minimal {$param} karakter.";
                }
                break;

            case 'maxChar':
                if (mb_strlen($trimmed) > (int) $param) {
                    $errors[] = "{$label} maksimal {$param} karakter.";
                }
                break;

            case 'min':
                if (!is_numeric($trimmed) || (float) $trimmed < (float) $param) {
                    $errors[] = "{$label} minimal bernilai {$param}.";
                }
                break;

            case 'max':
                if (!is_numeric($trimmed) || (float) $trimmed > (float) $param) {
                    $errors[] = "{$label} maksimal bernilai {$param}.";
                }
                break;

            case 'email':
                if (!filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Format {$label} tidak valid.";
                }
                break;

            case 'numeric':
                if (!is_numeric($trimmed)) {
                    $errors[] = "{$label} harus berupa angka.";
                }
                break;

            case 'int':
                if (filter_var($trimmed, FILTER_VALIDATE_INT) === false) {
                    $errors[] = "{$label} harus berupa bilangan bulat.";
                }
                break;

            case 'alpha':
                if (!preg_match('/^[a-zA-Z\s]+$/u', $trimmed)) {
                    $errors[] = "{$label} hanya boleh berisi huruf.";
                }
                break;

            case 'alphaNum':
                if (!preg_match('/^[a-zA-Z0-9]+$/u', $trimmed)) {
                    $errors[] = "{$label} hanya boleh berisi huruf dan angka.";
                }
                break;

            case 'url':
                if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
                    $errors[] = "Format {$label} tidak valid (harus berupa URL).";
                }
                break;

            case 'phone':
                if (!preg_match('/^[0-9\s\+\-\(\)]+$/', $trimmed)) {
                    $errors[] = "Format {$label} tidak valid (contoh: +62 812-3456-7890).";
                }
                break;

            case 'regex':
                // Param wajib diawali & diakhiri delimiter, contoh: regex:/^[A-Z]+$/i
                if (@preg_match($param, '') === false) {
                    $errors[] = "Pattern regex untuk {$label} tidak valid.";
                } elseif (!preg_match($param, $trimmed)) {
                    $errors[] = "Format {$label} tidak sesuai ketentuan.";
                }
                break;

            case 'in':
                $allowed = array_map('trim', explode(',', (string) $param));
                if (!in_array($trimmed, $allowed, true)) {
                    $errors[] = "{$label} harus salah satu dari: " . implode(', ', $allowed) . ".";
                }
                break;

            case 'confirmed':
                $confirmation = $_POST[$fieldName . '_confirmation'] ?? '';
                if (!is_scalar($confirmation) || trim((string) $confirmation) !== $trimmed) {
                    $errors[] = "Konfirmasi {$label} tidak cocok.";
                }
                break;

            case 'unique':
                // Format: unique:tabel,kolom (kecuali nilai sendiri: unique:tabel,kolom,id_abaikan)
                global $con;
                $parts = array_map('trim', explode(',', (string) $param));
                if (count($parts) < 2 || !isset($con)) {
                    $errors[] = "Rule unique untuk {$label} salah konfigurasi.";
                    break;
                }
                [$table, $column] = $parts;
                $ignoreId = $parts[2] ?? null;
                // Whitelist nama tabel & kolom (hanya identifier alfanumerik + underscore) untuk cegah SQL injection
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                    $errors[] = "Rule unique untuk {$label} salah konfigurasi.";
                    break;
                }
                $sql = "SELECT `{$column}` FROM `{$table}` WHERE `{$column}` = ?";
                $types = 's';
                $params = [$trimmed];
                if ($ignoreId !== null) {
                    $sql .= " AND id != ?";
                    $types .= 's';
                    $params[] = $ignoreId;
                }
                $sql .= " LIMIT 1";
                $result = querySecure($con, $sql, $params, $types);
                if ($result && mysqli_num_rows($result) > 0) {
                    $errors[] = "{$label} sudah terdaftar / sudah digunakan.";
                }
                break;

            default:
                $errors[] = "Rule '{$rule}' pada {$label} tidak dikenal.";
        }
    }

    return $errors;
}

/**
 * Validasi batch seluruh field form sekaligus (sanitasi + validasi).
 * Jika ada error: otomatis memanggil htmxMessage() dengan gabungan pesan
 * dan keepFields berisi semua field yang invalid, lalu stop eksekusi (exit).
 *
 * Contoh:
 *   $data = saniFields($_POST, [
 *       'fullname' => 'required|minChar:3',
 *       'email'    => 'required|email|unique:users,email',
 *       'password' => 'required|minChar:8|confirmed',
 *   ]);
 *   // $data['fullname'], $data['email'], ... sudah bersih & valid
 *
 * @param array $input        Sumber data (umumnya $_POST)
 * @param array $definitions  Map field => rules
 * @param array $labels       (Opsional) Map field => label ramah-pengguna
 * @return array              Map field => nilai bersih yang lolos validasi
 */
function saniFields($input, $definitions, $labels = [])
{
    $clean = [];
    $allErrors = [];
    $invalidFields = [];

    foreach ($definitions as $field => $rules) {
        if ($rules === null || $rules === '') {
            // Field tanpa rules: cukup disanitasi
            $clean[$field] = sani($input[$field] ?? '');
            continue;
        }
        $label = $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
        $value = sani($input[$field] ?? '');
        $errors = validateRules($value, $rules, $label, $field);
        if ($errors) {
            $allErrors = array_merge($allErrors, $errors);
            $invalidFields[] = $field;
        }
        $clean[$field] = $value;
    }

    if ($allErrors) {
        // Kirim sebagai daftar agar toast menampilkan tiap error secara per-baris
        htmxMessage($allErrors, 'danger', [], $invalidFields);
    }

    return $clean;
}
