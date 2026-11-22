<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

/**
 * Provision the additive audit tables before starting a financial transaction.
 * Existing operational reports fall back gracefully until the schema is ready.
 */
final class VehicleClassSchema
{
    private static ?bool $present=null;

    public static function exists(?PDO $pdo=null): bool {
        if(self::$present!==null)return self::$present;
        $pdo??=Connection::get();
        try {
            foreach(['vehicle_class_changes','vehicle_class_rate_periods','vehicle_class_contract_appendices','vehicle_class_debt_adjustments'] as $table)
                $pdo->query('SELECT id FROM '.$table.' LIMIT 0');
            return self::$present=true;
        } catch(\PDOException $e) {
            if(($e->errorInfo[1]??null)===1146)return self::$present=false;
            throw $e;
        }
    }

    public static function ensure(?PDO $pdo=null): void {
        $pdo??=Connection::get();
        if(self::exists($pdo))return;
        if($pdo->inTransaction())
            throw new AuthException(503,'به‌روزرسانی ساختار تاریخچه خودرو باید پیش از شروع تراکنش انجام شود.','VEHICLE_CLASS_SCHEMA_TRANSACTION');
        $file=dirname(__DIR__,3).'/database/migrations/0168_vehicle_class_change_history.sql';
        $sql=@file_get_contents($file);
        if($sql===false)throw new AuthException(503,'فایل مهاجرت تاریخچه خودرو روی هاست موجود نیست.','VEHICLE_CLASS_MIGRATION_MISSING');
        $sql=preg_replace('/^\s*--[^\r\n]*$/m','',$sql)??$sql;
        foreach(explode(';',$sql) as $statement){
            $statement=trim($statement);
            if($statement==='')continue;
            if(!preg_match('/^CREATE TABLE IF NOT EXISTS [a-z_]+ \(/i',$statement))
                throw new AuthException(503,'دستور غیرمنتظره در مهاجرت تاریخچه خودرو وجود دارد.','INVALID_VEHICLE_CLASS_MIGRATION');
            $pdo->exec($statement);
        }
        self::$present=null;
        if(!self::exists($pdo))
            throw new AuthException(503,'جداول تاریخچه تغییر کلاس به‌طور کامل ایجاد نشدند.','VEHICLE_CLASS_SCHEMA_REQUIRED');
    }
}
