# Agent Instructions & Guidelines

> [!CAUTION]
> **PERINTAH WAJIB (MANDATORY INSTRUCTION):**
> Sebelum memulai tugas apapun di codebase ini, AI Agent **WAJIB** membaca dan menaati seluruh panduan arsitektur pada file:
> 👉 **[`.agents/skills/PHMX.md`](file:///.agents/skills/PHMX.md)**

---

## Aturan Utama (Quick Guardrails)

1. **Wajib Membaca Panduan Framework**:
   - Selalu baca [`.agents/skills/PHMX.md`](file:///.agents/skills/PHMX.md) sebelum menulis, mengedit file, atau membuat fitur baru. Jangan berasumsi di luar standar arsitektur PHMX.

2. **Pembuatan File & Modul Baru (CRUD)**:
   - **Dilarang keras** membuat file secara acak atau mencampur antarmuka UI dengan logika manipulasi data.
   - **Selalu gunakan CLI**: `php phmx make:crud <folder>/<nama_modul>` atau buka web generator di `/generate-crud`.
   - Patuhi arsitektur 3-lapisan:
     - `pages/`: Khusus UI (HTML) & query `SELECT` (gunakan `async_start()` / `async_end()` untuk lazy-loading).
     - `actions/`: Khusus pemrosesan form Web/HTMX (`POST`, `PUT`, `DELETE`).
     - `api/methods/`: Khusus REST API JSON murni (`response($success, $message, $data)`).

3. **Migrasi Database**:
   - **Dilarang keras** mengeksekusi DDL langsung (CREATE/ALTER TABLE) tanpa mencatatnya ke dalam file migrasi.
   - Buat file SQL di `database/YYYYMMDD-nama_migrasi.sql`.
   - Eksekusi migrasi menggunakan perintah: `php phmx migrate`.
