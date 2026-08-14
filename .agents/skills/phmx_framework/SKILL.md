---
name: phmx_framework
description: "Aturan utama, konsep routing, dan daftar fungsi bawaan saat membangun aplikasi web menggunakan framework kustom PHMX."
---

# PHMX Framework Guidelines

## Arsitektur & Aturan Ketat
1. **Pemisahan Logika**: 
   - Folder `pages/`: HANYA untuk UI (HTML) dan Query SELECT. Dilarang menaruh logika INSERT/UPDATE/DELETE di sini.
   - Folder `actions/`: HANYA untuk memproses data (POST/PUT/DELETE).
2. **HTMX Integration & Navigasi**: Framework kini menggunakan `hx-boost="true"` pada `<body>` untuk SPA Navigation (Auto Root / Slash URL). Jangan gunakan `#hash` untuk link, cukup gunakan relative url (misal `href="users/user-management"`).
3. **Routing & Middleware**: Routing ditangani secara dinamis melalui `.htaccess` ke `index.php?hal=...`. Middleware dikonfigurasi secara terpusat di `middleware/routes.php` menggunakan pencocokan pola `fnmatch()`.
4. **Action & Form**: Form action harus merujuk ke aksi dengan `hx-post="?act=..."`, menggunakan `hx-target` dan `hx-swap="innerHTML"`.

## Daftar Fungsi Bawaan (Wajib Digunakan)
- `sani($data)`: Wajib untuk sanitasi input $_POST / $_GET.
- `querySecure($con, $sql, $params, $types)`: Mengeksekusi prepared statement (SELECT).
- `executeSecure($con, $sql, $params, $types)`: Mengeksekusi prepared statement (INSERT/UPDATE/DELETE).
- `generate_uuid()`: Menghasilkan UUID 36 karakter untuk primary key.
- `htmxRedirectWithMessage($url, $message, $type)`: Redirect SPA ramah HTMX. Target redirect adalah `body` untuk mengganti keseluruhan halaman, menghindari nested navbar.
- `paginationQuery($con, $sql, $params, $types, $limit, $baseUrl)`: Helper paginasi.

## CLI & Generasi
- Gunakan `php phmx make:crud <folder>/<file>` untuk scaffolding dasar.
- Gunakan `php phmx migrate` untuk mengeksekusi semua SQL di `database/`.

## Standar Keamanan & CSRF (Sangat Penting!)
1. **Auto-Injector CSRF**: Framework ini menggunakan Output Buffering di `htmx_request.php` untuk menyisipkan `<input type="hidden" name="csrf_token">` ke dalam semua tag `<form>` secara otomatis.
2. **Wajib Menggunakan Form**: Jangan PERNAH membuat tombol aksi manipulasi data (POST/DELETE/PUT) yang berdiri sendiri (`<button hx-post="...">`). Semua tombol aksi **wajib** dibungkus dengan `<form>` agar sistem Auto-Injector CSRF dapat bekerja.
3. **Pengecualian File Eksternal**: Jika membuat form di luar folder `pages/` (misalnya di `navbar.php` yang dipanggil langsung via `hx-get`), Auto-Injector tidak akan berjalan. Anda wajib menyisipkan token manual: `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">`.
