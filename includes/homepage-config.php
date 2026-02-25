<?php

function ensureHomepageSettingsTable(mysqli $conn): void {
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS homepage_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            hero_image VARCHAR(255) DEFAULT NULL,
            hero_url VARCHAR(500) DEFAULT NULL,
            category_ids TEXT DEFAULT NULL,
            brand_ids TEXT DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqli_query($conn, "INSERT IGNORE INTO homepage_settings (id) VALUES (1)");
}

function getHomepageSettings(mysqli $conn): array {
    ensureHomepageSettingsTable($conn);
    $res = mysqli_query($conn, "SELECT * FROM homepage_settings WHERE id=1 LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ?: [
        'hero_image' => null,
        'hero_url' => null,
        'category_ids' => '',
        'brand_ids' => ''
    ];
}

function csvIdsToArray(?string $csv): array {
    if (!$csv) return [];
    $ids = array_map('intval', explode(',', $csv));
    return array_values(array_filter($ids, fn($id) => $id > 0));
}

function idsArrayToCsv(array $ids): string {
    $clean = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    return implode(',', $clean);
}
