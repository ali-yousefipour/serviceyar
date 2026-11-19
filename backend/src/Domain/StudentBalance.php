<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;

/** Student account: non-cancelled invoices are charges; actual cash remains cash
 * even if a charge is cancelled. Refunds are negative ledger receipts. */
final class StudentBalance {
 public static function yearSql(string $column): string {
  $expr="TRIM($column)";
  foreach(['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','/'=>'-',' '=>'','–'=>'-','—'=>'-'] as $from=>$to)
   $expr="REPLACE($expr,'$from','$to')";
  return "CONVERT($expr USING utf8mb4) COLLATE utf8mb4_unicode_ci";
 }
 public static function invoicesSql(): string {
  return "(SELECT COALESCE(SUM(i.amount),0) FROM invoices i WHERE i.party_type='student' AND i.party_id=students.id AND i.agency_id=students.agency_id AND i.status<>'cancelled')";
 }
 public static function receiptsSql(): string {
  return "((SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id WHERE i.party_type='student' AND i.party_id=students.id AND p.agency_id=students.agency_id) + (SELECT COALESCE(SUM(CASE WHEN le.direction='receipt' THEN le.amount ELSE -le.amount END),0) FROM ledger_entries le WHERE le.agency_id=students.agency_id AND le.party_type='student' AND le.party_id=students.id AND le.is_active=1))";
 }
 public static function totals(float $invoiced,float $received): array {
  $net=round($invoiced-$received,2);
  return ['invoiced'=>$invoiced,'received'=>$received,'balance'=>max(0,$net),'credit'=>max(0,-$net),'net'=>$net];
 }
 /** Allocate cumulative net receipts oldest first; refund excess remains account debt. */
 public static function allocate(array &$invoices,float $received): void {
  $indices=array_keys($invoices);
  usort($indices,static fn($a,$b)=>strcmp((string)($invoices[$a]['issue_date']??''),(string)($invoices[$b]['issue_date']??'')) ?: ((int)$invoices[$a]['id']<=>(int)$invoices[$b]['id']));
  $available=max(0,$received);
  foreach($indices as $index){
   $amount=(float)$invoices[$index]['amount'];$allocated=min($amount,$available);
   $available=round($available-$allocated,2);
   $invoices[$index]['paidAmount']=$allocated;
   $invoices[$index]['remainingAmount']=max(0,round($amount-$allocated,2));
   $invoices[$index]['displayStatus']=$allocated >= $amount?'paid':($allocated>0?'partial':'open');
  }
 }
}
