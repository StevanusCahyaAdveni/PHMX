<div class="container py-5">
    <div id="users-alert"></div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="fw-bold text-dark">Manajemen Pengguna</h2>
    </div>
    <div class="card shadow-sm border-0 mb-2">
        <div class="card-body d-flex justify-content-between align-items-center">
            <p class="mb-0">Selamat datang, <strong><?= htmlspecialchars($_SESSION['fullname']) ?></strong> (<?= htmlspecialchars($_SESSION['role']) ?>)!</p>
        </div>
    </div>

    <div class="text-end mb-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"> + Tambah</button>
    </div>
    <div class="card shadow-sm border-0 p-2">
        <?= generateSearchForm([
                ['name' => 'search_name', 'placeholder' => 'Cari Nama...', 'col' => 'col-5 col-md-6'],
                ['name' => 'search_email', 'placeholder' => 'Cari Email...', 'col' => 'col-5 col-md-5']
            ], [
                'text' => 'Cari',
                'col' => 'col-2 col-md-1',
                'class' => 'btn btn-sm btn-primary'
            ]) ?>
            <br>
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Nama Lengkap</th>
                        <th class="py-3">Username</th>
                        <th class="py-3">Email</th>
                        <th class="py-3">No. Telp</th>
                        <th class="py-3">Role</th>
                        <th class="px-4 py-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Searching Logic
                    $search_name = sani($_GET['search_name'] ?? '');
                    $search_email = sani($_GET['search_email'] ?? '');
                    
                    $whereConditions = [];
                    $params = [];
                    $types = "";
                    
                    if (!empty($search_name)) {
                        $whereConditions[] = "fullname LIKE ?";
                        $params[] = "%{$search_name}%";
                        $types .= "s";
                    }
                    if (!empty($search_email)) {
                        $whereConditions[] = "email LIKE ?";
                        $params[] = "%{$search_email}%";
                        $types .= "s";
                    }
                    
                    $whereClause = "";
                    if (!empty($whereConditions)) {
                        $whereClause = "AND " . implode(" AND ", $whereConditions);
                    }
                    // End Searching Logic
                    
                    $sql = "SELECT * FROM users WHERE 1 = 1 $whereClause ORDER BY created_at DESC";
                    
                    // EKSEKUSI AJAIB (1 Baris)
                    $paginated = paginationQuery($con, $sql, $params, $types, 5, 'users/management');
                    $users = $paginated['data'];
                    
                    if ($users && mysqli_num_rows($users) > 0) {
                        while ($u = mysqli_fetch_assoc($users)) {
                            $id = htmlspecialchars($u['id']);
                            $fn = htmlspecialchars($u['fullname']);
                            $un = htmlspecialchars($u['username']);
                            $em = htmlspecialchars($u['email']);
                            $tl = htmlspecialchars($u['telp_number']);
                            $rl = htmlspecialchars($u['role']);
                    ?>
                    <tr>
                        <td class="px-4"><?= $fn ?></td>
                        <td><?= $un ?></td>
                        <td><?= $em ?></td>
                        <td><?= $tl ?></td>
                        <td><span class="badge bg-<?= $rl === 'admin' ? 'primary' : 'secondary' ?>"><?= $rl ?></span></td>
                        <td class="px-4 text-end">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="openEditModal('<?= $id ?>', '<?= addslashes($fn) ?>', '<?= addslashes($un) ?>', '<?= addslashes($em) ?>', '<?= addslashes($tl) ?>', '<?= addslashes($rl) ?>')">
                                Edit
                            </button>
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <button class="btn btn-sm btn-outline-danger ms-1"
                                    hx-post="?act=users/user-management" 
                                    hx-vals='{"action_type": "delete", "id": "<?= $id ?>"}'
                                    hx-target="#users-alert"
                                    hx-confirm="Yakin ingin menghapus pengguna <?= addslashes($fn) ?>?">
                                Hapus
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else {
                        echo '<tr><td colspan="6" class="text-center py-4 text-muted">Data pengguna tidak ditemukan.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        
        <!-- BAGIAN PAGINATION -->
        <?php if (!empty($paginated['links'])): ?>
        <div class="card-footer bg-white py-3 border-0">
            <?= $paginated['links'] ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL TAMBAH USER -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addModalLabel">Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="?act=users/user-management" hx-target="#users-alert" hx-swap="innerHTML">
                <input type="hidden" name="action_type" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Nama Lengkap</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">No. Telp</label>
                        <input type="text" name="telp_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Peran (Role)</label>
                        <select name="role" class="form-select" required>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT USER -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editModalLabel">Edit Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="?act=users/user-management" hx-target="#users-alert" hx-swap="innerHTML">
                <input type="hidden" name="action_type" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Nama Lengkap</label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">No. Telp</label>
                        <input type="text" name="telp_number" id="edit_telp_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Peran (Role)</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Password Baru (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, fullname, username, email, telp, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_fullname').value = fullname;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_telp_number').value = telp;
    document.getElementById('edit_role').value = role;
    
    // Tampilkan modal
    var editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
}
</script>
