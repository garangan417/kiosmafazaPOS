<?php
// database/pagination_helper.php

/**
 * Helper paginasi SQL reusable.
 * Mengembalikan array dengan struktur SAMA seperti paginateArray() lama,
 * sehingga kode HTML di view TIDAK perlu diubah.
 *
 * @param PDO    $pdo
 * @param string $selectSql  Query SELECT lengkap TANPA LIMIT/OFFSET
 * @param string $countSql   Query COUNT(*) dengan WHERE yang sama
 * @param array  $params     Parameter binding untuk kedua query
 * @param int    $page       Halaman saat ini (1-based)
 * @param int    $perPage    Baris per halaman
 * @return array
 */
function paginateSql(PDO $pdo, string $selectSql, string $countSql, array $params, int $page, int $perPage): array {
    // ---- 1. Hitung total item ----
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalItems = (int) $stmtCount->fetchColumn();

    // ---- 2. Hitung halaman & offset ----
    $page       = max(1, $page);
    $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 1;

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    // ---- 3. Ambil data halaman ini saja ----
    $sql = $selectSql . " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- 4. Return format standar ----
    return [
        'items'        => $items,
        'total_items'  => $totalItems,
        'total_pages'  => $totalPages,
        'current_page' => $page,
        'per_page'     => $perPage,
        'from'         => $totalItems > 0 ? $offset + 1 : 0,
        'to'           => min($offset + $perPage, $totalItems),
    ];
}