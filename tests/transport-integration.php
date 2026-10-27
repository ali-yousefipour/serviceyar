<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Database\Connection;
use ServiceYar\Domain\DomainService as D;
use ServiceYar\Domain\OperationsService as O;
use ServiceYar\Domain\StudentImport;
use ServiceYar\Auth\AuthException;
use ServiceYar\Support\Uuid;
use ServiceYar\Integrations\BaleClient;
use ServiceYar\Integrations\BalePaymentService;
use ServiceYar\Integrations\NeginSms;
use ServiceYar\Domain\PayrollDocuments;

final class TestBaleClient extends BaleClient {
 public array $calls=[];public array $proof=[];
 public function request(string $method,array $body): mixed{$this->calls[]=[$method,$body];return match($method){'sendInvoice'=>['message_id'=>123],'answerPreCheckoutQuery'=>true,'inquireTransaction'=>$this->proof,default=>throw new RuntimeException('Unexpected provider method')};}
}

// This suite must only ever operate on a dedicated, explicitly opted-in database.
if(getenv('SERVICEYAR_TEST_DATABASE')!=='1'||!str_starts_with((string)config('DB_DATABASE'),'serviceyar_test'))throw new RuntimeException('Set SERVICEYAR_TEST_DATABASE=1 and use a serviceyar_test* database.');
$p=Connection::get();$p->beginTransaction();$checks=0;
function check(bool $condition,string $message): void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function rejected(callable $call,string $code): void{try{$call();}catch(AuthException $e){check($e->codeName===$code,'Expected '.$code.', received '.$e->codeName);return;}throw new RuntimeException('Expected rejection: '.$code);}
function insertFixture(string $table,array $data): int{global $p;$fields=array_keys($data);$p->prepare('INSERT INTO '.$table.' ('.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')')->execute(array_values($data));return (int)$p->lastInsertId();}
try{
 $tag=bin2hex(random_bytes(4));
 $uid=insertFixture('users',['uuid'=>Uuid::v4(),'username'=>'test_'.$tag,'password_hash'=>password_hash('unused-test-password',PASSWORD_DEFAULT)]);
 $viewer=insertFixture('users',['uuid'=>Uuid::v4(),'username'=>'viewer_'.$tag,'password_hash'=>'unused']);
 $role=(int)$p->query("SELECT id FROM roles WHERE name='super_admin'")->fetchColumn();$p->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$uid,$role]);
 $a=insertFixture('agencies',['uuid'=>Uuid::v4(),'name'=>'شرکت آزمایشی الف']);$b=insertFixture('agencies',['uuid'=>Uuid::v4(),'name'=>'شرکت آزمایشی ب']);
 $operator=(int)$p->query("SELECT id FROM roles WHERE name='operator'")->fetchColumn();$p->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$viewer,$operator]);$p->prepare('INSERT INTO agency_users(agency_id,user_id) VALUES(?,?)')->execute([$a,$viewer]);
 $school=D::save($uid,$a,'schools',['name'=>'مدرسه الف']);$otherSchool=D::save($uid,$b,'schools',['name'=>'مدرسه ب']);
 $s1=D::save($uid,$a,'students',['schoolId'=>$school['id'],'firstName'=>'آرمان','lastName'=>'آزمایشی','mobile'=>'09120000001','monthlyFee'=>'1000','latitude'=>36.2,'longitude'=>59.5]);
 $s2=D::save($uid,$a,'students',['schoolId'=>$school['id'],'firstName'=>'مینا','lastName'=>'آزمایشی','mobile'=>'09120000002','monthlyFee'=>'2000']);
 $foreign=D::save($uid,$b,'students',['schoolId'=>$otherSchool['id'],'firstName'=>'دانش','lastName'=>'دیگر','mobile'=>'09120000003']);
 check(!empty($s1['uuid']),'Creation generates UUID');
 rejected(fn()=>D::save($uid,$a,'students',['schoolId'=>$otherSchool['id'],'firstName'=>'x','lastName'=>'x','mobile'=>'09120000000']),'CROSS_SCOPE_REFERENCE');
 rejected(fn()=>D::save($viewer,$a,'students',['schoolId'=>$school['id'],'firstName'=>'x','lastName'=>'x','mobile'=>'09120000000']),'FORBIDDEN');
 rejected(fn()=>D::get($viewer,$b,'students',(int)$foreign['id']),'SCOPE_FORBIDDEN');
 $driver=D::save($uid,$a,'drivers',['firstName'=>'راننده','lastName'=>'یک']);$replacement=D::save($uid,$a,'drivers',['firstName'=>'راننده','lastName'=>'دو']);
 $service=D::save($uid,$a,'services',['title'=>'صبح','schoolId'=>$school['id'],'driverId'=>$driver['id'],'capacity'=>1]);
 D::assignStudent($uid,$a,(int)$service['id'],(int)$s1['id']);check(D::get($uid,$a,'students',(int)$s1['id'])['service_status']==='assigned','Assignment updates service status');
 D::assignStudent($uid,$a,(int)$service['id'],(int)$s1['id'],'نشانی اصلاحی',1);check(count(D::serviceStudents($uid,$a,(int)$service['id']))===1,'Repeated assignment remains unique');
 rejected(fn()=>D::assignStudent($uid,$a,(int)$service['id'],(int)$s2['id']),'CAPACITY_EXCEEDED');
 rejected(fn()=>D::assignStudent($uid,$a,(int)$service['id'],(int)$foreign['id']),'NOT_FOUND');
 rejected(fn()=>D::save($uid,$a,'students',['serviceStatus'=>'waiting'],(int)$s1['id']),'ASSIGNMENT_CONFLICT');
 O::bulkFees($uid,$a,['ids'=>[$s1['id'],$s2['id']],'mode'=>'percent','value'=>10]);check((float)D::get($uid,$a,'students',(int)$s1['id'])['monthly_fee']===1100.0,'Percentage change persisted');
 rejected(fn()=>O::bulkFees($uid,$a,['ids'=>[$s1['id'],$foreign['id']],'mode'=>'set','value'=>5]),'CROSS_SCOPE_REFERENCE');check((float)D::get($uid,$a,'students',(int)$s1['id'])['monthly_fee']===1100.0,'Cross-company bulk write is atomic');
 rejected(fn()=>O::bulkFees($uid,$a,['ids'=>[$s1['id'],$s2['id']],'mode'=>'decrease','value'=>2000]),'VALIDATION_ERROR');check((float)D::get($uid,$a,'students',(int)$s1['id'])['monthly_fee']===1100.0,'Failed bulk write rolls back');
 $date=(new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran')))->format('Y-m-d');D::save($uid,$a,'driver-work',['driverId'=>$driver['id'],'workDate'=>$date,'amount'=>1000]);D::save($uid,$a,'driver-payments',['driverId'=>$driver['id'],'paymentDate'=>$date,'amount'=>200]);D::save($uid,$a,'ledger',['partyType'=>'driver','partyId'=>$driver['id'],'direction'=>'payment','amount'=>100,'entryDate'=>$date]);
 $pay=O::payroll($uid,$a,['from'=>$date,'to'=>$date]);$paid=array_values(array_filter($pay['items'],fn($r)=>(int)$r['id']===(int)$driver['id']))[0];check((float)$paid['balance']===700.0,'Payroll combines earned and real payments');
 $change=D::save($uid,$a,'driver-changes',['serviceId'=>$service['id'],'newDriverId'=>$replacement['id']]);O::changeDriver($uid,$a,(int)$change['id'],'approved');check((int)D::get($uid,$a,'services',(int)$service['id'])['driver_id']===(int)$replacement['id'],'Approved change updates service driver');rejected(fn()=>O::changeDriver($uid,$a,(int)$change['id'],'approved'),'ALREADY_REVIEWED');
 rejected(fn()=>D::save($uid,$a,'driver-changes',['serviceId'=>$service['id'],'newDriverId'=>$driver['id'],'status'=>'approved']),'WORKFLOW_REQUIRED');
 D::removeStudent($uid,$a,(int)$service['id'],(int)$s1['id']);check(D::get($uid,$a,'students',(int)$s1['id'])['service_status']==='waiting','Removal restores waiting status');
 $before=(int)$p->query('SELECT COUNT(*) FROM services')->fetchColumn();rejected(fn()=>O::createGroup($uid,$a,['title'=>'bad','schoolId'=>$school['id'],'capacity'=>2,'ids'=>[$s1['id'],$foreign['id']]]),'NOT_FOUND');check((int)$p->query('SELECT COUNT(*) FROM services')->fetchColumn()===$before,'Failed group creates no orphan service');
 $group=O::createGroup($uid,$a,['title'=>'group','schoolId'=>$school['id'],'capacity'=>2,'ids'=>[$s1['id'],$s2['id']]]);check($group['assigned']===2,'Group creation assigns all students');
 $rows=StudentImport::map([['نام','نام خانوادگی','شماره همراه ولی'],['آزمایش','واردات','۰۹۱۲۰۰۰۰۰۰۴']]);check($rows[0]['mobile']==='09120000004','Import normalizes Persian digits');
 $studentsBefore=(int)$p->query('SELECT COUNT(*) FROM students')->fetchColumn();rejected(fn()=>StudentImport::commit($uid,$a,['schoolId'=>$school['id'],'rows'=>[$rows[0],['firstName'=>'bad','lastName'=>'bad','mobile'=>'bad']]]),'VALIDATION_ERROR');check((int)$p->query('SELECT COUNT(*) FROM students')->fetchColumn()===$studentsBefore,'Import commit rolls back on invalid row');
 $result=StudentImport::commit($uid,$a,['schoolId'=>$school['id'],'rows'=>$rows]);check($result['created']===1,'Valid import persists');
 $d=D::list($uid,$a,'students',['schoolId'=>$school['id'],'serviceStatus'=>'assigned','limit'=>1,'page'=>2]);check($d['pagination']['total']===2&&count($d['items'])===1,'Filtering and paging use same dataset');
 rejected(fn()=>O::queueMessages($uid,$a,['recipientType'=>'student','ids'=>[$s1['id'],$foreign['id']],'body'=>'test']),'CROSS_SCOPE_REFERENCE');
 $messages=O::queueMessages($uid,$a,['recipientType'=>'student','ids'=>[$s1['id']],'body'=>'test']);check($messages['delivery']==='pending_provider','Queue does not claim delivery');
 check(O::validDate('2024-02-29')&&!O::validDate('2025-02-29'),'Dates validate real calendar days');check(O::distance(['latitude'=>0,'longitude'=>0],['latitude'=>0,'longitude'=>0])===0.0,'Zero distance');
 $summary=O::summary($uid,$a);check($summary['counts']['schools']===1&&$summary['counts']['students']===3,'Dashboard aggregates tenant data');
 $docs=O::saveDocument($uid,$a,(int)$driver['id'],['documentType'=>'گواهینامه','documentNo'=>'demo','expiresAt'=>'2027-01-01']);check(count(O::documents($uid,$a,(int)$driver['id']))===1,'Driver document persists');
 rejected(fn()=>O::saveDocument($uid,$b,(int)$driver['id'],['documentType'=>'x']),'CROSS_SCOPE_REFERENCE');
 D::delete($uid,$a,'services',(int)$group['service']['id']);check(D::get($uid,$a,'students',(int)$s1['id'])['service_status']==='waiting','Deactivating service synchronizes student status');
 D::save($uid,$a,'services',['isActive'=>1],(int)$group['service']['id']);check(D::get($uid,$a,'students',(int)$s1['id'])['service_status']==='assigned','Restoring service synchronizes student status');
 rejected(fn()=>D::save($uid,$a,'services',['capacity'=>1],(int)$group['service']['id']),'CAPACITY_EXCEEDED');
 $invoice=D::save($uid,$a,'invoices',['title'=>'فاکتور آزمایشی','amount'=>1234,'partyType'=>'student','partyId'=>$s1['id']]);$client=new TestBaleClient();
 $intent=BalePaymentService::create($uid,$a,['invoiceId'=>$invoice['id'],'chatId'=>'99'],$client);$repeat=BalePaymentService::create($uid,$a,['invoiceId'=>$invoice['id'],'chatId'=>'99'],$client);check($repeat['alreadyPending']===true&&count($client->calls)===1,'Repeated payment creation does not resend invoice');
 $q=$p->prepare('SELECT payload FROM payment_intents WHERE id=?');$q->execute([$intent['intent']['id']]);$payload=$q->fetchColumn();$tx='tx_'.$tag;
 $event=['id'=>$tx,'from'=>['id'=>99],'currency'=>'IRR','total_amount'=>1234,'invoice_payload'=>$payload];$pre=BalePaymentService::webhook(['pre_checkout_query'=>$event],$client);check($pre['preCheckout']===true,'Matching precheckout accepted');
 $bad=$event;$bad['total_amount']=2;$pre=BalePaymentService::webhook(['pre_checkout_query'=>$bad],$client);check($pre['preCheckout']===false,'Tampered amount rejected before checkout');
 $payment=['currency'=>'IRR','total_amount'=>1234,'invoice_payload'=>$payload,'telegram_payment_charge_id'=>$tx,'provider_payment_charge_id'=>'reference'];$client->proof=['id'=>$tx,'status'=>'pending','amount'=>1234,'userID'=>99];
 rejected(fn()=>BalePaymentService::webhook(['message'=>['successful_payment'=>$payment]],$client),'PAYMENT_UNVERIFIED');
 $client->proof['status']='paid';$client->proof['amount']=3;rejected(fn()=>BalePaymentService::webhook(['message'=>['successful_payment'=>$payment]],$client),'PAYMENT_MISMATCH');
 $client->proof['amount']=1234;$settled=BalePaymentService::webhook(['message'=>['successful_payment'=>$payment]],$client);$again=BalePaymentService::webhook(['message'=>['successful_payment'=>$payment]],$client);check($settled['paid']&&$again['duplicate']&&$again['paymentId']===$settled['paymentId'],'Verified callbacks settle exactly once');
 check(D::get($uid,$a,'invoices',(int)$invoice['id'])['status']==='paid','Verified payment closes invoice');
 $q=$p->prepare('SELECT COUNT(*) FROM payments WHERE invoice_id=?');$q->execute([$invoice['id']]);check((int)$q->fetchColumn()===1,'Callback replay creates no duplicate payment');
 rejected(fn()=>BalePaymentService::create($viewer,$a,['invoiceId'=>$invoice['id'],'chatId'=>'99'],$client),'FORBIDDEN');
 check(NeginSms::result((object)['SendSmsResult'=>-123])['accepted']===true,'Negin negative success code recognized');
 check(NeginSms::result((object)['SendSmsResult'=>12])['accepted']===false,'Negin positive error code rejected');
 check(NeginSms::result((object)['SendSmsResult'=>0,'pMessageIds'=>(object)['long'=>[1001]]])['id']==='1001','Negin provider ID preserved');
 $cash=O::ledgerReport($uid,$a,['from'=>$date,'to'=>$date]);$studentCash=array_values(array_filter($cash['items'],fn($r)=>$r['party_type']==='student'&&(int)$r['party_id']===(int)$s1['id']))[0];check((float)$studentCash['receipts']===1234.0,'Financial report includes verified invoice receipts');
 $driverCash=array_values(array_filter($cash['items'],fn($r)=>$r['party_type']==='driver'&&(int)$r['party_id']===(int)$driver['id']))[0];check((float)$driverCash['payments']===300.0,'Financial report includes driver payments and ledger');
 $row=D::save($uid,$a,'ledger',['partyType'=>'driver','partyId'=>$driver['id'],'direction'=>'receipt','amount'=>10,'entryDate'=>$date]);D::delete($uid,$a,'ledger',(int)$row['id']);$visible=D::list($uid,$a,'ledger',['active'=>'1']);check(!in_array($row['id'],array_column($visible['items'],'id')),'Inactive ledger entries are hidden by active filter');
 $zipFile=tempnam(sys_get_temp_dir(),'sy-import-');$zip=new ZipArchive();$zip->open($zipFile,ZipArchive::CREATE|ZipArchive::OVERWRITE);
 $zip->addFromString('xl/sharedStrings.xml','<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>firstName</t></si><si><t>lastName</t></si><si><t>mobile</t></si><si><t>آزمایش</t></si><si><t>اکسل</t></si><si><t>09120000333</t></si></sst>');
 $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row><row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2" t="s"><v>4</v></c><c r="C2" t="s"><v>5</v></c></row></sheetData></worksheet>');$zip->close();
 try{$reader=new ReflectionMethod(StudentImport::class,'xlsx');$mapped=StudentImport::map($reader->invoke(null,$zipFile));check($mapped[0]['mobile']==='09120000333','XLSX shared strings preserve leading zero');}finally{unlink($zipFile);}
 $bom=StudentImport::map([["\xEF\xBB\xBFfirstName",'lastName','mobile'],['x','y','09120000444']]);check($bom[0]['firstName']==='x','CSV BOM is normalized');
 check(O::schoolReport($uid,$a)['items'][0]['students']==3,'School report aggregates actual students');
 $doc=PayrollDocuments::create($uid,$a,['from'=>$date,'to'=>$date,'lines'=>[['driverId'=>$driver['id'],'amount'=>700]]]);check(PayrollDocuments::get($uid,$a,$doc['id'])['status']==='draft','Payroll snapshot is a draft, not a payment');
 rejected(fn()=>PayrollDocuments::create($uid,$a,['from'=>$date,'to'=>$date,'lines'=>[['driverId'=>$driver['id'],'amount'=>700]]]),'PAYROLL_EXISTS');
 PayrollDocuments::update($uid,$a,$doc['id'],['lines'=>[['driverId'=>$driver['id'],'amount'=>750]]]);check((float)PayrollDocuments::get($uid,$a,$doc['id'])['total_amount']===750.0,'Draft payroll amounts can be corrected');
 rejected(fn()=>PayrollDocuments::get($uid,$b,$doc['id']),'NOT_FOUND');PayrollDocuments::approve($uid,$a,$doc['id']);rejected(fn()=>PayrollDocuments::update($uid,$a,$doc['id'],['lines'=>[['driverId'=>$driver['id'],'amount'=>800]]]),'PAYROLL_LOCKED');
 check(PayrollDocuments::validIban('IR490000000000000000000000')&&!PayrollDocuments::validIban('IR480000000000000000000000'),'Bank export enforces IBAN checksum');
 rejected(fn()=>D::save($uid,$a,'payments',['amount'=>1],(int)$settled['paymentId']),'VERIFIED_PAYMENT_LOCKED');
 rejected(fn()=>D::save($uid,$a,'payments',['amount'=>1,'paymentDate'=>$date,'method'=>'bale','invoiceId'=>$invoice['id']]),'WORKFLOW_REQUIRED');
 $q=$p->prepare("UPDATE messages SET status='sent' WHERE agency_id=?");$q->execute([$a]);$message=D::list($uid,$a,'messages',[])['items'][0];rejected(fn()=>D::save($uid,$a,'messages',['status'=>'queued'],(int)$message['id']),'MESSAGE_LOCKED');
 $secondSchool=D::save($uid,$a,'schools',['name'=>'مدرسه دوم همین شرکت']);$secondStudent=D::save($uid,$a,'students',['schoolId'=>$secondSchool['id'],'firstName'=>'دانش','lastName'=>'مدرسه دوم','mobile'=>'09120000777']);
 $multi=D::save($uid,$a,'services',['title'=>'چندمدرسه‌ای','schoolId'=>$school['id'],'capacity'=>2]);
 rejected(fn()=>O::setServiceSchools($uid,$a,(int)$multi['id'],['schoolIds'=>[$school['id'],$otherSchool['id']]]),'CROSS_SCOPE_REFERENCE');
 O::setServiceSchools($uid,$a,(int)$multi['id'],['schoolIds'=>[$school['id'],$secondSchool['id']]]);check(count(O::serviceSchools($uid,$a,(int)$multi['id']))===2,'Service supports multiple schools');
 O::assign($uid,$a,(int)$multi['id'],(int)$secondStudent['id']);check(D::get($uid,$a,'students',(int)$secondStudent['id'])['service_status']==='assigned','Secondary-school student can be assigned');
 rejected(fn()=>O::setServiceSchools($uid,$a,(int)$multi['id'],['schoolIds'=>[$school['id']]]),'SCHOOL_MISMATCH');
 $eligible=D::list($uid,$a,'students',['eligibleServiceId'=>$multi['id']]);check(!in_array($foreign['id'],array_column($eligible['items'],'id')),'Multi-school eligibility remains tenant-scoped');
 check(count(\ServiceYar\Integrations\MapService::points($uid,$a,(int)$multi['id'])['schools'])===2,'Map includes all service schools');
 D::delete($uid,$a,'students',(int)$secondStudent['id']);check(count(D::serviceStudents($uid,$a,(int)$multi['id']))===0,'Deactivating a student releases occupied capacity');
 D::save($uid,$a,'students',['isActive'=>1],(int)$secondStudent['id']);check(count(D::serviceStudents($uid,$a,(int)$multi['id']))===0,'Restoring a student does not silently reassign a route');
 echo "Transport integration: $checks checks passed. All fixtures rolled back.\n";
}finally{if($p->inTransaction())$p->rollBack();}
