<?php
// actions/generate-crud.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $con;

    $folder_name = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder_name'] ?? ''));
    $file_name   = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['file_name'] ?? ''));
    $table_name  = trim(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name'] ?? ''));
    $generate_api = isset($_POST['generate_api']) && $_POST['generate_api'] == '1';
    
    $col_names    = $_POST['col_name'] ?? [];
    $col_labels   = $_POST['col_label'] ?? [];
    $col_types    = $_POST['col_type'] ?? [];
    $col_required = $_POST['col_required'] ?? [];
    
    if (empty($folder_name) || empty($file_name) || empty($table_name)) {
        htmxMessage('Folder, File, dan Table wajib diisi.', 'danger');
    }
    
    if (empty($col_names)) {
        htmxMessage('Minimal satu kolom harus ditambahkan.', 'danger');
    }
    
    // 1. Buat folder direktori jika belum ada
    $pages_dir   = __DIR__ . '/../pages/' . $folder_name;
    $actions_dir = __DIR__ . '/' . $folder_name;
    
    if (!is_dir($pages_dir)) mkdir($pages_dir, 0777, true);
    if (!is_dir($actions_dir)) mkdir($actions_dir, 0777, true);
    
    if ($generate_api) {
        $api_dir = __DIR__ . '/../api/methods/' . $folder_name;
        if (!is_dir($api_dir)) mkdir($api_dir, 0777, true);
    }
    
    // 2. GENERATE PAGES FILE (UI Mirip User Management dengan Async & Skeleton)
    $page_content = generatePageContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required);
    file_put_contents($pages_dir . '/' . $file_name . '.php', $page_content);
    
    // 3. GENERATE ACTIONS FILE (Logic Add, Update, Delete)
    $action_content = generateActionContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required);
    file_put_contents($actions_dir . '/' . $file_name . '.php', $action_content);
    
    // 4. GENERATE API FILE (RESTful JSON Endpoint jika dicentang)
    if ($generate_api) {
        $api_content = generateApiContent($file_name, $folder_name, $table_name, $col_names, $col_types, $col_required);
        file_put_contents($api_dir . '/' . $file_name . '.php', $api_content);
    }
    
    // 5. GENERATE & EKSEKUSI SQL FILE
    $db_dir = __DIR__ . '/../database';
    if (!is_dir($db_dir)) mkdir($db_dir, 0777, true);
    
    $sql_content = generateSqlContent($table_name, $col_names, $col_types, $col_required);
    $timestamp = date('YmdHis');
    $migrationFileName = $timestamp . '-' . $table_name . '.sql';
    file_put_contents($db_dir . '/' . $migrationFileName, $sql_content);
    
    // Otomatis eksekusi tabel di MySQL saat ini jika koneksi aktif
    if (isset($con) && $con) {
        try {
            @mysqli_query($con, $sql_content);
            $checkMig = @mysqli_query($con, "SHOW TABLES LIKE 'migrations'");
            if ($checkMig && mysqli_num_rows($checkMig) > 0) {
                @executeSecure($con, "INSERT INTO migrations (filename) VALUES (?)", [$migrationFileName], 's');
            }
        } catch (Throwable $e) {
            // Lanjutkan jika tabel sudah ada
        }
    }
    
    // Redirect langsung ke rute baru menggunakan HTMX
    htmxRedirectWithMessage("{$folder_name}/{$file_name}", "Modul {$file_name} berhasil di-generate!", "success");
}

/**
 * Pembangun konten UI Halaman (Pages) persis seperti user-management.php
 */
function generatePageContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required) {
    $title = ucwords(str_replace(['-', '_'], ' ', $file_name));
    $alertId = $file_name . '-alert';

    // Cari kolom untuk form pencarian
    $searchCols = [];
    foreach ($col_names as $idx => $cname) {
        if (count($searchCols) < 2 && in_array($col_types[$idx], ['text', 'email', 'textarea'])) {
            $searchCols[] = ['name' => $cname, 'label' => $col_labels[$idx]];
        }
    }
    if (empty($searchCols) && !empty($col_names)) {
        $searchCols[] = ['name' => $col_names[0], 'label' => $col_labels[0]];
    }
    
    // Bangun form pencarian responsif
    $searchFormArgs = "[\n";
    $searchVars = "";
    $whereChecks = "";
    
    if (count($searchCols) === 2) {
        $colsLayout = ['col-5 col-md-6', 'col-5 col-md-5'];
    } else {
        $colsLayout = ['col-10 col-md-11'];
    }

    foreach ($searchCols as $idx => $sc) {
        $layout = $colsLayout[$idx] ?? 'col-auto';
        $searchFormArgs .= "                ['name' => 'search_{$sc['name']}', 'placeholder' => 'Cari {$sc['label']}...', 'col' => '{$layout}'],\n";
        $searchVars .= "                        \$search_{$sc['name']} = sani(\$_GET['search_{$sc['name']}'] ?? '');\n";
        $whereChecks .= "                        if (!empty(\$search_{$sc['name']})) {\n";
        $whereChecks .= "                            \$whereConditions[] = \"{$sc['name']} LIKE ?\";\n";
        $whereChecks .= "                            \$params[] = \"%{\$search_{$sc['name']}}%\";\n";
        $whereChecks .= "                            \$types .= \"s\";\n";
        $whereChecks .= "                        }\n";
    }
    $searchFormArgs .= "            ]";
    
    // Header tabel
    $thHtml = "";
    foreach ($col_labels as $idx => $cl) {
        $pad = ($idx === 0) ? 'px-4 py-3' : 'py-3';
        $thHtml .= "                            <th class=\"{$pad}\">{$cl}</th>\n";
    }
    
    // Ekstraksi variabel per baris data
    $rowVarExtracts = "                                \$id = htmlspecialchars(\$row['id'] ?? '');\n";
    foreach ($col_names as $idx => $cn) {
        $rowVarExtracts .= "                                \${$cn} = htmlspecialchars(\$row['{$cn}'] ?? '');\n";
    }

    // Sel tabel
    $tdHtml = "";
    foreach ($col_names as $idx => $cn) {
        $pad = ($idx === 0) ? 'px-4' : '';
        $tdHtml .= "                            <td class=\"{$pad}\"><?= \${$cn} ?></td>\n";
    }
    
    // Argumen edit modal (openEditModal)
    $editArgsList = ["'<?= \$id ?>'"];
    $editSetters = "    document.getElementById('edit_id').value = id;\n";
    foreach ($col_names as $idx => $cn) {
        $editArgsList[] = "'<?= addslashes(\${$cn}) ?>'";
        $editSetters .= "    document.getElementById('edit_{$cn}').value = {$cn};\n";
    }
    $editArgs = implode(", ", $editArgsList);
    $editFuncParams = "id, " . implode(", ", $col_names);
    
    // Input form tambah & edit
    $addInputsHtml = "";
    $editInputsHtml = "";
    foreach ($col_names as $idx => $cn) {
        $cl = $col_labels[$idx];
        $ct = $col_types[$idx];
        $cr = $col_required[$idx] == '1' ? 'required' : '';
        
        $inputType = "text";
        if ($ct === 'email') $inputType = "email";
        if ($ct === 'number') $inputType = "number";
        if ($ct === 'date') $inputType = "date";
        
        if ($ct === 'textarea') {
            $addInputsHtml .= "                    <div class=\"mb-3\">\n";
            $addInputsHtml .= "                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>\n";
            $addInputsHtml .= "                        <textarea name=\"{$cn}\" class=\"form-control\" {$cr}></textarea>\n";
            $addInputsHtml .= "                    </div>\n";
            
            $editInputsHtml .= "                    <div class=\"mb-3\">\n";
            $editInputsHtml .= "                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>\n";
            $editInputsHtml .= "                        <textarea name=\"{$cn}\" id=\"edit_{$cn}\" class=\"form-control\" {$cr}></textarea>\n";
            $editInputsHtml .= "                    </div>\n";
        } else {
            $addInputsHtml .= "                    <div class=\"mb-3\">\n";
            $addInputsHtml .= "                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>\n";
            $addInputsHtml .= "                        <input type=\"{$inputType}\" name=\"{$cn}\" class=\"form-control\" {$cr}>\n";
            $addInputsHtml .= "                    </div>\n";
            
            $editInputsHtml .= "                    <div class=\"mb-3\">\n";
            $editInputsHtml .= "                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>\n";
            $editInputsHtml .= "                        <input type=\"{$inputType}\" name=\"{$cn}\" id=\"edit_{$cn}\" class=\"form-control\" {$cr}>\n";
            $editInputsHtml .= "                    </div>\n";
        }
    }
    
    $totalCols = count($col_names) + 1;

    return <<<HTML
<div class="container py-5">
    <div id="{$alertId}"></div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="fw-bold text-dark">Manajemen {$title}</h2>
    </div>

    <div class="text-end mb-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"> + Tambah</button>
    </div>
    <div class="card shadow-sm border-0 p-2">
        <?= generateSearchForm({$searchFormArgs}, [
                'text' => 'Cari',
                'col' => 'col-2 col-md-1',
                'class' => 'btn btn-sm btn-primary'
            ]) ?>
            <br>

        <!-- CONTAINER TABEL ASINKRONUS (SIMPEL & OTOMATIS) -->
        <?= async_start('Sedang memuat data {$title}...') ?>
        <?php if (is_async()): ?>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
{$thHtml}                            <th class="px-4 py-3 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Searching Logic
{$searchVars}
                        \$whereConditions = [];
                        \$params = [];
                        \$types = "";
{$whereChecks}
                        \$whereClause = "";
                        if (!empty(\$whereConditions)) {
                            \$whereClause = "AND " . implode(" AND ", \$whereConditions);
                        }
                        // End Searching Logic
                        
                        \$sql = "SELECT * FROM {$table_name} WHERE 1 = 1 \$whereClause ORDER BY created_at DESC";
                        
                        // EKSEKUSI AJAIB (Default 10 data per halaman dengan pagination)
                        \$limit = isset(\$_GET['limit']) ? (int)\$_GET['limit'] : 10;

                        \$paginated = paginationQuery(\$con, \$sql, \$params, \$types, \$limit, '{$folder_name}/{$file_name}');
                        \$rows = \$paginated['data'];
                        
                        if (\$rows && mysqli_num_rows(\$rows) > 0) {
                            while (\$row = mysqli_fetch_assoc(\$rows)) {
{$rowVarExtracts}                        ?>
                        <tr>
{$tdHtml}                            <td class="px-4 text-end">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="openEditModal({$editArgs})">
                                    Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger ms-1"
                                        hx-post="?act={$folder_name}/{$file_name}" 
                                        hx-vals='{"action_type": "delete", "id": "<?= \$id ?>"}'
                                        hx-target="#{$alertId}"
                                        hx-confirm="Yakin ingin menghapus data ini?">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo '<tr><td colspan="{$totalCols}" class="text-center py-4 text-muted">Data {$title} tidak ditemukan.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- BAGIAN PAGINATION -->
            <?php if (!empty(\$paginated['links'])): ?>
            <div class="card-footer bg-white py-3 border-0">
                <?= \$paginated['links'] ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        <?= async_end() ?>
    </div>
</div>

<!-- MODAL TAMBAH DATA -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addModalLabel">Tambah {$title} Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="?act={$folder_name}/{$file_name}" hx-target="#{$alertId}" hx-swap="innerHTML">
                <input type="hidden" name="action_type" value="add">
                <div class="modal-body">
{$addInputsHtml}                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT DATA -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editModalLabel">Edit {$title}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="?act={$folder_name}/{$file_name}" hx-target="#{$alertId}" hx-swap="innerHTML">
                <input type="hidden" name="action_type" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
{$editInputsHtml}                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal({$editFuncParams}) {
{$editSetters}    
    var editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
}
</script>
HTML;
}

/**
 * Pembangun logika Action (Actions) Add, Update, Delete
 */
function generateActionContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required) {
    $title = ucwords(str_replace(['-', '_'], ' ', $file_name));
    $varsAssigment = "";
    $reqChecks = [];
    $insertFields = "id";
    $insertPlaceholders = "?";
    $insertVars = "\$id";
    $insertTypes = "s";
    
    $updateFields = [];
    $updateVars = [];
    $updateTypes = "";
    
    foreach ($col_names as $idx => $cn) {
        $varsAssigment .= "        \${$cn} = sani(\$_POST['{$cn}'] ?? '');\n";
        
        if ($col_required[$idx] == '1') {
            $reqChecks[] = "empty(\${$cn})";
        }
        
        $insertFields .= ", {$cn}";
        $insertPlaceholders .= ", ?";
        $insertVars .= ", \${$cn}";
        
        $type = ($col_types[$idx] === 'number') ? "i" : "s";
        $insertTypes .= $type;
        
        $updateFields[] = "{$cn} = ?";
        $updateVars[] = "\${$cn}";
        $updateTypes .= $type;
    }
    
    $reqChecksStr = empty($reqChecks) ? "false" : implode(" || ", $reqChecks);
    $keepFieldsJson = json_encode($col_names);
    
    $updateFieldsStr = implode(", ", $updateFields);
    $updateVarsStr = implode(", ", $updateVars) . ", \$id";
    $updateTypes .= "s"; // Untuk id
    
    return <<<PHP
<?php
// actions/{$folder_name}/{$file_name}.php
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    global \$con;
    \$action_type = \$_POST['action_type'] ?? '';
    
    // ==========================================
    // ACTION: TAMBAH DATA
    // ==========================================
    if (\$action_type === 'add') {
{$varsAssigment}
        if ({$reqChecksStr}) {
            htmxMessage('Semua kolom wajib diisi!', 'danger', {$keepFieldsJson});
        }
        
        \$id = generate_uuid();
        
        \$success = executeSecure(\$con, 
            "INSERT INTO {$table_name} ({$insertFields}) VALUES ({$insertPlaceholders})",
            [{$insertVars}],
            '{$insertTypes}'
        );
        
        if (\$success) {
            htmxReloadWithMessage('Berhasil menambahkan {$title} baru!', 'success');
        } else {
            htmxMessage('Gagal menambahkan {$title}.', 'danger', {$keepFieldsJson});
        }
    }
    
    // ==========================================
    // ACTION: PERBARUI DATA
    // ==========================================
    else if (\$action_type === 'update') {
        \$id = sani(\$_POST['id'] ?? '');
{$varsAssigment}
        if (empty(\$id) || ({$reqChecksStr})) {
            htmxMessage('Semua kolom wajib diisi!', 'danger');
        }
        
        \$success = executeSecure(\$con, 
            "UPDATE {$table_name} SET {$updateFieldsStr} WHERE id = ?",
            [{$updateVarsStr}],
            '{$updateTypes}'
        );
        
        if (\$success) {
            htmxReloadWithMessage('Data {$title} berhasil diperbarui!', 'success');
        } else {
            htmxMessage('Gagal memperbarui data {$title}.', 'danger');
        }
    }
    
    // ==========================================
    // ACTION: HAPUS DATA
    // ==========================================
    else if (\$action_type === 'delete') {
        \$id = sani(\$_POST['id'] ?? '');
        
        if (empty(\$id)) {
            htmxMessage('ID data tidak valid.', 'danger');
        }
        
        \$success = executeSecure(\$con, "DELETE FROM {$table_name} WHERE id = ?", [\$id], 's');
        
        if (\$success) {
            htmxReloadWithMessage('Data {$title} berhasil dihapus!', 'success');
        } else {
            htmxMessage('Gagal menghapus data {$title}.', 'danger');
        }
    }
}
?>
PHP;
}

/**
 * Pembangun RESTful API Endpoint (Mobile / Client Eksternal)
 */
function generateApiContent($file_name, $folder_name, $table_name, $col_names, $col_types, $col_required) {
    $insertFields = "id";
    $insertPlaceholders = "?";
    $insertVars = ["\$id"];
    $insertTypes = "s";
    
    $updateFields = [];
    $updateVars = [];
    $updateTypes = "";
    
    $inputAssignments = "";
    $reqChecks = [];
    
    foreach ($col_names as $idx => $cn) {
        $inputAssignments .= "        \${$cn} = sani(\$input['{$cn}'] ?? '');\n";
        if ($col_required[$idx] == '1') {
            $reqChecks[] = "empty(\${$cn})";
        }
        $insertFields .= ", {$cn}";
        $insertPlaceholders .= ", ?";
        $insertVars[] = "\${$cn}";
        
        $type = ($col_types[$idx] === 'number') ? "i" : "s";
        $insertTypes .= $type;
        $updateFields[] = "{$cn} = ?";
        $updateVars[] = "\${$cn}";
        $updateTypes .= $type;
    }
    
    $insertVarsStr = implode(", ", $insertVars);
    $updateFieldsStr = implode(", ", $updateFields);
    $updateVarsStr = implode(", ", $updateVars) . ", \$id";
    $updateTypes .= "s";
    $reqChecksStr = empty($reqChecks) ? "false" : implode(" || ", $reqChecks);

    return <<<PHP
<?php
// api/methods/{$folder_name}/{$file_name}.php

global \$con;
\$method = \$_SERVER['REQUEST_METHOD'];
\$id = \$_GET['id'] ?? null;

// Parse JSON input untuk request non-GET
\$input = \$_POST;
if (empty(\$input) && (\$json = file_get_contents('php://input'))) {
    \$decoded = json_decode(\$json, true);
    if (is_array(\$decoded)) \$input = \$decoded;
}

switch (\$method) {
    case 'GET':
        if (\$id) {
            \$res = querySecure(\$con, "SELECT * FROM {$table_name} WHERE id = ? LIMIT 1", [\$id], 's');
            \$data = \$res ? mysqli_fetch_assoc(\$res) : null;
            if (\$data) {
                response(true, 'Data berhasil ditemukan', \$data);
            } else {
                http_response_code(404);
                response(false, 'Data tidak ditemukan');
            }
        } else {
            \$page  = isset(\$_GET['page']) ? (int)\$_GET['page'] : 1;
            \$limit = isset(\$_GET['limit']) ? (int)\$_GET['limit'] : 20;
            \$offset = (\$page - 1) * \$limit;
            
            \$res = querySecure(\$con, "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT ? OFFSET ?", [\$limit, \$offset], 'ii');
            \$data = [];
            if (\$res) {
                while (\$r = mysqli_fetch_assoc(\$res)) {
                    \$data[] = \$r;
                }
            }
            response(true, 'Data fetched successfully', \$data);
        }
        break;

    case 'POST':
{$inputAssignments}
        if ({$reqChecksStr}) {
            http_response_code(422);
            response(false, 'Parameter wajib belum lengkap');
        }
        
        \$id = generate_uuid();
        \$ok = executeSecure(\$con, 
            "INSERT INTO {$table_name} ({$insertFields}) VALUES ({$insertPlaceholders})", 
            [{$insertVarsStr}], 
            '{$insertTypes}'
        );
        
        if (\$ok) {
            http_response_code(201);
            response(true, 'Data berhasil dibuat', ['id' => \$id]);
        } else {
            http_response_code(500);
            response(false, 'Gagal menyimpan data ke database');
        }
        break;

    case 'PUT':
        if (!\$id) {
            http_response_code(400);
            response(false, 'ID wajib disertakan untuk update data');
        }
{$inputAssignments}
        \$ok = executeSecure(\$con, 
            "UPDATE {$table_name} SET {$updateFieldsStr} WHERE id = ?", 
            [{$updateVarsStr}], 
            '{$updateTypes}'
        );
        
        if (\$ok) {
            response(true, 'Data berhasil diperbarui');
        } else {
            http_response_code(500);
            response(false, 'Gagal memperbarui data');
        }
        break;

    case 'DELETE':
        if (!\$id) {
            http_response_code(400);
            response(false, 'ID wajib disertakan untuk menghapus data');
        }
        
        \$ok = executeSecure(\$con, "DELETE FROM {$table_name} WHERE id = ?", [\$id], 's');
        if (\$ok) {
            response(true, 'Data berhasil dihapus');
        } else {
            http_response_code(500);
            response(false, 'Gagal menghapus data');
        }
        break;

    default:
        http_response_code(405);
        response(false, 'Metode HTTP tidak diizinkan');
        break;
}
?>
PHP;
}

/**
 * Pembangun skema tabel SQL
 */
function generateSqlContent($table_name, $col_names, $col_types, $col_required) {
    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (\n";
    $sql .= "  `id` varchar(36) NOT NULL,\n";
    
    foreach ($col_names as $idx => $cn) {
        $type = "VARCHAR(255)";
        if ($col_types[$idx] === 'textarea') $type = "TEXT";
        if ($col_types[$idx] === 'number')   $type = "INT(11)";
        if ($col_types[$idx] === 'date')     $type = "DATE";
        
        $req = ($col_required[$idx] == '1') ? "NOT NULL" : "DEFAULT NULL";
        
        $sql .= "  `{$cn}` {$type} {$req},\n";
    }
    
    $sql .= "  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n";
    $sql .= "  PRIMARY KEY (`id`)\n";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\n";
    
    return $sql;
}
?>
