<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use ServiceYar\Integrations\NeginSms;
use ServiceYar\Integrations\SmsQueue;
if(!in_array('--send',$argv,true)){echo "Dry run. To process the Negin SMS queue: php backend/bin/send-sms.php --send\n";exit;}
if(!NeginSms::configured()){fwrite(STDERR,"Negin SMS is not configured/enabled. No messages were sent.\n");exit(1);}
echo json_encode(SmsQueue::run(),JSON_UNESCAPED_UNICODE)."\n";
