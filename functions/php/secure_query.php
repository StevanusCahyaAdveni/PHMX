<?php 
/**
 * Fungsi untuk query yang aman dengan prepared statement
 * 
 * @param mysqli $con Koneksi database
 * @param string $query Query dengan placeholder (?)
 * @param array $params Parameter untuk binding
 * @param string $types Tipe data parameter (s=string, i=integer, d=double, b=blob)
 * @return mysqli_result|bool Hasil query atau false jika gagal
 */
function querySecure($con, $query, $params = [], $types = '')
{
    $stmt = mysqli_prepare($con, $query);

    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($con));
        return false;
    }

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    mysqli_stmt_close($stmt);

    return $result;
}

/**
 * Fungsi untuk INSERT/UPDATE/DELETE yang aman
 * 
 * @param mysqli $con Koneksi database
 * @param string $query Query dengan placeholder (?)
 * @param array $params Parameter untuk binding
 * @param string $types Tipe data parameter
 * @return bool|int True jika berhasil, atau last insert id untuk INSERT
 */
function executeSecure($con, $query, $params = [], $types = '')
{
    $stmt = mysqli_prepare($con, $query);

    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($con));
        return false;
    }

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    $success = mysqli_stmt_execute($stmt);

    // Untuk INSERT, return last insert id jika menggunakan Auto Increment
    // Jika menggunakan UUID (di mana last_insert_id = 0), kembalikan nilai true
    if ($success && stripos($query, 'INSERT') === 0) {
        $lastId = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);
        return $lastId > 0 ? $lastId : true;
    }

    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * ============================================
 * CONTOH PENGGUNAAN QUERY AMAN
 * ============================================
 */

/*
// 1. SELECT dengan WHERE
$email = 'user@example.com';
$result = querySecure($con, 
    "SELECT * FROM users WHERE email = ?", 
    [$email], 
    's'  // s = string
);

if ($result) {
    $user = mysqli_fetch_assoc($result);
    echo $user['name'];
}

// 2. INSERT data
$name = 'John Doe';
$email = 'john@example.com';
$age = 25;

$lastId = executeSecure($con,
    "INSERT INTO users (name, email, age, created_at) VALUES (?, ?, ?, NOW())",
    [$name, $email, $age],
    'ssi'  // s=string, s=string, i=integer
);

if ($lastId) {
    echo "Data berhasil disimpan dengan ID: " . $lastId;
}

// 3. UPDATE data
$name = 'John Updated';
$userId = 1;

$success = executeSecure($con,
    "UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?",
    [$name, $userId],
    'si'  // s=string, i=integer
);

if ($success) {
    echo "Data berhasil diupdate";
}

// 4. DELETE data
$userId = 1;

$success = executeSecure($con,
    "DELETE FROM users WHERE id = ?",
    [$userId],
    'i'  // i=integer
);

if ($success) {
    echo "Data berhasil dihapus";
}

// 5. Query dengan multiple parameters
$search = '%john%';
$minAge = 18;
$maxAge = 50;

$result = querySecure($con,
    "SELECT * FROM users WHERE name LIKE ? AND age BETWEEN ? AND ? ORDER BY created_at DESC",
    [$search, $minAge, $maxAge],
    'sii'  // s=string, i=integer, i=integer
);

while ($row = mysqli_fetch_assoc($result)) {
    echo $row['name'] . ' - ' . $row['age'];
}

// ============================================
// TIPE DATA UNTUK BINDING:
// ============================================
// s = string
// i = integer
// d = double/float
// b = blob (binary)
// 
// Contoh: 'ssi' = string, string, integer
// ============================================
*/

?>