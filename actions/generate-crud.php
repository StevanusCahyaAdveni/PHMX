<?php
// actions/generate-crud.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder_name = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder_name'] ?? ''));
    $file_name = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['file_name'] ?? ''));
    $table_name = trim(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name'] ?? ''));
    $generate_api = isset($_POST['generate_api']) && $_POST['generate_api'] == '1';
    
    $col_names = $_POST['col_name'] ?? [];
    $col_labels = $_POST['col_label'] ?? [];
    $col_types = $_POST['col_type'] ?? [];
    $col_required = $_POST['col_required'] ?? [];
    
    if (empty($folder_name) || empty($file_name) || empty($table_name)) {
        htmxMessage('Folder, File, dan Table wajib diisi.', 'danger');
    }
    
    if (empty($col_names)) {
        htmxMessage('Minimal satu kolom harus ditambahkan.', 'danger');
    }
    
    // Create folders
    $pages_dir = __DIR__ . '/../pages/' . $folder_name;
    $actions_dir = __DIR__ . '/' . $folder_name;
    
    if (!is_dir($pages_dir)) mkdir($pages_dir, 0777, true);
    if (!is_dir($actions_dir)) mkdir($actions_dir, 0777, true);
    
    if ($generate_api) {
        $api_dir = __DIR__ . '/../api/' . $folder_name;
        if (!is_dir($api_dir)) mkdir($api_dir, 0777, true);
    }
    
    // ---------------------------------------------------------
    // 1. GENERATE PAGES FILE
    // ---------------------------------------------------------
    $page_content = generatePageContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required);
    file_put_contents($pages_dir . '/' . $file_name . '.php', $page_content);
    
    // ---------------------------------------------------------
    // 2. GENERATE ACTIONS FILE
    // ---------------------------------------------------------
    $action_content = generateActionContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required);
    file_put_contents($actions_dir . '/' . $file_name . '.php', $action_content);
    
    // ---------------------------------------------------------
    // 3. GENERATE API FILE (If requested)
    // ---------------------------------------------------------
    if ($generate_api) {
        $api_content = generateApiContent($table_name, $col_names);
        file_put_contents($api_dir . '/' . $file_name . '.php', $api_content);
    }
    

    // ---------------------------------------------------------
    // 5. GENERATE SQL FILE
    // ---------------------------------------------------------
    $db_dir = __DIR__ . '/../database';
    if (!is_dir($db_dir)) mkdir($db_dir, 0777, true);
    
    $sql_content = generateSqlContent($table_name, $col_names, $col_types, $col_required);
    $timestamp = date('YmdHis');
    file_put_contents($db_dir . '/' . $timestamp . '-' . $table_name . '.sql', $sql_content);
    
    // Injeksi rute langsung ke memori client sebelum redirect, agar tidak 404
    htmxRedirectWithMessage("{$folder_name}/{$file_name}", "Modul {$file_name} berhasil di-generate!", "success");
}

function generatePageContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required) {
    // Cari 2 kolom teks untuk pencarian
    $searchCols = [];
    foreach($col_names as $idx => $cname) {
        if (count($searchCols) < 2 && in_array($col_types[$idx], ['text', 'email', 'textarea'])) {
            $searchCols[] = ['name' => $cname, 'label' => $col_labels[$idx]];
        }
    }
    
    // Fallback jika tidak ada kolom teks
    if (empty($searchCols) && !empty($col_names)) {
        $searchCols[] = ['name' => $col_names[0], 'label' => $col_labels[0]];
    }
    
    $searchFormArgs = "[\n";
    $whereChecks = "";
    foreach($searchCols as $idx => $sc) {
        $searchFormArgs .= "                ['name' => 'search_{$sc['name']}', 'placeholder' => 'Cari {$sc['label']}...', 'col' => 'col-5'],\n";
        $whereChecks .= "
                    if (!empty(\$search_{$sc['name']})) {
                        \$whereConditions[] = \"{$sc['name']} LIKE ?\";
                        \$params[] = \"%{\$search_{$sc['name']}}%\";
                        \$types .= \"s\";
                    }";
    }
    $searchFormArgs .= "            ]";
    
    $thHtml = "";
    foreach($col_labels as $cl) {
        $thHtml .= "                        <th class=\"py-3\">{$cl}</th>\n";
    }
    
    $tdHtml = "";
    foreach($col_names as $idx => $cn) {
        $tdHtml .= "                        <td><?= htmlspecialchars(\$row['{$cn}']) ?></td>\n";
    }
    
    $editArgs = "'<?= \$row['id'] ?>'";
    $editSetters = "document.getElementById('edit_id').value = id;\n";
    foreach($col_names as $idx => $cn) {
        $editArgs .= ", '<?= addslashes(\$row['{$cn}']) ?>'";
        $editSetters .= "    document.getElementById('edit_{$cn}').value = {$cn};\n";
    }
    $editFuncParams = "id, " . implode(", ", $col_names);
    
    $addInputsHtml = "";
    $editInputsHtml = "";
    foreach($col_names as $idx => $cn) {
        $cl = $col_labels[$idx];
        $ct = $col_types[$idx];
        $cr = $col_required[$idx] == '1' ? 'required' : '';
        
        $inputType = "text";
        if ($ct === 'email') $inputType = "email";
        if ($ct === 'number') $inputType = "number";
        if ($ct === 'date') $inputType = "date";
        
        if ($ct === 'textarea') {
            $addInputsHtml .= "
                    <div class=\"mb-3\">
                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>
                        <textarea name=\"{$cn}\" class=\"form-control\" {$cr}></textarea>
                    </div>";
            $editInputsHtml .= "
                    <div class=\"mb-3\">
                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>
                        <textarea name=\"{$cn}\" id=\"edit_{$cn}\" class=\"form-control\" {$cr}></textarea>
                    </div>";
        } else {
            $addInputsHtml .= "
                    <div class=\"mb-3\">
                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>
                        <input type=\"{$inputType}\" name=\"{$cn}\" class=\"form-control\" {$cr}>
                    </div>";
            $editInputsHtml .= "
                    <div class=\"mb-3\">
                        <label class=\"form-label text-muted small fw-bold\">{$cl}</label>
                        <input type=\"{$inputType}\" name=\"{$cn}\" id=\"edit_{$cn}\" class=\"form-control\" {$cr}>
                    </div>";
        }
    }
    
    $title = ucwords(str_replace(['-', '_'], ' ', $file_name));
    
    $searchVars = "";
    foreach($searchCols as $sc) {
        $searchVars .= "                    \$search_{$sc['name']} = sani(\$_GET['search_{$sc['name']}'] ?? '');\n";
    }

    return <<<HTML
<div class="container py-5">
    <div id="crud-alert"></div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="fw-bold text-dark">Manajemen {$title}</h2>
    </div>

    <div class="text-end mb-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"> + Tambah</button>
    </div>
    <div class="card shadow-sm border-0 p-2">
        <?= generateSearchForm({$searchFormArgs}, [
                'text' => 'Cari',
                'col' => 'col-2',
                'class' => 'btn btn-sm btn-primary w-100'
            ]) ?>
            <br>
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
{$thHtml}                        <th class="px-4 py-3 text-end">Aksi</th>
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
                    
                    \$sql = "SELECT * FROM {$table_name} WHERE 1 = 1 \$whereClause ORDER BY created_at DESC";
                    
                    \$paginated = paginationQuery(\$con, \$sql, \$params, \$types, 5, '{$folder_name}/{$file_name}');
                    \$rows = \$paginated['data'];
                    
                    if (\$rows && mysqli_num_rows(\$rows) > 0) {
                        while (\$row = mysqli_fetch_assoc(\$rows)) {
                    ?>
                    <tr>
{$tdHtml}                        <td class="px-4 text-end">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="openEditModal({$editArgs})">
                                Edit
                            </button>
                            <form hx-post="?act={$folder_name}/{$file_name}" hx-target="#crud-alert" hx-confirm="Yakin ingin menghapus data ini?" style="display:inline-block;">
                                <input type="hidden" name="action_type" value="delete">
                                <input type="hidden" name="id" value="<?= \$row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger ms-1">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else {
                        echo '<tr><td colspan="10" class="text-center py-4 text-muted">Data tidak ditemukan.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty(\$paginated['links'])): ?>
        <div class="card-footer bg-white py-3 border-0">
            <?= \$paginated['links'] ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addModalLabel">Tambah Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="?act={$folder_name}/{$file_name}" hx-target="#crud-alert" hx-swap="innerHTML">
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

<!-- MODAL EDIT -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editModalLabel">Edit Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="?act={$folder_name}/{$file_name}" hx-target="#crud-alert" hx-swap="innerHTML">
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

function generateActionContent($file_name, $folder_name, $table_name, $col_names, $col_labels, $col_types, $col_required) {
    
    $varsAssigment = "";
    $reqChecks = [];
    $insertFields = "id";
    $insertPlaceholders = "?";
    $insertVars = "\$id";
    $insertTypes = "s";
    
    $updateFields = [];
    $updateVars = [];
    $updateTypes = "";
    
    foreach($col_names as $idx => $cn) {
        $varsAssigment .= "        \${$cn} = sani(\$_POST['{$cn}'] ?? '');\n";
        
        if ($col_required[$idx] == '1') {
            $reqChecks[] = "empty(\${$cn})";
        }
        
        $insertFields .= ", {$cn}";
        $insertPlaceholders .= ", ?";
        $insertVars .= ", \${$cn}";
        
        $type = "s"; // default string
        if ($col_types[$idx] === 'number') {
            $type = "i"; 
        }
        $insertTypes .= $type;
        
        $updateFields[] = "{$cn}=?";
        $updateVars[] = "\${$cn}";
        $updateTypes .= $type;
    }
    
    $reqChecksStr = empty($reqChecks) ? "false" : implode(" || ", $reqChecks);
    
    $updateFieldsStr = implode(", ", $updateFields);
    $updateVarsStr = implode(", ", $updateVars) . ", \$id";
    $updateTypes .= "s"; // for id
    
    return <<<PHP
<?php
// actions/{$folder_name}/{$file_name}.php
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    \$action_type = \$_POST['action_type'] ?? '';
    
    // ==========================================
    // ACTION: ADD
    // ==========================================
    if (\$action_type === 'add') {
{$varsAssigment}
        if ({$reqChecksStr}) {
            htmxMessage('Kolom wajib harus diisi!', 'danger');
        }
        
        \$id = generate_uuid();
        
        \$success = executeSecure(\$con, 
            "INSERT INTO {$table_name} ({$insertFields}) VALUES ({$insertPlaceholders})",
            [{$insertVars}],
            '{$insertTypes}'
        );
        
        if (\$success) {
            htmxRedirectWithMessage('{$folder_name}/{$file_name}', 'Berhasil menambahkan data!', 'success');
        } else {
            htmxMessage('Gagal menambahkan data.', 'danger');
        }
    }
    
    // ==========================================
    // ACTION: UPDATE
    // ==========================================
    else if (\$action_type === 'update') {
        \$id = sani(\$_POST['id'] ?? '');
{$varsAssigment}
        if (empty(\$id) || ({$reqChecksStr})) {
            htmxMessage('Kolom wajib harus diisi!', 'danger');
        }
        
        \$success = executeSecure(\$con, 
            "UPDATE {$table_name} SET {$updateFieldsStr} WHERE id=?",
            [{$updateVarsStr}],
            '{$updateTypes}'
        );
        
        if (\$success) {
            htmxRedirectWithMessage('{$folder_name}/{$file_name}', 'Berhasil memperbarui data!', 'success');
        } else {
            htmxMessage('Gagal memperbarui data.', 'danger');
        }
    }
    
    // ==========================================
    // ACTION: DELETE
    // ==========================================
    else if (\$action_type === 'delete') {
        \$id = sani(\$_POST['id'] ?? '');
        
        if (empty(\$id)) {
            htmxMessage('ID tidak valid!', 'danger');
        }
        
        \$success = executeSecure(\$con, "DELETE FROM {$table_name} WHERE id = ?", [\$id], 's');
        
        if (\$success) {
            htmxRedirectWithMessage('{$folder_name}/{$file_name}', 'Data berhasil dihapus!', 'success');
        } else {
            htmxMessage('Gagal menghapus data.', 'danger');
        }
    }
}
?>
PHP;
}

function generateApiContent($table_name, $col_names) {
    return <<<PHP
<?php
// api/{$table_name}.php
header("Content-Type: application/json");
require_once __DIR__ . '/../../config.php';

\$method = \$_SERVER['REQUEST_METHOD'];
\$id = \$_GET['id'] ?? null;

switch (\$method) {
    case 'GET':
        if (\$id) {
            \$res = querySecure(\$con, "SELECT * FROM {$table_name} WHERE id = ?", [\$id], 's');
            \$data = mysqli_fetch_assoc(\$res);
            echo json_encode(['status' => 'success', 'data' => \$data]);
        } else {
            \$res = querySecure(\$con, "SELECT * FROM {$table_name} ORDER BY created_at DESC");
            \$data = [];
            while (\$r = mysqli_fetch_assoc(\$res)) {
                \$data[] = \$r;
            }
            echo json_encode(['status' => 'success', 'data' => \$data]);
        }
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}
?>
PHP;
}

function generateSqlContent($table_name, $col_names, $col_types, $col_required) {
    $sql = "CREATE TABLE `{$table_name}` (\n";
    $sql .= "  `id` varchar(36) NOT NULL,\n";
    
    foreach($col_names as $idx => $cn) {
        $type = "VARCHAR(255)";
        if ($col_types[$idx] === 'textarea') $type = "TEXT";
        if ($col_types[$idx] === 'number') $type = "INT(11)";
        if ($col_types[$idx] === 'date') $type = "DATE";
        
        $req = $col_required[$idx] == '1' ? "NOT NULL" : "DEFAULT NULL";
        
        $sql .= "  `{$cn}` {$type} {$req},\n";
    }
    
    $sql .= "  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n";
    $sql .= "  PRIMARY KEY (`id`)\n";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\n";
    
    return $sql;
}
?>
