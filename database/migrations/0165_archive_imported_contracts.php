<?php
declare(strict_types=1);
use ServiceYar\Domain\ContractDocuments;
use ServiceYar\Auth\AuthException;

return static function(PDO $pdo): void {
    // Reserve numbers above every integer or zero-fraction legacy number before normalization.
    $pdo->exec("INSERT IGNORE INTO contract_number_sequence(id,next_number) VALUES(1,100000)");
    $pdo->exec("UPDATE contract_number_sequence SET next_number=GREATEST(next_number,(SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(contract_no,'.',1) AS UNSIGNED)),99999)+1 FROM contracts WHERE contract_no REGEXP '^[0-9]+([.]0+)?$')) WHERE id=1");
    $last=0;$done=0;$failed=0;
    $q=$pdo->prepare("SELECT c.id FROM contracts c JOIN student_contract_invoices m ON m.contract_id=c.id AND m.agency_id=c.agency_id AND m.student_id=c.party_id WHERE c.id>? AND c.party_type='student' AND (c.rendered_html IS NULL OR c.rendered_html='') ORDER BY c.id LIMIT 100");
    do {
        $q->execute([$last]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id){
            $last=(int)$id;
            try {if(ContractDocuments::archiveImportedStudentContract($last))++$done;}
            catch(AuthException $e){++$failed;echo "Contract {$last}: ".$e->getMessage()."\n";}
        }
    }while(count($ids)===100);
    echo "Archived contracts: {$done}; skipped: {$failed}\n";
    if($failed)throw new RuntimeException('Some imported contracts could not be archived; correct reported source data and rerun.');
};
