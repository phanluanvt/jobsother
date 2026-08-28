<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$dataDir=dirname(__DIR__,2).'/jobsother-data';$alertsFile=$dataDir.'/alerts.jsonl';$jobsFile=$dataDir.'/jobs.json';
if(!is_file($alertsFile)||!is_file($jobsFile)){echo "No alerts or job cache yet\n";exit;}
$jobs=json_decode((string)file_get_contents($jobsFile),true);if(!is_array($jobs))$jobs=[];
$lines=file($alertsFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];$updated=[];$sent=0;
foreach($lines as $line){
 $a=json_decode($line,true);if(!is_array($a)||($a['status']??'active')!=='active'){if(is_array($a))$updated[]=$a;continue;}
 $freq=$a['frequency']??'daily';$last=!empty($a['last_sent_at'])?strtotime((string)$a['last_sent_at']):0;$minGap=$freq==='weekly'?6*86400:20*3600;
 if($last && time()-$last<$minGap){$updated[]=$a;continue;}
 $kw=mb_strtolower(trim((string)($a['keyword']??'')));$loc=mb_strtolower(trim((string)($a['location']??'')));$matches=[];
 foreach($jobs as $j){$hay=mb_strtolower((string)($j['title']??'').' '.(string)($j['company']??'').' '.(string)($j['description']??''));$jl=mb_strtolower((string)($j['location']??''));$posted=!empty($j['posted_at'])?strtotime((string)$j['posted_at']):0;if($kw!==''&&!str_contains($hay,$kw))continue;if($loc!==''&&!str_contains($jl,$loc))continue;if($last&&$posted&&$posted<=$last)continue;$matches[]=$j;if(count($matches)>=10)break;}
 if(!$matches){$updated[]=$a;continue;}
 $subject=count($matches).' new '.$a['keyword'].' jobs'.(!empty($a['location'])?' in '.$a['location']:'');
 $name=htmlspecialchars((string)($a['first_name']??''),ENT_QUOTES,'UTF-8');$html='<html><body style="font-family:Arial,sans-serif"><h2>'.htmlspecialchars($subject,ENT_QUOTES,'UTF-8').'</h2>'.($name?'<p>Hi '.$name.',</p>':'');
 foreach($matches as $j){$url='https://jobsother.com/job.html?id='.rawurlencode((string)$j['id']).'&q='.rawurlencode((string)$a['keyword']).'&location='.rawurlencode((string)$a['location']);$html.='<div style="margin:18px 0;padding:14px;border:1px solid #ddd;border-radius:8px"><strong>'.htmlspecialchars((string)$j['title'],ENT_QUOTES,'UTF-8').'</strong><br>'.htmlspecialchars((string)($j['company']??''),ENT_QUOTES,'UTF-8').'<br><span style="color:#666">'.htmlspecialchars((string)($j['location']??''),ENT_QUOTES,'UTF-8').'</span><br><a href="'.$url.'">View Job</a></div>';}
 $token=(string)($a['unsubscribe_token']??'');$html.='<p style="font-size:12px;color:#888"><a href="https://jobsother.com/api/unsubscribe.php?token='.rawurlencode($token).'">Unsubscribe</a></p></body></html>';
 $headers="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: JobsOther <alerts@jobsother.com>\r\n";
 if(@mail((string)$a['email'],$subject,$html,$headers)){$a['last_sent_at']=gmdate('c');$sent++;}
 $updated[]=$a;
}
$buf='';foreach($updated as $a)$buf.=json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";file_put_contents($alertsFile,$buf,LOCK_EX);echo "Sent $sent alert emails\n";
