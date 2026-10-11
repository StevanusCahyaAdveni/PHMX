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
 * =========================================================================
 * TRANSACTION CONTROL LANGUAGE (TCL) - KONSISTENSI & KEAMANAN DATA (ACID)
 * =========================================================================
 */

/**
 * Mulai transaksi database secara manual
 * 
 * @param mysqli $con Koneksi database
 * @return bool
 */
function beginTransaction($con)
{
    return mysqli_begin_transaction($con);
}

/**
 * Komit perubahan transaksi database secara manual
 * 
 * @param mysqli $con Koneksi database
 * @return bool
 */
function commitTransaction($con)
{
    return mysqli_commit($con);
}

/**
 * Batalkan / rollback transaksi database secara manual
 * 
 * @param mysqli $con Koneksi database
 * @return bool
 */
function rollbackTransaction($con)
{
    return mysqli_rollback($con);
}

/**
 * Mendapatkan pesan kesalahan dari transaksi terakhir yang gagal
 * 
 * @return string|null
 */
function getTransactionError()
{
    return $GLOBALS['last_transaction_error'] ?? null;
}

/**
 * Eksekusi transaksi database secara aman dan otomatis (Auto-Commit & Auto-Rollback).
 * Menjamin integritas data (ACID): jika salah satu query gagal, semua perubahan dibatalkan.
 * 
 * Mendukung 2 cara pemanggilan:
 * 1. Closure / Callback:
 *    transactionSecure($con, function($con) {
 *        executeSecure($con, "UPDATE ...", ...);
 *        executeSecure($con, "INSERT ...", ...);
 *        return true; // Return false atau lempar Exception untuk membatalkan (rollback)
 *    });
 * 
 * 2. Array Query Batch:
 *    transactionSecure($con, [
 *        ['query' => 'INSERT ...', 'params' => [...], 'types' => '...'],
 *        ['query' => 'UPDATE ...', 'params' => [...], 'types' => '...'],
 *    ]);
 * 
 * @param mysqli $con Koneksi database
 * @param callable|array $action Closure callback atau array daftar query yang dieksekusi berurutan
 * @return mixed Return value callback, array hasil eksekusi batch, atau false jika gagal (rollback)
 */
function transactionSecure($con, $action)
{
    $GLOBALS['last_transaction_error'] = null;
    mysqli_begin_transaction($con);

    try {
        if (is_callable($action)) {
            $result = $action($con);

            // Jika callback secara eksplisit mengembalikan false, batalkan transaksi
            if ($result === false) {
                mysqli_rollback($con);
                $GLOBALS['last_transaction_error'] = "Transaksi dibatalkan karena callback mengembalikan nilai false.";
                return false;
            }

            mysqli_commit($con);
            return $result !== null ? $result : true;
        } elseif (is_array($action)) {
            $results = [];

            foreach ($action as $index => $item) {
                $q = $item['query'] ?? ($item['sql'] ?? ($item[0] ?? null));
                $p = $item['params'] ?? ($item[1] ?? []);
                $t = $item['types'] ?? ($item[2] ?? '');

                if (empty($q)) {
                    throw new Exception("Query pada indeks {$index} tidak boleh kosong.");
                }

                $execResult = executeSecure($con, $q, $p, $t);
                if ($execResult === false) {
                    throw new Exception("Query pada indeks {$index} gagal dieksekusi: " . mysqli_error($con));
                }

                $results[] = $execResult;
            }

            mysqli_commit($con);
            return $results;
        } else {
            throw new InvalidArgumentException("Parameter kedua transactionSecure harus berupa callable atau array query.");
        }
    } catch (Throwable $e) {
        mysqli_rollback($con);
        $GLOBALS['last_transaction_error'] = $e->getMessage();
        error_log("Transaction failed: " . $e->getMessage());
        return false;
    }
}

/**
 * ============================================
 * CONTOH PENGGUNAAN QUERY & TRANSAKSI AMAN
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

// 6. Transaksi Otomatis menggunakan Closure (Transfer Saldo / Multi-Query)
$success = transactionSecure($con, function($con) {
    // Langkah 1: Kurangi saldo akun A
    $deduct = executeSecure($con, "UPDATE accounts SET balance = balance - ? WHERE id = ? AND balance >= ?", [100000, 1, 100000], 'dii');
    if (!$deduct) {
        throw new Exception("Saldo akun pengirim tidak mencukupi!");
    }

    // Langkah 2: Tambah saldo akun B
    $add = executeSecure($con, "UPDATE accounts SET balance = balance + ? WHERE id = ?", [100000, 2], 'di');
    if (!$add) {
        throw new Exception("Gagal menambahkan saldo akun penerima!");
    }

    // Langkah 3: Catat mutasi / riwayat
    $logId = executeSecure($con, "INSERT INTO mutations (from_id, to_id, amount, created_at) VALUES (?, ?, ?, NOW())", [1, 2, 100000], 'iid');

    return $logId; // Transaksi sukses, otomatis di-commit
});

if ($success) {
    echo "Transfer berhasil dengan ID mutasi: " . $success;
} else {
    echo "Transfer gagal dan di-rollback: " . getTransactionError();
}

// 7. Transaksi Batch Query (Array)
$batchSuccess = transactionSecure($con, [
    ['query' => "INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)", 'params' => [1, 'Login'], 'types' => 'is'],
    ['query' => "UPDATE users SET last_login = NOW() WHERE id = ?", 'params' => [1], 'types' => 'i']
]);

if ($batchSuccess) {
    echo "Batch transaksi sukses dieksekusi dan di-commit!";
} else {
    echo "Batch transaksi gagal: " . getTransactionError();
}

// 8. Transaksi Manual (Begin, Commit, Rollback)
beginTransaction($con);
try {
    executeSecure($con, "UPDATE inventory SET stock = stock - ? WHERE product_id = ?", [5, 10], 'ii');
    executeSecure($con, "INSERT INTO order_items (order_id, product_id, qty) VALUES (?, ?, ?)", [100, 10, 5], 'iii');
    commitTransaction($con);
    echo "Pesanan berhasil diproses!";
} catch (Throwable $e) {
    rollbackTransaction($con);
    echo "Pesanan gagal: " . $e->getMessage();
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