<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/**
 * A contract is issued once and its rendered document is never recomputed.
 * Template publishing appends a version; earlier versions are immutable.
 */
final class ContractDocuments {
 private static function scope(int $uid,int $agencyId,bool $manage=false): void {
  Authorization::requirePermission($uid,$manage?'services.manage':'services.view');
  Authorization::requireAgencyAccess($uid,$agencyId);
 }
 private static function ensureSchema(PDO $pdo): void {
  try {
   $pdo->query('SELECT id FROM contract_templates LIMIT 0');
   $pdo->query('SELECT rendered_html,snapshot_json FROM contracts LIMIT 0');
  } catch(\PDOException $error) {
   throw new AuthException(503,'ابتدا مهاجرت دیتابیس 0107_versioned_contract_documents.sql را اجرا کنید.','CONTRACT_SCHEMA_REQUIRED');
  }
 }
 public static function defaultHtml(string $kind): string {
  if($kind==='driver')return <<<'HTML'
<h1>قرارداد ارائه خدمات حمل‌ونقل دانش‌آموزی با راننده</h1>
<p class="center">شماره: {{contractNo}} | تاریخ صدور: {{issueDate}}</p>
<h2>ماده ۱ ـ طرفین قرارداد</h2>
<p>این قرارداد فی‌مابین شرکت {{companyName}} به نمایندگی {{managerName}} به نشانی {{companyAddress}} و راننده آقای/خانم {{driverName}} به شماره ملی {{driverNationalCode}} و شماره همراه {{driverMobile}} منعقد می‌گردد.</p>
<h2>ماده ۲ ـ موضوع قرارداد</h2>
<p>ارائه خدمات حمل‌ونقل دانش‌آموزی مطابق سرویس‌های تخصیص‌یافته، مقررات مرتبط و گواهی صلاحیت معتبر راننده.</p>
<h2>ماده ۳ ـ مدت و مبلغ قرارداد</h2>
<p>شروع: {{startDate}} | پایان: {{endDate}} | مبلغ توافق‌شده: {{totalAmount}} ریال.</p>
<h2>ماده ۴ ـ تعهدات راننده</h2>
<p>رعایت زمان‌بندی، ظرفیت مجاز خودرو، مقررات ایمنی، بیمه، گواهی صلاحیت و ضوابط خدمت‌رسانی به دانش‌آموزان الزامی است.</p>
<h2>ماده ۵ ـ تعهدات شرکت</h2>
<p>هماهنگی سرویس، نظارت بر اجرای تعهدات و تسویه مبالغ بر اساس مفاد قرارداد و مقررات جاری بر عهده شرکت است.</p>
<p class="signatures">نام و امضای راننده: {{driverName}}　　مهر و امضای شرکت: {{managerName}}</p>
HTML;
  // Text of articles 2 and 5-12 is transcribed from the two-page user-supplied reference.
  return <<<'HTML'
<div class="contract-top"><strong>قرارداد معین‌شده حمل‌ونقل دانش‌آموزان</strong><span>شماره: {{contractNo}}　 تاریخ: {{issueDate}}</span></div>
<h2>مقدمه:</h2>
<p>قرارداد حاضر بر اساس پیمان منعقده فیمابین سازمان مدیریت و نظارت بر تاکسیرانی شهرداری مشهد مقدس و طرف دوم موصوف در ماده یک و در اجرای بند 9 از ماده 1 آئیننامه اجرایی حملونقل دانشآموزان مصوبه شماره /105024 ت 61289 هیأت وزیران و به منظور ساماندهی ،هماهنگی و یکپارچگی ضوابط در ارائه خدمات حملونقل دانشآموزان در سال تحصیلی ،{{academicYear}}بین طرفین به شرح ذیل منعقد میگردد.</p>
<h2>ماده ۱- طرفین قرارداد:</h2>
<p>طرف اول: آقای/خانم {{guardianName}} فرزند {{guardianFatherName}} با کد ملی {{guardianNationalCode}} به نشانی مشهد مقدس، {{guardianAddress}} با شماره همراه {{guardianMobile}} که از این پس در قرارداد <strong>ولی دانش‌آموز</strong> خوانده می‌شود.</p>
<p>تبصره ۱: مشخصات دانش‌آموز به شرح ذیل می‌باشد: نام و نام خانوادگی: {{studentName}}　کد ملی: {{studentNationalCode}}　نام مدرسه: {{schoolName}}　ناحیه: {{schoolRegion}}</p>
<p>طرف دوم: شرکت {{companyName}} به شماره ثبت {{companyRegistration}} با شناسه ملی {{companyNationalId}} به نمایندگی آقای/خانم {{managerName}} با کد ملی {{managerNationalCode}} به عنوان مدیرعامل/رئیس هیأت مدیره به نشانی مشهد مقدس، {{companyAddress}} با شماره تماس {{companyPhone}} که به اختصار شرکت نامیده می‌گردد.</p>
<h2>ماده ۲- موضوع قرارداد:</h2>
<p>ارائه خدمات حملونقل دانشآموز/دانشآموزان برابر ضوابط مندرج در آئیننامه اجرایی حملونقل دانشآموزی در ساعات رسمی آموزشی و مطابق با فرم ارائه خدمات پیوست با پرداخت بهای ارائه خدمات حملونقل دانشآموزی وفق مصوبه شماره /6/1404/20084 ش مورخه 1404/11/14 شورای اسلامی شهر مشهد مقدس.</p>
<h2>ماده ۳- مدت قرارداد:</h2>
<p>مدت قرارداد از تاریخ {{startDate}} به مدت {{durationMonths}} ماه شمسی می‌باشد.</p>
<h2>ماده ۴- مبلغ قرارداد:</h2>
<p>مبلغ کل قرارداد برای ارائه خدمات حمل‌ونقل دانش‌آموزی بر مبنای مسافت {{distanceKm}} کیلومتر (میانگین مسافت رفت و برگشت) معادل {{totalAmount}} ریال می‌باشد که مستند به سوابق مندرج در سامانه سپند و فرم ارائه خدمات (پیوست) و بهای ارائه خدمات حمل‌ونقل دانش‌آموزی (مشتمل بر نرخ خرید خدمات راننده به همراه هزینه‌های بالاسری از جمله آموزش، تجهیز ناوگان به علائم ممیزه و غیره) که توسط ولی دانش‌آموز به حساب شرکت پرداخت می‌گردد.</p>
<p>تبصره :2 در صورت درخواست ولی دانشآموز به دریافت ارائه خدمات در صرفاً یک مسیر)رفت یا برگشت( حداکثر تا میزان %70 بهای خدمات وفق مصوبه شورای اسلامی شهر مشهد مقدس قابل دریافت خواهد بود.</p>
<p>تبصره :3 دریافت هرگونه وجوهاتی خارج از مصوبه شورای اسلامی شهر مشهد مقدس از ولی دانشآموز برای ارائه خدمات موضوع قرارداد مجاز نمیباشد.</p>
<h2>ماده ۵- نحوه پرداخت:</h2>
<p>الف :پرداخت مبلغ {{payment40}}ریال)حداقل معادل %40 مبلغ کل قرارداد( به صورت نقد در زمان انعقاد قرارداد صرفاً در وجه شرکت به شماره حساب {{bankAccount}}نزد بانک {{bankName}}واریز و رسید مربوطه پیوست قرارداد گردد.</p>
<p>ب :پرداخت مبلغ {{payment20}}ریال)حداقل معادل %20 مبلغ کل قرارداد( به صورت واریز به حساب یا چک صیادی در بازه زمانی نیمه ابتدایی آبانماه سال جاری صرفاً در وجه شرکت به شماره حساب {{bankAccount}}نزد بانک {{bankName}}پرداخت و رسید مربوطه تحویل شرکت گردد.</p>
<p>ج :پرداخت مبلغ {{payment20}}ریال)حداقل معادل %20 مبلغ کل قرارداد( به صورت واریز به حساب یا چک صیادی در بازه زمانی نیمه ابتدایی آذرماه سال جاری صرفاً در وجه شرکت به شماره حساب {{bankAccount}}نزد بانک {{bankName}}پرداخت و رسید مربوطه تحویل شرکت گردد.</p>
<p>د :پرداخت مبلغ {{payment20}}ریال)حداقل معادل %20 مبلغ کل قرارداد( به صورت واریز به حساب یا چک صیادی در بازه زمانی نیمه ابتدایی بهمنماه سال جاری صرفاً در وجه شرکت به شماره حساب {{bankAccount}}نزد بانک {{bankName}}پرداخت و رسید مربوطه تحویل شرکت گردد.</p>
<h2>ماده -6 تعهدات ولی دانشآموز:</h2>
<p>-6-1 پرداخت به موقع مبلغ قرارداد مطابق شرایط توافق شده در ماده پنج</p>
<p>-6-2 حضور به موقع دانشآموز در مبداء ارائه خدمات)در زمان رفت(</p>
<p>-6-3 حضور و تحویلگیری دانشآموز در مقصد ارائه خدمات)در زمان برگشت(</p>
<p>-6-4 نصب نرم افزار سامانه سپند و مشارکت در نظارت بر حسن انجام کار شرکت</p>
<p>-6-5 کنترل و نظارت بر ارائه خدمات توسط راننده دارای گواهی صلاحیت معتبر)صادر شده از سامانه سپند(</p>
<p>-6-6 اعلام عدم حضور دانشآموز در مبداء)منزل( حداقل یک ساعت پیش از موعد ارائه خدمت</p>
<p>-6-7 تأکید بر رعایت نکات بهداشتی ،استفاده از تجهیزات ایمنی خودرو ،عدم مکالمه غیرضروری با راننده و رعایت شئونات اخلاقی و اسلامی توسط دانشآموز تحت سرپرستی</p>
<p>-6-8 ثبت گزارش تخلفات و شکایات احتمالی از شرکت در سامانه سپند</p>
<h2>ماده -7 تعهدات شرکت:</h2>
<p>-7-1 تامین راننده و ناوگان دانشآموزی دارای گواهی صلاحیت معتبر مطابق با ضوابط مندرج در آئیننامه اجرایی حملونقل دانشآموزان</p>
<hr>
<p>-7-2 جایگزینی راننده و ناوگان دانشآموزی صرفاً با هماهنگی ولی دانشآموز و با شرط دارا بودن گواهی صلاحیت معتبر</p>
<p>تبصره :4 در صورت تخلف ،مطابق ضوابط و مقررات انضباطی اعمال قانون خواهد گردید.</p>
<p>-7-3 تخصیص ناوگان دانشآموزی با رعایت حداکثر یک ساعت مدت زمان رفت و برگشت دانشآموز</p>
<p>-7-4 برنامهریزی به مقصد رسیدن دانشآموز حداقل 5 و حداکثر 15 دقیقه پیش از شروع فعالیت مدرسه و در دسترس بودن حداکثر 15 دقیقه پس از پایان فعالیت مدرسه</p>
<p>-7-5 ممنوعیت سوار نمودن افراد غیر در زمان ارائه خدمات</p>
<p>-7-6 رعایت تعداد سرنشینان مطابق با کارت خودرو در زمان ارائه خدمات</p>
<p>-7-7 اخذ بیمه حوادث)خارج از مدرسه( و مسئولیت سرنشین برای ناوگان</p>
<p>-7-8 نظارت بر رعایت نکات بهداشتی ،استفاده از تجهیزات ایمنی خودرو ،عدم مکالمه غیرضروری راننده با دانشآموز ،رعایت شئونات اخلاقی و سایر ضوابط ارائه خدمات دانشآموزی</p>
<p>-7-9 اعلام غیبت راننده به ولی دانشآموز و جایگزین نمودن ناوگان دانشآموزی دارای گواهی صلاحیت بدون مطالبه وجه مازاد از ایشان</p>
<p>تبصره :5 در صورت عدم معرفی ناوگان دانشآموزی جایگزین ،ولی دانشآموز مجاز است نسبت به تأمین خودروی جایگزین اقدام نموده و هزینه مربوطه را با %20 خسارت از شرکت مطالبه نماید .بدیهی است در این شرایط ،تمامی مسئولیتهای مترتب از انتخاب خودروی جایگزین بر عهده ولی دانشآموز خواهد بود.</p>
<p>-7-10 عودت به موقع وجوه مرتبط با عدم ارائه خدمات در ایام برگزاری غیرحضوری مدارس)پس از کسر هزینههای بالاسری( وفق شرایط مندرج در ماده 11 قرارداد</p>
<p>-7-11 عدم مجوز واگذاری موضوع قرارداد به اشخاص دیگر بصورت کلی یا جزئی)اعم از حقیقی یا حقوقی(</p>
<p>-7-12 راهنمایی اولیا جهت بهرهمندی اختیاری و صرفا رایگان از نرمافزارهای جانبی در خصوص سرویسمدارس)مورد استفاده شرکت(</p>
<h2>ماده -8 تضمین قرارداد: به منظور حسن انجام تعهدات مندرج در قرارداد ،شرکت تضامین معتبر در اختیار مرجع صدور گواهی صلاحیت قرار داده است .بدیهی در صورت اجرای صحیح و به موقع تعهدات مندرج در قرارداد و تأیید ناظر قرارداد ،تضامین در پایان دوره به ایشان عودت داده خواهد گردید .در غیر اینصورت کلیه خسارات وارده احتمالی با تأیید دستگاه نظارت از محل تضامین دریافتی جبران میگردد.</h2>
<h2>ماده -9 ناظر قرارداد: سازمان مدیریت و نظارت بر تاکسیرانی شهرداری مشهد مقدس ناظر قرارداد و مرجع حل اختلاف طرفین میباشد.</h2>
<h2>ماده -10 خاتمه قرارداد:</h2>
<p>-10-1 درصورت عدم انجام پرداخت به موقع تمام یا قسمتی از تعهدات وفق ماده 5 قرارداد ،شرکت ضمن اعلام کتبی موضوع به سازمان ،مجاز به عدم سرویس دهی و پیگیری وصول مطالبات از طریق مراجع قضائی خواهد بود.</p>
<p>-10-2 در صورت انصراف ولی دانشآموز از دریافت خدمات حملونقل دانشآموزی در ماه اول استفاده از سرویس %20 ،و در ماههای آتی به ازای هر ماه %10 مازاد از مبلغ قرارداد کسر و مابقی ظرف حداکثر مدت یکماه از تاریخ اعلام انصراف به ایشان مسترد میگردد.</p>
<p>تبصره :6 در صورت انصراف ،هزینه تعداد روزهای استفاده از سرویس در آن ماه از مبلغ پرداختی به ولی دانش آموز کسر می گردد.</p>
<p>تبصره :7 درصورت انصراف ولی دانشآموز بعد از پایان اسفندماه ،هیچگونه وجهی به ایشان عودت نخواهد گردید.</p>
<h2>ماده -11 حوادث قهری: هرگاه اجرای تمام یا بخشی از تعهدات هریک از طرفین بهعلت بروز هرگونه حادثه خارج از اراده و کنترل از جمله جنگ اما نه صرفا محدود به حوادث طبیعی مانند زلزله ،سیل ،طوفان ،آتشسوزی گسترده ،بیماریهای همهگیر ،جنگ ،شورش ،اعتصابهای سراسری ،تحریمها و مقررات ناگهانی دولتی بهطور موقت یا دائم غیرممکن گردد و منجر به برگزاری کلاس های مدارس به صورت غیر حضوری )مجازی( شود ،جزء حوادث قهری به شمار می رود و در صورت بروز آنها ،حقالزحمه رانندگان ناوگان حملونقل دانشآموزی به طریق ذیل محاسبه و پرداخت خواهد گردید:</h2>
<p>oدر صورتیکه کمتر از سی درصد مجموع روزهای آموزشی هر ماه کلاسها به صورت غیرحضوری برگزار شود ،حقالزحمه رانندگان بصورت کامل پرداخت شود.</p>
<p>oدر صورتیکه بیش از سی درصد مجموع روزهای آموزشی هر ماه کلاسها به صورت غیرحضوری برگزار شود ،حقالزحمه رانندگان به تناسب برگزاری حضوری کلاسها محاسبه و پرداخت شود.</p>
<p>تبصره :8 نسبت برگزاری حضوری کلاسها از سوی دستگاه نظارت محاسبه و بصورت کتبی به شرکت ابلاغ میگردد.</p>
<h2>ماده -12 نسخ قرارداد: این قرارداد مشتمل بر دوازده ماده و هشت تبصره در دو نسخه منعقد گردیده که هر دو نسخه پس از امضای طرفین حکم واحد را داشته و لازمالاجراء میباشد.</h2>
<div class="signatures"><div>ولی دانش‌آموز: {{guardianName}}<br>امضا:</div><div>نام و نام خانوادگی صاحب امضاء مجاز: {{managerName}}<br>نام شرکت: {{companyName}}<br>مهر و امضاء:</div></div>
HTML;
 }
 private static function cleanHtml(string $html): string {
  // Fixed semantic tags only, stripped of all attributes (including event handlers).
  $html=strip_tags($html,'<h1><h2><h3><p><div><span><strong><b><em><i><ul><ol><li><table><thead><tbody><tr><td><th><br><hr>');
  return preg_replace('/<([a-z][a-z0-9]*)(?:\s+[^>]*)?>/i','<$1>',$html)??'';
 }
 private static function template(PDO $pdo,string $kind): array {
  $q=$pdo->prepare('SELECT * FROM contract_templates WHERE kind=? ORDER BY version DESC LIMIT 1');
  $q->execute([$kind]);$template=$q->fetch(PDO::FETCH_ASSOC);
  if($template)return $template;
  return ['id'=>null,'kind'=>$kind,'version'=>1,'title'=>$kind==='parent'?'قرارداد حمل‌ونقل دانش‌آموزان با ولی':'قرارداد شرکت با راننده','body_html'=>self::defaultHtml($kind)];
 }
 public static function templateEditor(int $uid,string $kind): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'ویرایش قالب تنها برای ادمین کل مجاز است.','SUPER_ADMIN_REQUIRED');
  if(!in_array($kind,['parent','driver'],true))throw new AuthException(422,'نوع قرارداد نامعتبر است.','INVALID_CONTRACT_TYPE');
  $pdo=Connection::get();self::ensureSchema($pdo);
  $template=self::template($pdo,$kind);
  return ['kind'=>$kind,'version'=>(int)$template['version'],'title'=>$template['title'],'html'=>$template['body_html']];
 }
 public static function publishTemplate(int $uid,string $kind,array $body): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'ویرایش قالب تنها برای ادمین کل مجاز است.','SUPER_ADMIN_REQUIRED');
  if(!in_array($kind,['parent','driver'],true))throw new AuthException(422,'نوع قرارداد نامعتبر است.','INVALID_CONTRACT_TYPE');
  $html=(string)($body['html']??'');$title=trim((string)($body['title']??''));
  if(mb_strlen($html)>150000||mb_strlen($html)<100||$title===''||mb_strlen($title)>200)throw new AuthException(422,'عنوان یا متن قالب قرارداد نامعتبر است.','INVALID_CONTRACT_TEMPLATE');
  $html=self::cleanHtml($html);
  $pdo=Connection::get();self::ensureSchema($pdo);
  return OperationsService::transaction(function(PDO $pdo)use($uid,$kind,$html,$title) {
   $pdo->query('SELECT id FROM contract_templates ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetchColumn();
   $q=$pdo->prepare('SELECT COALESCE(MAX(version),0)+1 FROM contract_templates WHERE kind=?');$q->execute([$kind]);$version=(int)$q->fetchColumn();
   $pdo->prepare('INSERT INTO contract_templates(kind,version,title,body_html,created_by) VALUES(?,?,?,?,?)')->execute([$kind,$version,$title,$html,$uid]);
   return ['kind'=>$kind,'version'=>$version,'title'=>$title];
  });
 }
 private static function data(PDO $pdo,int $agencyId,string $kind,int $partyId): array {
  $q=$pdo->prepare('SELECT a.name,a.sepand_national_id FROM agencies a WHERE a.id=?');$q->execute([$agencyId]);$agency=$q->fetch(PDO::FETCH_ASSOC);
  if(!$agency)throw new AuthException(404,'شرکت یافت نشد.','NOT_FOUND');
  $settings=$pdo->prepare('SELECT setting_key,setting_value FROM agency_settings WHERE agency_id=?');$settings->execute([$agencyId]);
  $setting=[];foreach($settings->fetchAll(PDO::FETCH_ASSOC) as $item)$setting[$item['setting_key']]=$item['setting_value'];
  $values=['companyName'=>$agency['name'],'companyNationalId'=>$setting['company_national_id']??$agency['sepand_national_id']??'',
   'companyRegistration'=>$setting['company_registration_number']??'','managerName'=>$setting['company_manager_name']??'',
   'managerNationalCode'=>$setting['company_manager_national_code']??'','companyAddress'=>$setting['company_address']??'',
   'companyPhone'=>$setting['company_phone']??'','bankName'=>$setting['company_bank_name']??'',
   'bankAccount'=>$setting['company_bank_account']??'','academicYear'=>$setting['academic_year']??'۱۴۰۵-۱۴۰۶'];
  if($kind==='parent'){
   $q=$pdo->prepare('SELECT st.id,st.first_name,st.last_name,st.national_code,st.mobile,st.address,st.guardian_first_name,st.guardian_last_name,st.guardian_national_code,st.father_name,st.tariff_distance_km,st.monthly_fee,st.grade,sc.name AS school_name,sc.region,sc.school_type FROM students st JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id WHERE st.id=? AND st.agency_id=? AND st.is_active=1');
   $q->execute([$partyId,$agencyId]);$row=$q->fetch(PDO::FETCH_ASSOC);
   if(!$row)throw new AuthException(404,'دانش‌آموز انتخاب‌شده در شرکت یافت نشد.','STUDENT_NOT_FOUND');
   $g=$pdo->prepare('SELECT name,mobile FROM guardians WHERE student_id=? ORDER BY is_primary DESC,id LIMIT 1');$g->execute([$partyId]);$guardian=$g->fetch(PDO::FETCH_ASSOC)?:[];
   $name=trim((string)($row['guardian_first_name']??'').' '.(string)($row['guardian_last_name']??''));
   $values+=['studentName'=>trim($row['first_name'].' '.$row['last_name']),'studentNationalCode'=>$row['national_code'],
    'schoolName'=>$row['school_name'],'schoolRegion'=>$row['region'],'guardianName'=>$name!==''?$name:($guardian['name']??''),
    'guardianNationalCode'=>$row['guardian_national_code'],'guardianFatherName'=>'',
    'guardianMobile'=>$guardian['mobile']??$row['mobile'],'guardianAddress'=>$row['address'],
    'distanceKm'=>$row['tariff_distance_km'],'monthlyFee'=>$row['monthly_fee'],'grade'=>$row['grade'],
    'schoolType'=>$row['school_type']];
  }else{
   $q=$pdo->prepare('SELECT first_name,last_name,national_code,mobile FROM drivers WHERE id=? AND agency_id=? AND is_active=1');
   $q->execute([$partyId,$agencyId]);$driver=$q->fetch(PDO::FETCH_ASSOC);
   if(!$driver)throw new AuthException(404,'راننده انتخاب‌شده در شرکت یافت نشد.','DRIVER_NOT_FOUND');
   $values+=['driverName'=>trim($driver['first_name'].' '.$driver['last_name']),
    'driverNationalCode'=>$driver['national_code'],'driverMobile'=>$driver['mobile']];
  }
  return $values;
 }
 /**
  * Read-only issuance preflight. Only source fields accessible to the company
  * are returned; no old contract, other tenant or bank secret is read.
  */
 public static function draftData(int $uid,int $agencyId,string $kind,int $partyId): array {
  self::scope($uid,$agencyId);
  if(!in_array($kind,['parent','driver'],true)||$partyId<1)
   throw new AuthException(422,'نوع قرارداد یا طرف قرارداد معتبر نیست.','INVALID_CONTRACT_PARTY');
  $pdo=Connection::get();$data=self::data($pdo,$agencyId,$kind,$partyId);
  $required=$kind==='parent'
   ?['guardianName','guardianNationalCode','guardianMobile','studentName','studentNationalCode','schoolName','schoolRegion','managerName','companyName']
   :['driverName','driverNationalCode','managerName','companyName'];
  $missing=[];foreach($required as $field)if(trim((string)($data[$field]??''))==='')$missing[]=$field;
  $months=$kind==='parent'?(preg_match('/ابتدایی|دبستان|primary|elementary/u',(string)($data['schoolType']??'').(string)($data['grade']??''))?8.5:9):null;
  return ['values'=>$data,'missing'=>$missing,'suggestedTotal'=>$kind==='parent'?(int)round((float)($data['monthlyFee']??0)*$months):null,'academicMonths'=>$months];
 }
 private static function resolvedOverrides(array $body): array {
  $allowed=['guardianName','guardianNationalCode','guardianFatherName','guardianMobile','guardianAddress',
   'companyName','companyRegistration','companyNationalId','managerName','managerNationalCode',
   'companyAddress','companyPhone','bankName','bankAccount','distanceKm','durationMonths','academicYear'];
  $out=[];
  foreach($body as $key=>$value){
   if(!in_array($key,$allowed,true)||!is_string($value)||mb_strlen($value)>500)
    throw new AuthException(422,'فیلد تکمیلی قرارداد نامعتبر است.','INVALID_CONTRACT_OVERRIDE');
   $out[$key]=trim($value);
  }
  return $out;
 }
 private static function persian(string $text): string {
  return strtr($text,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
 }
 private static function jalali(string $isoDate): string {
  if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})/',$isoDate,$m))return self::persian($isoDate);
  $gy=(int)$m[1];$gm=(int)$m[2];$gd=(int)$m[3];
  $offsets=[0,31,59,90,120,151,181,212,243,273,304,334];
  $gy2=$gm>2?$gy+1:$gy;
  $days=355666+365*$gy+intdiv($gy2+3,4)-intdiv($gy2+99,100)+intdiv($gy2+399,400)+$gd+$offsets[$gm-1];
  $jy=-1595+33*intdiv($days,12053);$days%=12053;
  $jy+=4*intdiv($days,1461);$days%=1461;
  if($days>365){$jy+=intdiv($days-1,365);$days=($days-1)%365;}
  $jm=$days<186?1+intdiv($days,31):7+intdiv($days-186,30);
  $jd=1+($days<186?$days%31:($days-186)%30);
  return self::persian(sprintf('%04d/%02d/%02d',$jy,$jm,$jd));
 }
 private static function document(string $html,array $values): string {
  $output=preg_replace_callback('/\{\{([a-zA-Z][a-zA-Z0-9]*)\}\}/',
   static fn(array $match)=>htmlspecialchars((string)($values[$match[1]]??'—'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$html);
  return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>قرارداد '.htmlspecialchars((string)($values['contractNo']??''),ENT_QUOTES,'UTF-8').'</title><style>'.
    '@font-face{font-family:Vazirmatn;src:url(/assets/Vazirmatn-Regular.woff2) format("woff2");font-weight:400;font-style:normal;font-display:swap}@page{size:A4;margin:8mm 11mm}*{box-sizing:border-box}body{font-family:Tahoma,"Vazirmatn",sans-serif;direction:rtl;font-size:8.6pt;line-height:1.3;color:#171717;max-width:190mm;margin:auto}h1{text-align:center;font-size:13pt;margin:0 0 7px}h2{font-size:9.3pt;margin:7px 0 2px;font-weight:700}p{margin:2px 0;text-align:justify}.contract-top{display:flex;justify-content:space-between;align-items:center;border-top:1px solid #333;padding-top:5px;font-size:9.5pt}.center{text-align:center}.signatures{display:flex;justify-content:space-between;margin-top:20px;min-height:60px;page-break-inside:avoid}hr{break-before:page;page-break-before:always;border:0;height:0;margin:0}body>div:first-of-type{display:flex;justify-content:space-between}body>div:last-of-type{display:flex;justify-content:space-between;page-break-inside:avoid}table{width:100%;border-collapse:collapse}td,th{border:1px solid #555;padding:3px}ul,ol{margin:3px 0}@media print{button{display:none}}'.
    '</style></head><body><button type="button" style="margin:8px 0;padding:9px 16px;cursor:pointer" onclick="window.print()">چاپ قرارداد / ذخیره PDF</button>'.$output.'</body></html>';
 }
 public static function issue(int $uid,int $agencyId,string $kind,array $input): array {
  self::scope($uid,$agencyId,true);$pdo=Connection::get();self::ensureSchema($pdo);
  if(!in_array($kind,['parent','driver'],true))throw new AuthException(422,'نوع قرارداد نامعتبر است.','INVALID_CONTRACT_TYPE');
  $partyId=filter_var($input['partyId']??null,FILTER_VALIDATE_INT);
  if($partyId===false||$partyId<1)throw new AuthException(422,'طرف قرارداد را انتخاب کنید.','INVALID_CONTRACT_PARTY');
  $start=(string)($input['startDate']??'');$end=(string)($input['endDate']??'');
  if(!OperationsService::validDate($start)||!OperationsService::validDate($end)||$end<$start)throw new AuthException(422,'بازه زمانی قرارداد نامعتبر است.','INVALID_CONTRACT_DATES');
  $amount=filter_var($input['amount']??null,FILTER_VALIDATE_INT);
  if($amount===false||$amount<0||$amount>999999999999999)throw new AuthException(422,'مبلغ قرارداد باید عدد صحیح ریالی باشد.','INVALID_CONTRACT_AMOUNT');
  $values=self::data($pdo,$agencyId,$kind,(int)$partyId);
  $overrides=$input['overrides']??[];
  if(!is_array($overrides))throw new AuthException(422,'اطلاعات تکمیلی قرارداد معتبر نیست.','INVALID_CONTRACT_OVERRIDE');
  $values=array_replace($values,self::resolvedOverrides($overrides));
  $required=$kind==='parent'?['guardianName','guardianNationalCode','guardianMobile','studentName','studentNationalCode','schoolName','managerName','companyName']:['driverName','driverNationalCode','managerName','companyName'];
  foreach($required as $field)if(trim((string)($values[$field]??''))==='')
   throw new AuthException(422,'اطلاعات اصلی طرفین قرارداد کامل نیست؛ ابتدا مشخصات تکمیلی را وارد کنید.','CONTRACT_REQUIRED_DATA_MISSING');
  $template=self::template($pdo,$kind);
  return OperationsService::transaction(function(PDO $pdo)use($uid,$agencyId,$kind,$partyId,$start,$end,$amount,$values,$template) {
   $contractNo='SY-'.$agencyId.'-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
   $months=$kind==='parent'?(preg_match('/ابتدایی|دبستان|primary|elementary/u',(string)($values['schoolType']??'').(string)($values['grade']??''))?8.5:9):0;
   $data=$values+['contractNo'=>self::persian($contractNo),'issueDate'=>self::jalali(date('Y-m-d')),'startDate'=>self::jalali($start),'endDate'=>self::jalali($end),
    'durationMonths'=>self::persian((string)$months),'totalAmount'=>self::persian(number_format($amount,0,'.',',')),
    'payment40'=>self::persian(number_format((int)round($amount*.4),0,'.',',')),
    'payment20'=>self::persian(number_format((int)round($amount*.2),0,'.',','))];
   $rendered=self::document((string)$template['body_html'],$data);
   $title=(string)$template['title'].' - '.($kind==='parent'?$values['studentName']:$values['driverName']);
   $pdo->prepare("INSERT INTO contracts(agency_id,title,contract_no,party_type,party_id,start_date,end_date,amount,status,template_id,rendered_html,snapshot_json,issued_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())")->execute([
    $agencyId,$title,$contractNo,$kind==='parent'?'student':'driver',$partyId,$start,$end,$amount,'active',$template['id'],$rendered,
    json_encode(['templateVersion'=>$template['version'],'kind'=>$kind,'values'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
   $id=(int)$pdo->lastInsertId();
   return ['id'=>$id,'contractNo'=>$contractNo,'templateVersion'=>$template['version'],'title'=>$title];
  });
 }
 public static function listing(int $uid,int $agencyId,string $kind): array {
  self::scope($uid,$agencyId);if(!in_array($kind,['parent','driver'],true))throw new AuthException(422,'نوع قرارداد نامعتبر است.','INVALID_CONTRACT_TYPE');
  $pdo=Connection::get();self::ensureSchema($pdo);
  $q=$pdo->prepare("SELECT id,title,contract_no,party_type,party_id,start_date,end_date,amount,status,issued_at,
    CASE WHEN rendered_html IS NULL THEN 0 ELSE 1 END AS printable
    FROM contracts WHERE agency_id=? AND party_type=? ORDER BY id DESC LIMIT 250");
  $q->execute([$agencyId,$kind==='parent'?'student':'driver']);
  return $q->fetchAll(PDO::FETCH_ASSOC);
 }
 public static function printable(int $uid,int $agencyId,int $contractId): array {
  self::scope($uid,$agencyId);$pdo=Connection::get();self::ensureSchema($pdo);
  $q=$pdo->prepare('SELECT id,contract_no,rendered_html,party_type,party_id,start_date,end_date,amount FROM contracts WHERE id=? AND agency_id=?');
  $q->execute([$contractId,$agencyId]);$row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row)throw new AuthException(404,'قرارداد یافت نشد.','NOT_FOUND');
  if($row['rendered_html'])return ['html'=>$row['rendered_html'],'contractNo'=>$row['contract_no'],'legacyReconstructed'=>false];
  // Existing records before versioned issuance have no historical content.
  // Provide an explicitly watermarked reconstruction, never claim it is the
  // original and never overwrite the legal issuance snapshot column.
  $kind=$row['party_type']==='student'?'parent':($row['party_type']==='driver'?'driver':null);
  if(!$kind||!(int)$row['party_id'])throw new AuthException(409,'نوع یا طرف قرارداد قدیمی برای بازسازی مشخص نیست.','LEGACY_CONTRACT_DATA_MISSING');
  $data=self::data($pdo,$agencyId,$kind,(int)$row['party_id']);
  $total=(int)round((float)$row['amount']);
  $months=$kind==='parent'?(preg_match('/ابتدایی|دبستان|primary|elementary/u',(string)($data['schoolType']??'').(string)($data['grade']??''))?8.5:9):0;
  $values=$data+['contractNo'=>self::persian((string)($row['contract_no']??'—')),'issueDate'=>'نامشخص',
   'startDate'=>self::jalali((string)($row['start_date']??'')),
   'endDate'=>self::jalali((string)($row['end_date']??'')),
   'durationMonths'=>self::persian((string)$months),
   'totalAmount'=>self::persian(number_format($total,0,'.',',')),
   'payment40'=>self::persian(number_format((int)round($total*.4),0,'.',',')),
   'payment20'=>self::persian(number_format((int)round($total*.2),0,'.',','))];
  $warning='<p style="border:2px solid #a83232;padding:8px;font-weight:bold">نسخه بازسازی‌شده از اطلاعات فعلی سامانه؛ اصل قالب و محتوای زمان صدور این قرارداد قدیمی در آرشیو موجود نیست و این نسخه جایگزین نسخه اصلی نیست.</p>';
  $html=self::document(self::template($pdo,$kind)['body_html'],$values);
  $html=str_replace('<body>','<body>'.$warning,$html);
  return ['html'=>$html,'contractNo'=>$row['contract_no'],'legacyReconstructed'=>true];
 }
}
