<?php
declare(strict_types=1);

/**
 * One-off administrative correction of displayed contract issue dates to 1405/07/01.
 * Original issuance metadata and the original printable document are retained verbatim
 * in a dedicated audit/rollback table. Service periods, financial amounts and payments
 * are never changed.
 *
 * 1405/07/01 Jalali = 2026-09-23 Gregorian.
 */
return static function (\PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contract_issue_date_1405_backup (
        contract_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        agency_id BIGINT UNSIGNED NOT NULL,
        original_issued_at DATETIME NULL,
        original_snapshot_json LONGTEXT NULL,
        original_rendered_html LONGTEXT NULL,
        backed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_contract_issue_date_1405_agency (agency_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $date = '۱۴۰۵/۰۷/۰۱';
    $sqlDate = '2026-09-23 00:00:00';
    $pdo->beginTransaction();
    try {
        // INSERT IGNORE makes retry safe after an interrupted run, without losing the first preimage.
        $pdo->exec("INSERT IGNORE INTO contract_issue_date_1405_backup
            (contract_id,agency_id,original_issued_at,original_snapshot_json,original_rendered_html)
            SELECT id,agency_id,issued_at,snapshot_json,rendered_html FROM contracts");

        $rows = $pdo->query('SELECT id,snapshot_json,rendered_html FROM contracts ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE contracts SET issued_at=?,snapshot_json=?,rendered_html=? WHERE id=?');
        foreach ($rows as $row) {
            $snapshot = $row['snapshot_json'];
            if ($snapshot !== null && $snapshot !== '') {
                $decoded = json_decode((string)$snapshot, true);
                if (is_array($decoded)) {
                    if (!isset($decoded['values']) || !is_array($decoded['values'])) $decoded['values'] = [];
                    $decoded['values']['issueDate'] = $date;
                    $snapshot = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
                }
            }
            $html = $row['rendered_html'];
            if ($html !== null && $html !== '') {
                // Only replace a date explicitly labelled as issue date. Never replace
                // start/end dates, payment dates or other historical dates in contract text.
                $html = preg_replace(
                    '/(تاریخ(?:\\s+صدور)?\\s*[:：]\\s*)(?:[۰-۹0-9٠-٩]{4}[\\/\\-][۰-۹0-9٠-٩]{1,2}[\\/\\-][۰-۹0-9٠-٩]{1,2}|نامشخص)/u',
                    '$1'.$date,
                    (string)$html
                );
                if ($html === null) throw new \RuntimeException('Invalid UTF-8 in contract HTML id='.$row['id']);
            }
            $update->execute([$sqlDate,$snapshot,$html,(int)$row['id']]);
        }
        $pdo->commit();
        echo "Contract issue-date display correction completed. Original records retained in contract_issue_date_1405_backup.\n";
    } catch (\Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
};
