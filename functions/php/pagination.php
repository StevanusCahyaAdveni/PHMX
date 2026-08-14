<?php
/**
 * Fungsi Pagination Universal untuk PHMX (HTMX SPA)
 * Menghasilkan blok HTML Bootstrap 5 untuk pagination.
 * 
 * @param int $totalData Total keseluruhan baris data di database
 * @param int $limit Jumlah data per halaman
 * @param int $currentPage Halaman saat ini (dimulai dari 1)
 * @param string $baseUrlHash Hash URL tujuan (misal: 'users/management')
 */
function generatePagination($totalData, $limit, $currentPage, $baseUrlHash)
{
    if ($totalData <= $limit) {
        return ''; // Tidak perlu pagination jika data lebih sedikit dari limit
    }

    $totalPages = ceil($totalData / $limit);
    $currentPage = max(1, (int)$currentPage);
    $currentPage = min($currentPage, $totalPages);

    // Ambil parameter GET saat ini, buang 'hal' (internal HTMX path), dan 'page'
    $queryParams = $_GET;
    unset($queryParams['hal'], $queryParams['page'], $queryParams['act']);

    // Fungsi kecil pembangun URL Hash
    $buildUrl = function($page) use ($baseUrlHash, $queryParams) {
        $queryParams['page'] = $page;
        $queryStr = http_build_query($queryParams);
        return "#" . ltrim($baseUrlHash, '#') . "?" . $queryStr;
    };

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">';

    // Tombol Prev
    $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
    $prevUrl = ($currentPage > 1) ? $buildUrl($currentPage - 1) : '#';
    $html .= '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="' . $prevUrl . '">Sebelumnya</a></li>';

    // Logika menampilkan maksimal 5 nomor halaman berdekatan
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $buildUrl(1) . '">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = ($i === $currentPage) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $buildUrl($i) . '">' . $i . '</a></li>';
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $buildUrl($totalPages) . '">' . $totalPages . '</a></li>';
    }

    // Tombol Next
    $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextUrl = ($currentPage < $totalPages) ? $buildUrl($currentPage + 1) : '#';
    $html .= '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="' . $nextUrl . '">Selanjutnya</a></li>';

    $html .= '</ul></nav>';

    return $html;
}

/**
 * Fungsi Wrapper Universal untuk mengeksekusi Query Pagination secara instan
 * 
 * @param mysqli $con Koneksi database
 * @param string $sql Kueri SQL dasar (tanpa LIMIT dan OFFSET)
 * @param array $params Array parameter untuk prepared statement
 * @param string $types String tipe data parameter (misal 'ssi')
 * @param int $limit Batas jumlah baris per halaman
 * @param string $baseUrlHash Hash dasar untuk link pagination
 * @return array Berisi 'data' (mysqli_result), 'links' (HTML string), dan metadata lainnya
 */
function paginationQuery($con, $sql, $params = [], $types = "", $limit = 5, $baseUrlHash = '')
{
    // Jika tidak diberikan baseUrlHash, gunakan default hal saat ini
    if (empty($baseUrlHash)) {
        $baseUrlHash = $_GET['hal'] ?? '';
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // 1. Hitung total baris menggunakan Subquery
    $countSql = "SELECT COUNT(*) as total FROM ($sql) as _count_table";
    $countQuery = querySecure($con, $countSql, $params, $types);
    $totalRow = mysqli_fetch_assoc($countQuery);
    $totalData = $totalRow['total'];

    // 2. Tambahkan limit dan offset ke kueri utama
    $finalSql = $sql . " LIMIT ? OFFSET ?";
    $finalParams = $params;
    $finalParams[] = $limit;
    $finalParams[] = $offset;
    $finalTypes = $types . "ii";

    $dataQuery = querySecure($con, $finalSql, $finalParams, $finalTypes);

    // 3. Buat HTML Pagination
    $links = generatePagination($totalData, $limit, $page, $baseUrlHash);

    return [
        'data' => $dataQuery,
        'links' => $links,
        'total' => $totalData,
        'page' => $page,
        'limit' => $limit
    ];
}
?>
