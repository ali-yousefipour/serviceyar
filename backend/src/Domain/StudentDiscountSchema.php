<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

/** Fail closed for financial writes; read-only legacy reports remain available. */
final class StudentDiscountSchema
{
    public static function exists(?PDO $pdo=null): bool
    {
        $pdo??=Connection::get();
        try {
            $pdo->query('SELECT id FROM student_discount_financial_effects LIMIT 0');
            return true;
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1]??0)===1146) return false;
            throw $e;
        }
    }
    public static function requireReady(PDO $pdo): void
    {
        foreach (['student_discounts','student_discount_audit_logs',
            'student_discount_financial_effects','student_discount_contract_appendices'] as $table) {
            try {$pdo->query('SELECT id FROM '.$table.' LIMIT 0');}
            catch (\PDOException $e) {
                if ((int)($e->errorInfo[1]??0)===1146)
                    throw new AuthException(503,'ابتدا مهاجرت‌های ۰۱۶۹ و ۰۱۷۰ تخفیف را اجرا کنید.','STUDENT_DISCOUNT_SCHEMA_REQUIRED');
                throw $e;
            }
        }
    }
}
