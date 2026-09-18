---
name: phmx_framework
description: "Aturan utama, konsep routing, REST API Gateway, autentikasi token/session, rate limiting, PWA, Web Push VAPID, dan daftar fungsi bawaan saat membangun aplikasi web/API menggunakan framework kustom PHMX."
---

# PHMX Framework Guidelines & Architecture

## 1. Arsitektur & Aturan Ketat
1. **Pemisahan Tiga Lapisan (Pages, Actions, APIs)**:
   - **`pages/`**: HANYA untuk tampilan antarmuka (HTML/UI) dan pembacaan data (`SELECT`). Dilarang keras menaruh query manipulasi data (`INSERT`, `UPDATE`, `DELETE`) di folder ini.
   - **`actions/`**: HANYA untuk memproses data dari form web / HTMX (`POST`, `PUT`, `DELETE`). Gunakan helper pesan HTMX seperti `htmxMessage()`, `htmxReloadWithMessage()`, atau `htmxRedirectWithMessage()`.
   - **`api/methods/`**: HANYA untuk *endpoint* REST API murni (Mobile/JSON). Setiap file di folder ini cukup menggunakan fungsi `response($success, $message, $data)`.
2. **SPA Navigasi & HTMX**:
   - `index.php` menggunakan `hx-boost="true"` pada tag `<body>` untuk mode SPA tanpa reload (Slash URL / HTML5 History API).
   - Gunakan path URL relatif (contoh: `<a href="users/user-management">`).
   - Form Web harus diarahkan ke action dengan pola `hx-post="?act=..."`, serta `hx-target` dan `hx-swap="innerHTML"`.
3. **REST API Gateway (`api/index.php`)**:
   - Semua panggilan API diarahkan melalui `.htaccess` ke `api/index.php?endpoint=...`.
   - Mendukung CORS preflight (`OPTIONS`), penerusan header `Authorization`, dan validasi CSRF untuk request web/session.
   - Mengembalikan output berformat standar JSON: `{ "success": bool, "message": string, "data": any }`.
4. **PWA & Web Push Notification (VAPID)**:
   - Saklar di `config.php`: `$pwa_enabled = true / false`.
   - Pasangan kunci VAPID P-256 dibuat otomatis melalui CLI: `php phmx vapid:generate`.
   - Pengiriman notifikasi push langsung dari PHP menggunakan `sendWebPush($subscription, $payload)`.

---

## 2. Sistem Middleware & Rute Terpusat (`middleware/routes.php`)
Konfigurasi middleware dikelola di satu tempat (`middleware/routes.php`) dengan pencocokan pola `fnmatch()`:
- **Rute Web (Pages/Actions)**:
  - `'users/user-management' => ['auth']` (Memerlukan session web aktif).
  - `'auth/login' => ['throttle:5,1']` (Rate limit 5 percobaan per 1 menit).
- **Rute API**:
  - `'api/auth/mobile_login' => ['throttle:10,1']` (Rate limit endpoint login mobile).
  - `'api/auth/mobile_logout' => ['api_auth']` (Wajib menyertakan Bearer Token).
  - `'api/users/*' => ['api_auth']` (Melindungi semua endpoint API pengguna dengan Bearer Token).
  - `'api/push/subscribe' => ['throttle:30,1']` (Pendaftaran endpoint push perangkat).
  - `'api/push/send_test' => ['throttle:10,1']` (Pengujian pengiriman notifikasi).

---

## 3. Sistem Autentikasi (Web vs Mobile)
1. **Web Session & Remember Me (Silent Login)**:
   - Login web menggunakan session PHP (`$_SESSION['user_id']`).
   - Fitur *Remember Me* memanfaatkan `localStorage` di client (`assets/js/auth.js`) dan endpoint `api/methods/auth/silent_login.php`. Sesi diperbarui secara senyap jika session server habis tanpa menendang user dari halaman aktif.
2. **Mobile & External API (Bearer Token)**:
   - Header: `Authorization: Bearer <token>`.
   - Endpoint login mobile: `api/methods/auth/mobile_login.php` menghasilkan token acak aman 64-karakter (`bin2hex(random_bytes(32))`) yang disimpan di tabel `users.api_token`.
   - Middleware `middleware/api_auth.php` memverifikasi token dan menyediakan data user login di `$GLOBALS['auth_user']`.

---

## 4. Daftar Fungsi Bawaan (Core Helpers)
Termuat otomatis melalui `functions/index.php`:
- `sani($data)`: Sanitasi wajib untuk input `$_POST` / `$_GET` dari XSS.
- `querySecure($con, $sql, $params, $types)`: Eksekusi prepared statement untuk query `SELECT`.
- `executeSecure($con, $sql, $params, $types)`: Eksekusi prepared statement untuk `INSERT`, `UPDATE`, `DELETE`.
- `sendWebPush($subscription, $payload, $config)`: Kirim Web Push Notification VAPID murni.
- `generateVapidKeys()`: Helper pembuatan pasangan kunci VAPID P-256.
- `response($success, $message, $data)`: Format respons standar REST API (hanya di konteks API).
- `generate_uuid()`: Menghasilkan UUID v4 36-karakter untuk Primary Key.
- `htmxRedirectWithMessage($url, $message, $type)`: Redirect SPA HTMX dengan notifikasi Toast/Alert.
- `htmxReloadWithMessage($message, $type)`: Refresh data di halaman aktif sambil menampilkan pesan sukses.
- `htmxMessage($message, $type)`: Menampilkan pesan alert tanpa reload.
- `paginationQuery($con, $sql, $params, $types, $limit, $baseUrl)`: Helper paginasi cerdas 1 baris.
- `generateSearchForm($inputs, $button)`: Helper instan pembuat form pencarian multi-kolom.

---

## 5. CLI & Database Migrations
- `php phmx`: Menampilkan bantuan dan daftar perintah CLI framework.
- `php phmx vapid:generate`: Membuat pasangan kunci VAPID baru dan otomatis menyimpannya ke `config.php`.
- `php phmx make:crud <folder>/<file>`: Menghasilkan kerangka kerja (*scaffolding*) CRUD.
- `php phmx migrate`: Menjalankan semua file migrasi `.sql` di folder `database/` secara berurutan. Format file: `YYYYMMDD-nama_migrasi.sql` atau `YYYYMMDDHHIISS-nama_tabel.sql`.
- **CRUD Generator Web (`/generate-crud`)**: Menghasilkan file halaman (`pages/`), logika aksi (`actions/`), API RESTful (`api/methods/`), dan migrasi tabel SQL secara otomatis.
