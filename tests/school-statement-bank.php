<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/src/Domain/PayrollDocuments.php';
require_once __DIR__.'/../backend/src/Domain/IranBankDetails.php';
require_once __DIR__.'/../backend/src/Domain/SchoolServiceStatement.php';

use ServiceYar\Domain\IranBankDetails;
use ServiceYar\Domain\SchoolServiceStatement;

if (IranBankDetails::bankName('IR350150000003101054600235') !== 'بانک سپه') throw new RuntimeException('Bank detection failed');
if (IranBankDetails::bankName('IR123412341234123412341234') !== null) throw new RuntimeException('Invalid IBAN accepted');
$s=SchoolServiceStatement::calculate('15625000', '115741', '1361840.298');
if (abs($s['statement_excluding_certificate_insurance'] - 17343750)>0.001) throw new RuntimeException('Statement mismatch');
if (abs($s['parent_total'] - 18821331.298)>0.001) throw new RuntimeException('Parent fee mismatch');
$rate=SchoolServiceStatement::fromWorkbookRate('15625000',30);
if(abs($rate['parent_total']-18821331.298)>0.001)throw new RuntimeException('Insurance and certificate rate mismatch');
$half=SchoolServiceStatement::fromWorkbookRate('15625000',15);
if(abs($half['driver_fee']-7812500)>0.001)throw new RuntimeException('Service-day proration mismatch');
echo "School financial and bank checks passed\n";
