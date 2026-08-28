<?php
declare(strict_types=1);
$token=trim((string)($_GET['token']??''));$file=dirname(__DIR__,2).'/jobsother-data/alerts.jsonl';
if($token===''||!is_file($file)){http_response_code(400);exit('Invalid unsubscribe link');}
$lines=file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];$out=[];$found=false;
foreach($lines as $line){$a=json_decode($line,true);if(!is_array($a))continue;if(hash_equals((string)($a['unsubscribe_token']??''),$token)){$a['status']='unsubscribed';$a['unsubscribed_at']=gmdate('c');$found=true;}$out[]=$a;}
$buf='';foreach($out as $a)$buf.=json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";file_put_contents($file,$buf,LOCK_EX);
header('Content-Type: text/html; charset=utf-8');echo $found?'<h2>You have been unsubscribed from JobsOther job alerts.</h2>':'<h2>Subscription not found.</h2>';
