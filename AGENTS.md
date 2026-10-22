# Agent Instructions & Guidelines

> [!CAUTION]
> **PERINTAH WAJIB (MANDATORY INSTRUCTION):**
> Sebelum memulai tugas apapun di codebase ini, AI Agent **WAJIB** membaca dan menaati seluruh panduan arsitektur pada file:
> **[`.agents/skills/PHMX.md`](file:///.agents/skills/PHMX.md)**

---

## 1. Tech Stack
- **Backend**: Native PHP 8.x (Procedural Core Helpers, Prepared Statements `querySecure` / `executeSecure` / `transactionSecure`).
- **Frontend / SPA**: HTMX (`hx-boost="true"`, lazy-loading via `async_start()` / `async_end()`), Bootstrap 5 / Vanilla CSS, SweetAlert2.
- **API & Push**: REST API JSON Gateway (`/api/`), VAPID Web Push (RFC 8292/8291 murni).
- **Database**: MySQL (`mysqli`).

---

## 2. Core Guardrails & Execution Rules

1. **Strict Verification (Wajib Inspeksi Sebelum Menulis Kode)**:
   - **Dilarang keras menulis kode sebelum memastikan file atau referensi fungsinya benar-benar ada di workspace.**
   - Wajib baca/inspect file terlebih dahulu menggunakan tool viewing/search sebelum melakukan modifikasi atau pemanggilan fungsi helper.

2. **Anti-Assumption Rule**:
   - Jika kamu tidak yakin dengan isi file, parameter fungsi, atau arsitektur modular project, tanyakan atau baca kodenya langsung. **Jangan pernah menebak atau mengarang nama file/fungsi.**

3. **Pembuatan File & Modul Baru (CRUD)**:
   - **Dilarang membuat file secara acak.** Selalu gunakan CLI: `php phmx make:crud <folder>/<nama_modul>` atau web generator di `/generate-crud`.
   - Patuhi arsitektur 3-lapisan:
     - `pages/`: UI (HTML) & query `SELECT` (gunakan `async_start()` / `async_end()`).
     - `actions/`: Pemrosesan form Web/HTMX (`POST`, `PUT`, `DELETE`).
     - `api/methods/`: REST API JSON murni (`response($success, $message, $data)`).

4. **Migrasi Database**:
   - **Dilarang mengeksekusi DDL langsung (CREATE/ALTER TABLE)** tanpa file migrasi.
   - Buat file SQL di `database/YYYYMMDD-nama_migrasi.sql`, lalu jalankan: `php phmx migrate`.
