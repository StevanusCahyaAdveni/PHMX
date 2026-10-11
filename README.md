# PHMX Framework Documentation

<p align="center">
  <img src="assets/images/logo/phmx-logo.png" alt="PHMX Framework Logo" width="280">
</p>

**PHMX** adalah kerangka kerja (*framework*) PHP modern dan ultra-ringan yang dirancang untuk membangun **Single Page Application (SPA)** secepat kilat tanpa framework JavaScript berat, sekaligus berfungsi sebagai **REST API Gateway Murni** siap pakai untuk klien Mobile (Android/iOS) dan dilengkapi kapabilitas **PWA & VAPID Web Push Notifications**.

---

## Keunggulan Utama

1. **SPA Tanpa Reload (Single Page Application)**: Navigasi antar halaman menggunakan *Slash URL / Auto-Root* via HTML5 History API dan `hx-boost="true"` dari HTMX.
2. **Async Deferred Loading (Transisi 0ms / Non-Blocking)**: Render shell UI instan dengan kontainer lazy-load (`async_start()` & `async_end()`) yang memuat data berat di latar belakang tanpa mengunci sesi PHP (`session_write_close()`) dan mendukung pembatalan request otomatis (*auto-abort*) saat pengguna berpindah halaman.
3. **Database Transaction Control Language (TCL - ACID)**: Wrapper transaksi terpadu `transactionSecure($con, $callback)` (mendukung closure atau batch array query) dengan auto-commit & auto-rollback serta fungsi manual (`beginTransaction()`, `commitTransaction()`, `rollbackTransaction()`) demi menjamin integritas dan konsistensi data.
4. **PWA & Web Push Notification Bawaan (Batching & Background Queue)**:
   - Dilengkapi *Service Worker* dan *Manifest* siap pakai (dapat diaktifkan/dinonaktifkan via `$pwa_enabled` di `config.php`).
   - Mendukung pengiriman **Web Push Notifications VAPID murni (RFC 8292 / RFC 8291)** langsung dari PHP tanpa ketergantungan *vendor* pihak ketiga.
   - Pengiriman paralel berkecepatan tinggi via `sendWebPushBatch()` (`curl_multi_exec`) dengan caching JWT token.
   - Sistem antrean database asinkronus (`queueWebPush()`) yang diproses via worker CLI `php phmx push:work`.
   - Perintah CLI `php phmx vapid:generate` untuk pembuatan dan injeksi kunci otomatis ke `config.php`.
5. **REST API Gateway Murni (`/api/`)**: Arsitektur API mandiri dengan standarisasi respons JSON `response($success, $message, $data)`, penanganan CORS, dan pemisahan logika di `api/methods/`.
6. **Autentikasi Ganda (Session & Bearer Token)**:
   - **Web Client**: Autentikasi berbasis session dengan fitur *Remember Me (Silent Login)* via `localStorage` yang otomatis memperbarui sesi tanpa mengganggu navigasi pengguna.
   - **Mobile Client (Android/iOS)**: Autentikasi modern berbasis **Bearer Token** (`Authorization: Bearer <token>`) yang aman dan *stateless*.
7. **Rate Limiting / Throttle Middleware**: Proteksi dari serangan *brute force* dan spam request menggunakan middleware terpusat (`throttle:max_attempts,decay_minutes`).
8. **Auto-Routing & Middleware Terpusat**: Pengaturan rute halaman, aksi web, dan REST API dikelola di satu tempat (`middleware/routes.php`) menggunakan pola wildcard `fnmatch()`.
9. **Smart Asset Versioning (`asset()`)**: Cache busting otomatis berbasis `filemtime()` sehingga browser langsung mengunduh file baru hanya jika isi CSS/JS di server berubah.
10. **Keamanan Bawaan Berlapis (Enterprise Defaults)**:
    - Proteksi otomatis SQL Injection (`querySecure()`, `executeSecure()`, & `transactionSecure()`).
    - Proteksi XSS otomatis (`sani()`) dan escaping ketat `json_encode` di tag `<script>`.
    - Proteksi CSRF otomatis (*Zero-Boilerplate Auto-Injection*).
    - Whitelist ketat upload file & pencegahan eksekusi skrip di folder `uploads/`.
    - Hardening cookie sesi (`HttpOnly`, `SameSite=Lax`, `Secure`).
    - Anti-SSRF pada Web Push dengan verifikasi endpoint dan HTTPS mandatory.
    - Otorisasi folder internal Apache 2.4 (`Require all denied`) dan blokir akses langsung Nginx.
11. **CRUD & API Generator (Web & CLI)**: Pembuatan modul CRUD web asinkronus, API JSON, dan file migrasi database secara otomatis dalam hitungan detik.
12. **Magic Pagination & Multi-Column Search**: Paginasi dan form pencarian multi-kolom hanya dengan 1 baris kode.

---

## Instalasi & Memulai Proyek

1. Letakkan folder proyek di web server lokal Anda (misal: `d:/xampp/htdocs/PersonalProject/PHMX`).
2. Buka file **`config.php`** dan sesuaikan konfigurasi database:
   ```php
   $host = 'localhost';
   $user = 'root';
   $pass = ''; 
   $db   = 'phmx-framework'; // Sesuaikan nama database Anda
   ```
3. Buka Terminal / CMD di root folder proyek, lalu jalankan migrasi database awal:
   ```bash
   php phmx migrate
   ```
   *(Perintah ini akan membuat tabel `migrations`, `users`, dan `push_subscriptions` di database secara otomatis).*

4. Generate pasangan kunci VAPID untuk Web Push Notifications:
   ```bash
   php phmx vapid:generate
   ```
   *(Kunci Public dan Private akan otomatis terbuat dan tersimpan di `config.php`).*

---

## Arsitektur Tiga Lapisan

PHMX memisahkan kode menjadi 3 lapisan yang terisolasi:

```
├── pages/         -> Tampilan UI (HTML) & query SELECT
├── actions/       -> Pemrosesan form Web/HTMX (POST/PUT/DELETE)
├── api/
│   ├── index.php  -> API Gateway (Header, CORS, CSRF, & Middleware Runner)
│   └── methods/   -> Logika REST API (Output JSON via response())
├── middleware/    -> Layer keamanan (auth, api_auth, throttle, role)
├── database/      -> File migrasi SQL terstruktur
└── assets/        -> File statis (CSS, JS, Images, Logo)
```

---

## 1. Navigasi SPA & Pemrosesan Data Web (HTMX)

### Navigasi Halaman (Pages)
Semua link `<a>` otomatis diproses menjadi request SPA tanpa reload halaman berkat atribut `hx-boost="true"` di `index.php`:
```html
<a href="users/user-management">Manajemen Pengguna</a>
```

### Pemrosesan Data (Actions)
Form web mengirim request ke action menggunakan `hx-post="?act=..."`:
```html
<!-- pages/users/add.php -->
<form hx-post="?act=users/save" hx-target="#alert-box" hx-swap="innerHTML">
    <input type="text" name="username" class="form-control" placeholder="Username">
    <button type="submit" class="btn btn-primary">Simpan</button>
</form>
<div id="alert-box"></div>
```

```php
// actions/users/save.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sani($_POST['username'] ?? '');
    
    $success = executeSecure($con, "INSERT INTO users (id, username) VALUES (?, ?)", [generate_uuid(), $username], 'ss');
    if ($success) {
        htmxReloadWithMessage('Data pengguna berhasil disimpan!', 'success');
    } else {
        htmxMessage('Gagal menyimpan data.', 'danger');
    }
}
```

---

## 2. PWA & VAPID Web Push Notifications

### Saklar On/Off PWA di `config.php`
```php
$pwa_enabled = true; // Set false untuk mematikan PWA & auto-unregister Service Worker
```

### Cara Kerja Web Push
1. **Pendaftaran Perangkat**: Client browser meminta izin push melalui `assets/js/pwa.js` dan mendaftarkan endpoint ke `/api/push/subscribe`.
2. **Pengiriman dari PHP Backend**:
```php
$devices = querySecure($con, "SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?", [$target_user_id], 's');

$payload = [
    'title' => 'Pesan Baru',
    'body'  => 'Anda menerima pesan baru.',
    'icon'  => 'assets/images/logo/phmx-mark.svg',
    'data'  => ['url' => 'chat/room']
];

while ($device = mysqli_fetch_assoc($devices)) {
    sendWebPush($device, $payload);
}
```

---

## 3. REST API Gateway & Mobile Client (Bearer Token)

Semua permintaan API diakses melalui rute `/api/{endpoint}`.

### Endpoint Login Mobile
Klien mobile mengirim `email`/`username` dan `password` (mendukung JSON body maupun form-data):
```bash
POST /api/auth/mobile_login
Content-Type: application/json

{
  "email": "admin@gmail.com",
  "password": "password123"
}
```

**Respons Sukses (HTTP 200):**
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "token": "9c27ece2bc88aebb857a853dd80748899b1be387c2e13f36ca3df96e619fc8f7",
    "user": {
      "id": "975b6fcd-4b7c-4244-8a72-595ae836b378",
      "fullname": "Stevanus Cahya Adveni",
      "username": "Stevanus",
      "email": "admin@gmail.com",
      "role": "admin"
    }
  }
}
```

### Mengakses Endpoint API Terproteksi
Sertakan Bearer Token pada HTTP Header:
```bash
GET /api/users/user-management
Authorization: Bearer 9c27ece2bc88aebb857a853dd80748899b1be387c2e13f36ca3df96e619fc8f7
```

### Logout Mobile
```bash
POST /api/auth/mobile_logout
Authorization: Bearer 9c27ece2bc88aebb857a853dd80748899b1be387c2e13f36ca3df96e619fc8f7
```

---

## 4. Konfigurasi Rute & Middleware Terpusat (`middleware/routes.php`)

Daftarkan proteksi rute Web maupun API di dalam file `middleware/routes.php`:

```php
<?php
return [
    // Rute Web (Memerlukan login session di browser)
    'users/user-management'   => ['auth'],
    'auth/login'              => ['throttle:5,1'], // Maks 5 percobaan per 1 menit

    // Rute API (Rate limited, Token & Push)
    'api/auth/mobile_login'   => ['throttle:10,1'],
    'api/auth/silent_login'   => ['throttle:10,1'],
    'api/auth/mobile_logout'  => ['api_auth'],
    'api/users/*'             => ['api_auth'],
    'api/push/subscribe'      => ['throttle:30,1'],
    'api/push/send_test'      => ['throttle:10,1'],
];
?>
```

---

## 5. Remember Me (Silent Login) via localStorage

Fitur Remember Me tersimpan di `localStorage` peramban client:
1. Saat user mencentang *Remember Me* dan berhasil login, event `login-success` dipicu dan kredensial tersimpan di `localStorage`.
2. Skrip client [`assets/js/auth.js`](file:///d:/xampp/htdocs/PersonalProject/PHMX/assets/js/auth.js) mendeteksi saat session PHP kedaluwarsa ketika pengguna me-refresh halaman.
3. Skrip secara otomatis menembak `api/methods/auth/silent_login.php` di latar belakang untuk memperpanjang sesi tanpa perlu *redirect* atau mengganggu halaman yang sedang dibuka pengguna.
4. Saat user menekan tombol Logout, event `logout-success` otomatis membersihkan `localStorage`.

---

## 6. Daftar Fungsi Helper Bawaan

| Fungsi | Kegunaan | Contoh Pemakaian |
|---|---|---|
| `sani($data)` | Sanitasi string/array dari XSS | `$nama = sani($_POST['nama']);` |
| `querySecure($con, $sql, $params, $types)` | Eksekusi SELECT prepared statement | `$users = querySecure($con, "SELECT * FROM users WHERE role=?", ['admin'], 's');` |
| `executeSecure($con, $sql, $params, $types)` | Eksekusi INSERT/UPDATE/DELETE | `executeSecure($con, "DELETE FROM users WHERE id=?", [$id], 's');` |
| `transactionSecure($con, $action)` | Transaksi TCL (ACID) otomatis (closure/batch) | `transactionSecure($con, function($c) { ... });` |
| `beginTransaction($con)` / `commitTransaction($con)` / `rollbackTransaction($con)` | Kontrol transaksi database manual | `beginTransaction($con); ... commitTransaction($con);` |
| `getTransactionError()` | Ambil pesan error transaksi terakhir | `$err = getTransactionError();` |
| `asset($path)` | Smart asset versioning via `filemtime()` | `<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">` |
| `uploadFile($file, $targetDir, $maxSize, $types)` | Upload file aman dengan kompresi & whitelist | `$path = uploadFile($_FILES['avatar'], 'uploads/avatars/');` |
| `sendWebPush($sub, $payload, $config)` | Kirim notifikasi Web Push VAPID tunggal | `sendWebPush($device, ['title' => 'Halo!']);` |
| `sendWebPushBatch($subs, $payload, $config)` | Kirim Web Push paralel (`curl_multi_exec`) | `sendWebPushBatch($devices, ['title' => 'Pengumuman!']);` |
| `queueWebPush($con, $sub, $payload)` | Masukkan notifikasi ke antrean database | `queueWebPush($con, $device, ['title' => 'Halo']);` |
| `processWebPushQueue($con, $batchSize, $maxAttempts)` | Proses antrean Web Push di latar belakang | `$stats = processWebPushQueue($con, 50);` |
| `generateVapidKeys()` | Generate pasangan kunci VAPID P-256 | `$keys = generateVapidKeys();` |
| `response($success, $msg, $data)` | Standarisasi output JSON REST API | `response(true, 'Berhasil', $data);` |
| `generate_uuid()` | Generate UUID v4 36-karakter | `$id = generate_uuid();` |
| `htmxReloadWithMessage($msg, $type, $delay, $target)` | Reload asinkronus (pertahankan scroll posisi) | `htmxReloadWithMessage('Data berhasil disimpan!', 'success');` |
| `htmxRedirectWithMessage($url, $msg, $type, $delay, $keepFields, $target)` | Redirect halaman SPA dengan pesan | `htmxRedirectWithMessage('dashboard', 'Selamat datang!', 'success');` |
| `htmxMessage($msg, $type)` | Tampilkan alert tanpa refresh | `htmxMessage('Form belum lengkap', 'danger');` |
| `paginationQuery($con, $sql, $params, $types, $limit, $baseUrl)` | Paginasi data otomatis | `$paginated = paginationQuery($con, "SELECT * FROM products", [], "", 10, 'products');` |
| `generateSearchForm($inputs, $button)` | Pembuat form pencarian dinamis | `generateSearchForm([['name' => 'search_nama', 'placeholder' => 'Cari...']]);` |
| `async_start($text, $id, $key)` | Pembuka kontainer lazy-load (0ms shell) | `<?= async_start('Memuat data tabel...') ?>` |
| `is_async($key)` | Cek apakah request async deferred data | `if (is_async()) { ... }` |
| `async_end($key)` | Penutup kontainer lazy-load & auto exit | `<?= async_end() ?>` |

---

## 7. CRUD & REST API Generator

### Web Generator (`/generate-crud`)
1. Buka halaman `/generate-crud` di browser Anda.
2. Masukkan nama folder, nama file, nama tabel, dan konfigurasi kolom.
3. Centang opsi **"Generate REST API Endpoint (JSON)"** jika ingin membuat endpoint API secara bersamaan.
4. Klik **Generate Module**. Sistem akan membuatkan file `pages/` (dengan lazy-load asinkronus), `actions/`, `api/methods/`, dan migrasi SQL `database/` secara otomatis.

### CLI Runner (PHMX Artisan)
```bash
# Menampilkan bantuan CLI
php phmx

# Membuat pasangan kunci VAPID Web Push
php phmx vapid:generate

# Membuat kerangka kosong modul CRUD
php phmx make:crud products/manage

# Menjalankan migrasi SQL database
php phmx migrate

# Menjalankan worker antrean Web Push (opsional loop daemon)
php phmx push:work 50 --loop

# Mengisi 100 data dummy pengguna (seeder)
php phmx seed:users 100
```

---

## 8. Logo & Desain Identitas

Logo resmi PHMX Framework tersimpan di direktori [`assets/images/logo/`](file:///d:/xampp/htdocs/PersonalProject/PHMX/assets/images/logo):
- `phmx-logo.png` & `phmx-logo.jpg`: Logo resolusi tinggi dengan emblem origami geometris.
- `phmx-logo.svg`: Vektor SVG transparan untuk tema terang (*light mode*).
- `phmx-logo-white.svg`: Vektor SVG transparan untuk tema gelap (*dark mode/navbar*).
- `phmx-mark.svg`: Emblem ikon vektor untuk favicon dan mobile icon.

---

## 9. Panduan Deployment Server (Apache vs Nginx / aaPanel)

PHMX Framework mendukung berjalan murni di **Apache** maupun murni di **Nginx (aaPanel Ubuntu)** tanpa perlu kombo ganda (Apache + Nginx):

### A. Jika Menggunakan Apache Standalone
Framework sudah menyertakan file `.htaccess` di root, `api/`, `pages/`, `actions/`, `tmp/`, dan `uploads/`. Anda cukup memastikan modul `mod_rewrite` dan `mod_headers` aktif:
```bash
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

### B. Jika Menggunakan Nginx Standalone di aaPanel (Ubuntu)
1. Buat website baru di aaPanel dengan stack **Nginx** (PHP 8.1 / 8.2).
2. Masuk ke **aaPanel -> Website -> Klik Nama Domain Anda -> Tab "URL Rewrite"**.
3. Salin dan tempel aturan berikut, lalu klik **Save**:
```nginx
# 1. REST API Gateway Routing
location /api/ {
    try_files $uri $uri/ @phmx_api;
}
location @phmx_api {
    rewrite ^/api/(.*)$ /api/index.php?endpoint=$1&$query_string last;
}

# 2. Web SPA & HTMX Routing
location / {
    try_files $uri $uri/ @phmx_web;
}
location @phmx_web {
    rewrite ^/(.*)$ /index.php?hal=$1&$query_string last;
}

# 3. Proteksi Folder Internal
location ~* ^/(pages|actions|tmp|database|\.agents|\.git) {
    deny all;
    return 403;
}

# 4. Larang Eksekusi Skrip di uploads/
location ~* ^/uploads/.*\.(php|phtml|phar|sh|pl|cgi)$ {
    deny all;
    return 403;
}
```
Contoh konfigurasi lengkap virtual host standalone tersedia di file [`nginx.conf.example`](file:///d:/xampp/htdocs/PersonalProject/PHMX/nginx.conf.example).

