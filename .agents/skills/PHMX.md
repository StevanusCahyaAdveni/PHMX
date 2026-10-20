---
name: phmx_framework
description: "PANDUAN UTAMA WAJIB BACA: Aturan arsitektur 3-lapisan, CLI phmx (pembuatan modul CRUD & migrasi database), async deferred UI 0ms, TCL transaksi database, REST API, autentikasi ganda, dan fungsi inti PHMX. WAJIB DIBACA AI AGENT SETIAP KALI INGIN MELAKUKAN TUGAS."
---

# PHMX Framework — AI Agent Cheatsheet & Guardrails

> [!CAUTION]
> ### PERINTAH WAJIB UNTUK AI AGENT (MANDATORY INSTRUCTION)
> **SETIAP KALI AI AGENT INGIN MELAKUKAN ATAU MEMULAI TUGAS APAPUN PADA CODEBASE INI, AI AGENT WAJIB MEMBACA DAN MEMAHAMI SELURUH ISI FILE `[.agents/skills/PHMX.md](file:///d:/xampp/htdocs/PersonalProject/PHMX/.agents/skills/PHMX.md)` INI TERLEBIH DAHULU SEBELUM MENULIS, MENGUBAH KODE, MEMBUAT FILE, ATAU MENJALANKAN QUERY!**
>
> **Dilarang keras berasumsi, melupakan aturan, atau menyimpang dari arsitektur framework yang telah disepakati di bawah ini!**

> [!IMPORTANT]
> **ATURAN MUTLAK: PEMBUATAN FILE BARU & MIGRASI DATABASE**
>
> 1. **PEMBUATAN FILE BARU (CRUD / MODUL)**:
>    - **DILARANG KERAS membuat file secara acak atau mencampur antarmuka UI dengan logika manipulasi data.**
>    - **SELALU gunakan CLI: `php phmx make:crud <folder>/<nama_modul>`** atau buka web generator di **/generate-crud**.
>    - **SETIAP modul CRUD WAJIB mematuhi arsitektur 3-lapisan terisolasi**:
>      - **`pages/<folder>/<modul>.php`**: HANYA untuk tampilan UI (HTML) & pembacaan data (`SELECT`). Gunakan `async_start()` & `async_end()` untuk rendering tabel/data berat.
>      - **`actions/<folder>/<modul>.php`**: HANYA untuk memproses form Web / HTMX (`POST`, `PUT`, `DELETE`). Gunakan `htmxMessage()`, `htmxReloadWithMessage()`, atau `htmxRedirectWithMessage()`.
>      - **`api/methods/<folder>/<modul>.php`**: HANYA untuk endpoint REST API JSON murni (`response($success, $message, $data)`).
>
> 2. **MIGRASI DATABASE**:
>    - **DILARANG KERAS mengeksekusi DDL langsung (CREATE/ALTER TABLE) tanpa mencatatnya ke dalam file migrasi!**
>    - **SETIAP perubahan struktur database WAJIB dibuatkan file migrasi baru di folder `database/`** dengan format nama: **`database/YYYYMMDD-nama_migrasi.sql`** (contoh: `database/20261011-tabel_produk.sql`).
>    - **SETELAH membuat file SQL, WAJIB eksekusi CLI: `php phmx migrate`** untuk menerapkan struktur ke database secara aman dan tercatat di tabel `migrations`.

---

## 1. Navigasi SPA & Async Deferred UI (0ms Transition)

- **Navigasi SPA**: Tag `<body>` di `index.php` menggunakan `hx-boost="true"` (Slash URL / HTML5 History API).
- **Pola Lazy-Load Skeleton**: Untuk tabel atau halaman dengan query data berat, **WAJIB** gunakan kontainer lazy-load:
  ```php
  <?= async_start('Memuat data pengguna...', 'table-container', 'load_users') ?>
  <?php if (is_async('load_users')): ?>
      <!-- Query berat SELECT, paginationQuery(), dan render tabel di sini -->
  <?php endif; ?>
  <?= async_end('load_users') ?>
  ```
  *(Sistem otomatis memanggil `session_write_close()` untuk menghindari session lock, membatalkan request lama via auto-abort saat pindah halaman, dan menampilkan shell halaman seketika 0ms).*

---

## 2. Database & Transaksi TCL (ACID Safe)

- **Prepared Statements**:
  - `querySecure($con, $sql, $params, $types)` -> Untuk query `SELECT`.
  - `executeSecure($con, $sql, $params, $types)` -> Untuk query `INSERT`, `UPDATE`, `DELETE`.
- **Transaksi Konsistensi Data (TCL)**:
  Untuk eksekusi multi-query yang saling bergantung, **WAJIB** bungkus dengan `transactionSecure()`:
  ```php
  $success = transactionSecure($con, function($c) {
      executeSecure($c, "INSERT INTO orders (id, total) VALUES (?, ?)", [$id, $total], 'si');
      executeSecure($c, "UPDATE inventory SET stock = stock - ? WHERE id = ?", [$qty, $item_id], 'is');
      return true; // Return false jika ingin auto-rollback
  });
  if (!$success) {
      $error = getTransactionError();
  }
  ```
  *(Disediakan juga kontrol manual: `beginTransaction($con)`, `commitTransaction($con)`, `rollbackTransaction($con)`).*

---

## 3. REST API Gateway & Autentikasi Ganda

- **Gateway Terpusat (`/api/`)**: Semua request diarahkan ke `api/index.php?endpoint=...`.
- **Standar Respons JSON**: HANYA gunakan `response($success, $message, $data)`.
- **Autentikasi Web**: Berbasis Session PHP + *Silent Login (Remember Me)* via `localStorage` di client (`assets/js/auth.js`) yang menembak `/api/auth/silent_login`.
- **Autentikasi Mobile**: Berbasis Bearer Token (`Authorization: Bearer <token>`). Verifikasi token otomatis dilakukan middleware `api_auth` dan user login tersimpan di `$GLOBALS['auth_user']`.
- **Rute & Middleware (`middleware/routes.php`)**: Daftarkan rute menggunakan wildcard `fnmatch()`, contoh:
  - `'users/*' => ['auth']` (Web Session)
  - `'api/users/*' => ['api_auth']` (Mobile Bearer Token)
  - `'auth/login' => ['throttle:5,1']` (Rate Limiting: maks 5 percobaan per 1 menit)

---

## 4. Web Push Notification (VAPID) & Antrean Background

- **Kirim Tunggal**: `sendWebPush($subscription, $payload)` (dilengkapi anti-SSRF & validasi HTTPS).
- **Kirim Batch Paralel**: `sendWebPushBatch($subscriptions, $payload)` (menggunakan `curl_multi_exec` concurrent non-blocking).
- **Antrean Database (Queue)**: `queueWebPush($con, $subscription, $payload)` untuk memasukkan ke antrean.
- **Worker Background**: Jalankan `php phmx push:work 50 --loop` (bisa dijalankan via cron job atau daemon supervisor).

---

## 5. Ringkasan Core Helper Functions

| Helper | Kegunaan |
|---|---|
| `sani($data)` | Sanitasi input XSS |
| `asset($path)` | Smart versioning CSS/JS via `filemtime()` |
| `uploadFile($file, $dir, $size, $types)` | Upload aman + auto-compress + whitelist |
| `generate_uuid()` | Generate UUID v4 36-karakter |
| `paginationQuery($con, $sql, $p, $t, $limit, $url)` | Paginasi otomatis |
| `generateSearchForm($inputs, $btn)` | Form filter & pencarian multi-kolom |
| `htmxReloadWithMessage($msg, $type)` | Refresh halaman aktif dengan notifikasi |
| `htmxRedirectWithMessage($url, $msg, $type)` | Pindah halaman SPA dengan notifikasi |
| `htmxMessage($msg, $type)` | Alert respon AJAX HTMX |

---

## 6. Daftar Perintah CLI (`phmx`)

- **`php phmx make:crud <folder>/<file>`** : **Membuat kerangka modul 3-lapisan (`pages/`, `actions/`, `api/methods/`)**
- **`php phmx migrate`** : **Menjalankan seluruh file migrasi SQL baru di `database/`**
- `php phmx push:work [batch] [--loop]` : Menjalankan background worker antrean Web Push
- `php phmx vapid:generate` : Membuat pasangan kunci VAPID dan menyimpannya ke `config.php`
- `php phmx seed:users [jumlah]` : Mengisi data dummy pengguna (default: 100)
