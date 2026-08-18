# PHMX Framework Documentation
PHMX adalah kerangka kerja (*framework*) PHP ringan yang dirancang untuk membangun *Single Page Application* (SPA) dengan sangat cepat, tanpa memerlukan *framework* JavaScript berat seperti React atau Vue. PHMX mengawinkan kekuatan PHP Native dengan kelancaran perpindahan halaman berkat **HTMX** dan sistem *Hash Routing* kustom.

---

## 🌟 Keunggulan (Key Features)
1. **SPA Tanpa Reload**: Perpindahan halaman secepat kilat menggunakan *Slash URL* / *Auto-Root* via HTML5 History API dan `hx-boost` dari HTMX.
2. **Tanpa Virtual DOM**: 100% menggunakan PHP murni untuk merender HTML di server, namun terasa seperti aplikasi React.
3. **Sangat Aman**: Dilengkapi fungsi bawaan `sani()` dan `secureQuery()` untuk perlindungan mutlak dari SQL Injection dan XSS.
4. **Auto-Routing & Middleware**: Sistem *routing* terpusat melalui `.htaccess` ke `index.php` dengan perlindungan *Middleware* dinamis di sisi server.
5. **Magic Pagination & Search**: Pembuatan fitur *Pencarian* dan *Paginasi* kompleks hanya dengan 1 baris kode.
6. **Robot Generator (Web & CLI)**: Dilengkapi fitur pembuat kode otomatis (*Scaffolding*) untuk menghemat waktu penulisan CRUD hingga 90%.

---

## 🚀 Instalasi & Langkah Pertama (Ganti DB)
Apa yang harus dilakukan saat pertama kali mengunduh atau menggunakan PHMX?

1. Pindahkan folder proyek ke dalam direktori server lokal Anda (misalnya `htdocs/PHMX`).
2. Buka file **`config.php`** di direktori utama.
3. Sesuaikan kredensial database Anda:
   ```php
   $host = 'localhost';
   $user = 'root';
   $pass = ''; // Password MySQL
   $db   = 'nama_database_anda'; // Ubah ini!
   ```
4. Buka Terminal / CMD di dalam folder proyek tersebut.
5. Jalankan perintah migrasi pertama Anda:
   ```bash
   php phmx migrate
   ```
   *(Ini akan otomatis membangun tabel `migrations` dan mengeksekusi semua struktur tabel awal Anda dari folder `database/`)*.

---

## 🗺️ Konsep Routing & Navigasi
Routing di PHMX bersifat *Auto-Root* (Slash URL) berkat bantuan `.htaccess` yang meneruskan *request* ke `index.php?hal=...`.

**Navigasi:**
Cukup gunakan tag `<a>` biasa dengan atribut `href` ke *path* relatif. Semua link otomatis diproses menjadi *request* HTMX tanpa mereload halaman berkat atribut `hx-boost="true"` pada elemen `<body>`.

```html
<!-- Contoh Navigasi -->
<a href="products">Produk</a>
<a href="users/user-management">Manajemen Pengguna</a>
```

---

## ⚡ Konsep Action (Pemrosesan Data)
File di dalam folder `pages/` **HANYA** ditujukan untuk tampilan (UI) dan pengambilan data (SELECT). 
Untuk proses merubah data (INSERT, UPDATE, DELETE), Anda **wajib** menggunakan konsep **Action**.

1. Buat file pemroses di folder `actions/` (misal: `actions/products/save.php`).
2. Pada file tampilan (`pages`), gunakan atribut HTMX untuk mengirim data ke action tersebut:
   ```html
   <form hx-post="?act=products/save" hx-target="#alert-box" hx-swap="innerHTML">
       <input type="text" name="nama">
       <button type="submit">Simpan</button>
   </form>
   ```
   *Perhatikan penulisan `?act=...`. Ini akan ditangkap oleh mesin `htmx_request.php` dan diarahkan ke folder `actions`.*

---

## 🛡️ Cara Penulisan Middleware
Middleware bertugas menghadang pengguna sebelum mereka bisa mengakses suatu *Page* (misalnya: harus login dulu).
1. Buat file di dalam folder `middleware/` (misal: `auth.php`).
2. Tulis logika pengecekan Anda:
   ```php
   <?php
   if (!isset($_SESSION['user_id'])) {
       // Tendang user ke halaman login jika belum login
       htmxRedirectWithMessage('auth/login', 'Anda harus login terlebih dahulu!', 'danger');
   }
   ?>
   ```
3. Daftarkan middleware tersebut pada file `middleware/routes.php` menggunakan pola `fnmatch()`:
   ```php
   // middleware/routes.php
   return [
       'users/*' => ['auth'], // Semua rute yang diawali 'users/' akan memanggil middleware 'auth'
   ];
   ```

---

## 🛠️ Daftar Fungsi Bawaan (Helpers)
PHMX menyediakan sederet "Senjata Rahasia" yang semuanya otomatis termuat dari `functions/index.php`.

### 1. `sani($data)`
Fungsi wajib untuk membersihkan input dari XSS (Cross-Site Scripting).
```php
$nama = sani($_POST['nama']);
```

### 2. `querySecure()` & `executeSecure()`
Fungsi untuk melakukan *Prepared Statements* secara instan.
- **`querySecure`**: Untuk SELECT (mengembalikan *result set*).
- **`executeSecure`**: Untuk INSERT, UPDATE, DELETE (mengembalikan *boolean* true/false).

```php
// Parameter: Koneksi, SQL, Array Data, Tipe Data (s=string, i=integer)
$users = querySecure($con, "SELECT * FROM users WHERE role = ?", ['admin'], 's');

$sukses = executeSecure($con, "DELETE FROM products WHERE id = ?", [$id], 's');
```

### 3. `generate_uuid()`
Menghasilkan ID unik 36-karakter (UUID v4) untuk Primary Key.
```php
$id_baru = generate_uuid();
```

### 4. `htmxRedirectWithMessage()` & `htmxMessage()`
- **`htmxMessage($msg, $tipe)`**: Menampilkan kotak alert hijau/merah, HANYA me-refresh sebagian.
- **`htmxRedirectWithMessage($url, $msg, $tipe)`**: Mengalihkan user ke halaman lain dengan mulus (mengganti keseluruhan `<body>`) sambil membawa pesan sukses/error.

### 5. `paginationQuery()`
Membuat logika Paginasi kompleks menjadi 1 baris.
```php
// Parameter: Koneksi, SQL, Array Data, Tipe Data, Limit Per Halaman, Hash URL
$paginated = paginationQuery($con, "SELECT * FROM products", [], "", 10, 'products/index');

$data_produk = $paginated['data']; // Hasil fetch mysqli
$tombol_halaman = $paginated['links']; // HTML tombol navigasi (1, 2, 3...)
```

### 6. File Upload
Jika Anda menggunakan fungsi bawaan `upload_file` (di dalam `php/upload_file.php`), Anda bisa mengunggah file dengan aman:
```php
// return nama file yang berhasil diupload atau false
$nama_file = upload_file($_FILES['foto'], 'assets/uploads/', ['jpg', 'png'], 2048000); 
```

---

## 🤖 CRUD Generator (The Magic)
Malas mengetik kode berulang? Gunakan Generator!

### A. Generator Berbasis Web
1. Pastikan Anda sudah membuat struktur tabel di database (melalui phpMyAdmin).
2. Akses halaman **`/generate-crud`** melalui browser Anda.
3. Masukkan nama folder, nama file, nama tabel, dan daftarkan kolom-kolomnya.
4. Klik tombol **Generate**. File *Pages*, *Actions*, dan *API* akan dibuat dan langsung bisa diakses dalam hitungan detik!

### B. Generator Berbasis CLI (PHMX Artisan)
Buka Terminal/CMD di folder proyek Anda, dan gunakan alat CLI canggih ini:

**Melihat daftar perintah:**
```bash
php phmx
```

**Membuat Kerangka Kosong (Scaffolding):**
Berguna jika Anda ingin mengetik kode *custom* dari awal namun malas membuat struktur filenya.
```bash
php phmx make:crud folder/namafile
```

**Menjalankan Database Migration:**
Secara otomatis mengeksekusi semua file `.sql` di folder `database/` secara berurutan dan aman (file yang sama tidak akan dieksekusi 2 kali).
```bash
php phmx migrate
```

---

## 🔒 Sistem Keamanan (Security)
PHMX dirancang dengan standar keamanan setara *Framework Enterprise* (seperti Laravel), namun tanpa *boilerplate* (beban pengetikan) bagi *developer*.

### 1. Direct Access Protection
Anda dan pengguna Anda tidak bisa mengakses file PHP di dalam `pages/` atau `actions/` secara langsung via URL browser.
- **Pengguna Apache:** Telah dilindungi secara otomatis melalui file `.htaccess` (berisi perintah `Deny from all`).
- **Pengguna Nginx:** Anda harus menyalin blok `location` yang ada di dalam file `nginx.conf.example` ke dalam konfigurasi *Virtual Host* Anda untuk memblokir direktori tersebut.

### 2. Auto-Injection CSRF (Zero-Boilerplate)
Framework ini menerapkan **Synchronizer Token Pattern** untuk menangkis serangan *Cross-Site Request Forgery*.
- Setiap *session* memiliki satu `csrf_token` rahasia (dihasilkan oleh `config.php`).
- **Keajaiban Output Buffering & Global Header:** Dulu, Anda wajib membungkus semuanya dalam `<form>`. **SEKARANG TIDAK LAGI!** Mesin otomatis akan menyisipkan `<input type="hidden" name="csrf_token">` pada `<form>` (sebagai *fallback*), **DAN** menyuntikkan token tersebut secara global melalui HTTP Header (`X-CSRF-Token`) untuk *setiap* panggilan HTMX!
- **Hukum Bebas:** Anda kini sepenuhnya bebas membuat tombol hapus (`hx-post` / `hx-delete`) menggunakan tag `<button>` sederhana tanpa perlu membungkusnya dengan `<form>`! Keamanan tetap terjaga di belakang layar.
- **Pengecualian:** Jika Anda membuat form/request tradisional (tanpa HTMX) di luar arsitektur `pages/`, barulah Anda harus mengetikkan token secara manual: `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">`.

---

## 🎨 Kustomisasi Template (Ganti Tema HTML)
PHMX sangat fleksibel! Anda bebas mengganti Bootstrap bawaan dengan framework CSS lain (Tailwind, Bulma) atau *Admin Template* (AdminLTE, SB Admin). Karena ini arsitektur SPA, Anda hanya perlu merombak satu file utama yaitu **`index.php`**.

Namun, agar sihir PHMX (SPA, Middleware, & Keamanan) tetap bekerja, file `index.php` yang baru **WAJIB** mempertahankan struktur krusial berikut:

```php
<?php
// 1. WAJIB: Memuat semua fungsi inti PHMX
require_once __DIR__ . '/functions/index.php';

// 2. WAJIB: Menjalankan mesin Routing & Middleware (Ubah 'welcome' dengan halaman default Anda)
phmx_setup('welcome');
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- 3. WAJIB: Base URL agar HTML5 History API (URL slash) tidak rusak -->
    <base href="<?= $base_url ?>">
    
    <!-- Silakan panggil file CSS Template Anda di sini -->
    <link rel="stylesheet" href="path/to/your-template.css">

    <!-- 4. WAJIB: Core Library HTMX -->
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@1.9.10/dist/htmx.js"></script>
    <!-- Opsional tapi Direkomendasikan: SweetAlert2 untuk Toast Notifikasi -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  </head>
  
  <!-- 5. WAJIB: Atribut hx-boost="true" pada body untuk mengaktifkan mode SPA pada semua link <a> -->
  <body hx-boost="true">
    
    <!-- Panggil Header / Sidebar / Navbar Template Anda -->
    <?php include __DIR__ . '/navbar.php'; ?>

    <!-- 6. WAJIB: Wadah utama konten (id="content" direkomendasikan) -->
    <main id="content">
        <?php
        // 7. WAJIB: Mesin pe-render halaman dinamis beserta auto-injeksi CSRF
        render_page($hal);
        ?>
    </main>

    <!-- Panggil Footer Template Anda -->

    <!-- Silakan panggil Javascript Template Anda di sini -->
    <script src="path/to/your-template.js"></script>

    <!-- 8. WAJIB: Injeksi Skrip Inti PHMX (Letakkan tepat di atas tag penutup body) -->
    <?php load_phmx_scripts(); ?>
  </body>
</html>
```

### Aturan Emas Ganti Template:
- **Jangan buang tag `<base>`**: SPA dengan routing `/folder/file` akan kehilangan arah ke file aset CSS/JS jika `<base href="...">` dihapus.
- **`hx-boost="true"` pada `<body>`**: Ini adalah "saklar ajaib" yang menyulap template statis HTML biasa menjadi Single Page Application super cepat.
- **`render_page($hal)`**: Pastikan ini dipanggil tepat di dalam kotak utama konten (misalnya di dalam `<div class="content-wrapper">`). Ini adalah tempat di mana file-file di folder `pages/` akan disuntikkan.

---

## 💡 Tips & Trick Penggunaan
1. **Aturan Utama HTMX Form**: Selalu gunakan kombinasi `hx-post="?act=..."`, `hx-target="#ID-Alert"`, dan `hx-swap="innerHTML"` pada form Anda. Ini memastikan pengguna tidak mengalami *page reload*.
2. **Jangan Lupa `sani()`**: Meskipun Anda sudah menggunakan `executeSecure`, tetap bungkus variabel `$_POST` Anda dengan `sani()` untuk melindungi HTML di sisi *frontend* (mencegah *Script Injection* saat data ditampilkan kembali).
3. **Tombol Konfirmasi**: Tambahkan atribut `hx-confirm="Yakin?"` di tag `<button>` jika Anda membuat tombol Hapus. HTMX akan otomatis memunculkan *Pop-up Confirmation* bawaan browser tanpa perlu JavaScript tambahan.
4. **Pisahkan UI dan Logika**: Disiplinlah! Jangan pernah menaruh logika INSERT/UPDATE di dalam folder `pages/`. Folder `pages/` harus sebersih mungkin, hanya berisi tag HTML dan query SELECT sederhana. Lempar beban berat ke folder `actions/`.
5. **Manfaatkan Toleransi Migrasi**: Jika `php phmx migrate` gagal di tengah jalan karena tabel secara manual sudah Anda buat, jangan panik. Sistem memiliki *Smart Tolerance*. Cukup jalankan lagi, ia akan mengabaikan tabel yang sudah ada secara otomatis (Kode 1050/1060/1061) dan melanjutkan ke file berikutnya!
