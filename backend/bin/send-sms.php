<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use ServiceYar\Integrations\SmsProvider;
use ServiceYar\Integrations\SmsQueue;
if(!in_array('--send',$argv,true)){echo "Dry run. To process the selected SMS provider queue: php backend/bin/send-sms.php --send\n";exit;}
if(!SmsProvider::ready()){fwrite(STDERR,"Selected SMS provider is not configured/ready. No messages were sent.\n");exit(1);}
echo json_encode(SmsQueue::run(),JSON_UNESCAPED_UNICODE)."\n";
