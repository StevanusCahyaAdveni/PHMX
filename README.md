# PHMX Framework Documentation

<p align="center">
  <img src="assets/images/logo/phmx-logo.png" alt="PHMX Framework Logo" width="280">
</p>

**PHMX** adalah kerangka kerja (*framework*) PHP modern dan ultra-ringan yang dirancang untuk membangun **Single Page Application (SPA)** secepat kilat tanpa framework JavaScript berat, sekaligus berfungsi sebagai **REST API Gateway Murni** siap pakai untuk klien Mobile (Android/iOS) dan aplikasi pihak ketiga.

---

## Keunggulan Utama

1. **SPA Tanpa Reload (Single Page Application)**: Navigasi antar halaman menggunakan *Slash URL / Auto-Root* via HTML5 History API dan `hx-boost="true"` dari HTMX.
2. **REST API Gateway Murni (`/api/`)**: Arsitektur API mandiri dengan standarisasi respons JSON `response($success, $message, $data)`, penanganan CORS, dan pemisahan logika di `api/methods/`.
3. **Autentikasi Ganda (Session & Bearer Token)**:
   - **Web Client**: Autentikasi berbasis session dengan fitur *Remember Me (Silent Login)* via `localStorage` yang otomatis memperbarui sesi tanpa mengganggu navigasi pengguna.
   - **Mobile Client (Android/iOS)**: Autentikasi modern berbasis **Bearer Token** (`Authorization: Bearer <token>`) yang aman dan *stateless*.
4. **Rate Limiting / Throttle Middleware**: Proteksi dari serangan *brute force* dan spam request menggunakan middleware terpusat (`throttle:max_attempts,decay_minutes`).
5. **Auto-Routing & Middleware Terpusat**: Pengaturan rute halaman, aksi web, dan REST API dikelola di satu tempat (`middleware/routes.php`) menggunakan pola wildcard `fnmatch()`.
6. **Keamanan Bawaan**:
   - Proteksi otomatis dari SQL Injection (`querySecure()` & `executeSecure()`).
   - Proteksi XSS otomatis (`sani()`).
   - Proteksi CSRF otomatis (*Zero-Boilerplate Auto-Injection*).
7. **CRUD & API Generator (Web & CLI)**: Pembuatan modul CRUD web, API JSON, dan file migrasi database secara otomatis dalam hitungan detik.
8. **Magic Pagination & Multi-Column Search**: Paginasi dan form pencarian multi-kolom hanya dengan 1 baris kode.

---

## Instalasi & Memulai Proyek

1. Letakkan folder proyek di web server lokal Anda (misal: `d:/xampp/htdocs/PersonalProject/PHMX`).
2. Buka file **`config.php`** dan sesuaikan konfigurasi database:
   ```php
   $host = 'localhost';
   $user = 'root';
   $pass = ''; 
   $db   = 'phmx_db'; // Sesuaikan nama database Anda
   ```
3. Buka Terminal / CMD di root folder proyek, lalu jalankan migrasi database awal:
   ```bash
   php phmx migrate
   ```
   *(Perintah ini akan membuat tabel `migrations` dan mengeksekusi semua file `.sql` di folder `database/` secara berurutan).*

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

## 2. REST API Gateway & Mobile Client (Bearer Token)

Semua permintaan API diakses melalui rute `/api/{endpoint}`.

### Endpoint Login Mobile
Klien mobile mengirim `email`/`username` dan `password` (mendukung JSON body maupun form-data):
```bash
POST /api/auth/mobile_login
Content-Type: application/json

{
  "email": "evanlum4jang@gmail.com",
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
      "email": "evanlum4jang@gmail.com",
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

### Menulis File API Baru di `api/methods/`
Gunakan fungsi bawaan `response($success, $message, $data)`:
```php
// api/methods/products/list.php
$res = querySecure($con, "SELECT * FROM products ORDER BY created_at DESC");
$products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $products[] = $row;
}

response(true, 'Data produk berhasil diambil', $products);
```

---

## 3. Konfigurasi Rute & Middleware Terpusat (`middleware/routes.php`)

Daftarkan proteksi rute Web maupun API di dalam file `middleware/routes.php`:

```php
<?php
return [
    // Rute Web (Memerlukan login session di browser)
    'users/user-management'   => ['auth'],
    'auth/login'              => ['throttle:5,1'], // Maks 5 percobaan per 1 menit

    // Rute API (Rate limited & Bearer Token)
    'api/auth/mobile_login'   => ['throttle:10,1'],
    'api/auth/silent_login'   => ['throttle:10,1'],
    'api/auth/mobile_logout'  => ['api_auth'],
    'api/users/*'             => ['api_auth'],     // Wildcard: semua endpoint api/users/ dilindungi token
];
?>
```

---

## 4. Remember Me (Silent Login) via localStorage

Fitur Remember Me tersimpan di `localStorage` peramban client:
1. Saat user mencentang *Remember Me* dan berhasil login, event `login-success` dipicu dan kredensial tersimpan di `localStorage`.
2. Skrip client [`assets/js/auth.js`](file:///d:/xampp/htdocs/PersonalProject/PHMX/assets/js/auth.js) mendeteksi saat session PHP kedaluwarsa ketika pengguna me-refresh halaman.
3. Skrip secara otomatis menembak `api/methods/auth/silent_login.php` di latar belakang untuk memperpanjang sesi tanpa perlu *redirect* atau mengganggu halaman yang sedang dibuka pengguna.
4. Saat user menekan tombol Logout, event `logout-success` otomatis membersihkan `localStorage`.

---

## 5. Daftar Fungsi Helper Bawaan

| Fungsi | Kegunaan | Contoh Pemakaian |
|---|---|---|
| `sani($data)` | Sanitasi string/array dari XSS | `$nama = sani($_POST['nama']);` |
| `querySecure($con, $sql, $params, $types)` | Eksekusi SELECT prepared statement | `$users = querySecure($con, "SELECT * FROM users WHERE role=?", ['admin'], 's');` |
| `executeSecure($con, $sql, $params, $types)` | Eksekusi INSERT/UPDATE/DELETE | `executeSecure($con, "DELETE FROM users WHERE id=?", [$id], 's');` |
| `response($success, $msg, $data)` | Standarisasi output JSON REST API | `response(true, 'Berhasil', $data);` |
| `generate_uuid()` | Generate UUID v4 36-karakter | `$id = generate_uuid();` |
| `htmxReloadWithMessage($msg, $type)` | Refresh halaman aktif dengan notifikasi | `htmxReloadWithMessage('Data berhasil disimpan!', 'success');` |
| `htmxRedirectWithMessage($url, $msg, $type)` | Redirect halaman SPA dengan pesan | `htmxRedirectWithMessage('dashboard', 'Selamat datang!', 'success');` |
| `htmxMessage($msg, $type)` | Tampilkan alert tanpa refresh | `htmxMessage('Form belum lengkap', 'danger');` |
| `paginationQuery($con, $sql, $params, $types, $limit, $baseUrl)` | Paginasi data otomatis | `$paginated = paginationQuery($con, "SELECT * FROM products", [], "", 10, 'products');` |
| `generateSearchForm($inputs, $button)` | Pembuat form pencarian dinamis | `generateSearchForm([['name' => 'search_nama', 'placeholder' => 'Cari...']]);` |

---

## 6. CRUD & REST API Generator

### Web Generator (`/generate-crud`)
1. Buka halaman `/generate-crud` di browser Anda.
2. Masukkan nama folder, nama file, nama tabel, dan konfigurasi kolom.
3. Centang opsi **"Generate REST API Endpoint (JSON)"** jika ingin membuat endpoint API secara bersamaan.
4. Klik **Generate Module**. Sistem akan membuatkan file `pages/`, `actions/`, `api/methods/`, dan migrasi SQL `database/` secara otomatis.

### CLI Runner (PHMX Artisan)
```bash
# Menampilkan bantuan CLI
php phmx

# Membuat kerangka kosong
php phmx make:crud products/manage

# Menjalankan migrasi SQL database
php phmx migrate
```

---

## 7. Logo & Desain Identitas

Logo resmi PHMX Framework tersimpan di direktori [`assets/images/logo/`](file:///d:/xampp/htdocs/PersonalProject/PHMX/assets/images/logo):
- `phmx-logo.png` & `phmx-logo.jpg`: Logo resolusi tinggi dengan emblem origami geometris.
- `phmx-logo.svg`: Vektor SVG transparan untuk tema terang (*light mode*).
- `phmx-logo-white.svg`: Vektor SVG transparan untuk tema gelap (*dark mode/navbar*).
- `phmx-mark.svg`: Emblem ikon vektor untuk favicon dan mobile icon.
