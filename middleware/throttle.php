<?php
/**
 * Throttle Middleware (Rate Limiting)
 * 
 * Melindungi route dari serangan Brute-Force menggunakan file JSON.
 * Parameter: throttle:max_attempts,minutes (default: 5,10)
 */

if (!isset($mw_param)) {
    $mw_param = '5,1'; // Default: 5 percobaan dalam 10 menit
}

// Hanya terapkan throttle pada request POST (Action Submit)
// Membuka halaman biasa (GET) tidak akan dihitung sebagai percobaan.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$params = explode(',', $mw_param);
$max_attempts = (int)($params[0] ?? 5);
$timeout_minutes = (int)($params[1] ?? 10);
$timeout_seconds = $timeout_minutes * 60;

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
$throttle_file = __DIR__ . '/../tmp/throttle.txt';

// Pastikan folder tmp ada
if (!is_dir(dirname($throttle_file))) {
    mkdir(dirname($throttle_file), 0777, true);
}

// Gunakan fopen dan flock untuk mencegah race condition
$fp = fopen($throttle_file, 'c+');

if (flock($fp, LOCK_EX)) {
    // Cek umur file (Self-Cleaning)
    $file_age = time() - filemtime($throttle_file);
    if ($file_age > $timeout_seconds) {
        ftruncate($fp, 0); // Bersihkan file jika sudah kadaluwarsa
    }

    rewind($fp);
    $filesize = filesize($throttle_file);
    $content = $filesize > 0 ? fread($fp, $filesize) : '';
    
    $data = json_decode($content, true);
    if (!is_array($data)) {
        $data = [];
    }

    // Ambil data IP saat ini
    $attempts = $data[$ip_address] ?? 0;
    
    if ($attempts >= $max_attempts) {
        // Lepaskan lock dan tolak request
        flock($fp, LOCK_UN);
        fclose($fp);
        
        // Panggil htmxMessage jika tersedia, jika tidak echo biasa
        if (function_exists('htmxMessage')) {
            htmxMessage("Terlalu banyak percobaan! Silakan coba lagi setelah {$timeout_minutes} menit.", "danger");
        } else {
            echo "<div class='alert alert-danger'>Terlalu banyak percobaan! Silakan coba lagi setelah {$timeout_minutes} menit.</div>";
            exit;
        }
    }

    // Tambah percobaan
    $data[$ip_address] = $attempts + 1;

    // Simpan kembali
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    
    flock($fp, LOCK_UN);
}
fclose($fp);
?>
