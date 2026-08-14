<div class="container py-5">
    <div id="generator-alert"></div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">PHMX CRUD Generator</h2>
    </div>
    
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Konfigurasi Modul</h5>
        </div>
        <div class="card-body">
            <form hx-post="?act=generate-crud" hx-target="#generator-alert" hx-swap="innerHTML">
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-muted">Nama Folder</label>
                        <input type="text" name="folder_name" class="form-control" placeholder="contoh: products" required>
                        <small class="text-muted">Folder akan dibuat di pages/, actions/, dan api/</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-muted">Nama File (Route)</label>
                        <input type="text" name="file_name" class="form-control" placeholder="contoh: product-management" required>
                        <small class="text-muted">Akan menjadi URL: #products/product-management</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-muted">Nama Tabel DB</label>
                        <input type="text" name="table_name" class="form-control" placeholder="contoh: products" required>
                        <small class="text-muted">Tabel yang sudah Anda buat di MySQL</small>
                    </div>
                </div>

                <hr>
                
                <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
                    <h5 class="mb-0 fw-bold">Definisi Kolom</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addColumn()">+ Tambah Kolom</button>
                </div>
                
                <div class="alert alert-info py-2 small">
                    <strong>Penting:</strong> Sistem mengasumsikan setiap tabel memiliki kolom <code>id</code> (VARCHAR/UUID) dan <code>created_at</code> (TIMESTAMP) secara otomatis. Jangan masukkan kolom tersebut di sini.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="columnsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Kolom (Field)</th>
                                <th>Label UI (Form/Table)</th>
                                <th>Tipe Data / Form</th>
                                <th class="text-center">Wajib?</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="columnsBody">
                            <!-- Kolom Default -->
                            <tr>
                                <td><input type="text" name="col_name[]" class="form-control form-control-sm" placeholder="contoh: title" required></td>
                                <td><input type="text" name="col_label[]" class="form-control form-control-sm" placeholder="contoh: Judul" required></td>
                                <td>
                                    <select name="col_type[]" class="form-select form-select-sm">
                                        <option value="text">Teks Pendek (VARCHAR / text)</option>
                                        <option value="textarea">Teks Panjang (TEXT / textarea)</option>
                                        <option value="number">Angka (INT / number)</option>
                                        <option value="email">Email (VARCHAR / email)</option>
                                        <option value="date">Tanggal (DATE / date)</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="col_required_dummy[]" value="1" class="form-check-input" checked onchange="this.nextElementSibling.value = this.checked ? '1' : '0'">
                                    <input type="hidden" name="col_required[]" value="1">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()" disabled>Hapus</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <hr class="mt-4 mb-4">
                
                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="generateApi" name="generate_api" value="1" checked>
                        <label class="form-check-label fw-bold" for="generateApi">Generate REST API Endpoint (JSON)</label>
                    </div>
                    <small class="text-muted d-block mt-1">Jika dicentang, file API akan digenerate di <code>api/{folder}/{file}.php</code> untuk diakses via mobile apps atau pihak ketiga.</small>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-5 fw-bold">⚡ Generate CRUD Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addColumn() {
    const tbody = document.getElementById('columnsBody');
    const tr = document.createElement('tr');
    
    // index untuk array checkbox trick, tapi php handle array input checkbox agak tricky (jika unchecked dia tidak kekirim). 
    // Trick: Kita kirim value dari checkbox menggunakan hidden input atau proses di backend berdasarkan nama kolom.
    // Lebih mudah: Pakai input name="col_name[]" dll.
    
    tr.innerHTML = `
        <td><input type="text" name="col_name[]" class="form-control form-control-sm" placeholder="contoh: price" required></td>
        <td><input type="text" name="col_label[]" class="form-control form-control-sm" placeholder="contoh: Harga" required></td>
        <td>
            <select name="col_type[]" class="form-select form-select-sm">
                <option value="text">Teks Pendek (VARCHAR / text)</option>
                <option value="textarea">Teks Panjang (TEXT / textarea)</option>
                <option value="number">Angka (INT / number)</option>
                <option value="email">Email (VARCHAR / email)</option>
                <option value="date">Tanggal (DATE / date)</option>
            </select>
        </td>
        <td class="text-center">
            <input type="checkbox" name="col_required_dummy[]" value="1" class="form-check-input" checked onchange="this.nextElementSibling.value = this.checked ? '1' : '0'">
            <input type="hidden" name="col_required[]" value="1">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">Hapus</button>
        </td>
    `;
    tbody.appendChild(tr);
}
</script>
