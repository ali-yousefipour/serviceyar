<?php
declare(strict_types=1);

/**
 * Reconciliation, account history and student contract validation repeatedly
 * access documents through tenant + party identity. Legacy tables were only
 * indexed by tenant/status, resulting in scans of every tenant document.
 *
 * Idempotent and prefix-aware: skip when an equivalent covering index already
 * exists under another name; never delete an operator-managed index.
 */
return static function (PDO $pdo): void {
    foreach ([
        'contracts'=>'idx_contract_party_lookup',
        'invoices'=>'idx_invoice_party_lookup',
    ] as $table=>$index) {
        $q=$pdo->prepare("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS index_columns
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?
            GROUP BY INDEX_NAME");
        $q->execute([$table]);
        $covered=false;
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $cols=(string)($row['index_columns']??'');
            if($cols==='agency_id,party_type,party_id'||str_starts_with($cols,'agency_id,party_type,party_id,')){
                $covered=true;
                break;
            }
        }
        if($covered)continue;
        // Table and index names come exclusively from the constant map above.
        $pdo->exec("ALTER TABLE {$table} ADD INDEX {$index} (agency_id,party_type,party_id)");
    }
};
