<?php
declare(strict_types=1);

/**
 * Global worker claims the earliest queued SMS using (channel,status,id).
 * Existing idx_messages_agency_status cannot support that query because
 * it starts with agency_id, which the worker does not filter.
 */
return static function (PDO $pdo): void {
    $exists=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages'
          AND INDEX_NAME='idx_messages_sms_queue'");
    $exists->execute();
    if((int)$exists->fetchColumn()!==0)return;
    $pdo->exec('ALTER TABLE messages ADD INDEX idx_messages_sms_queue(channel,status,id)');
};
