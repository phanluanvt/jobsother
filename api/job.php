<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$id=trim((string)($_GET['id']??''));$q=trim((string)($_GET['q']??''));$where=trim((string)($_GET['location']??''));
if($id===''){http_response_code(422);echo json_encode(['error'=>'Job id required']);exit;}
$appId=getenv('ADZUNA_APP_ID')?:'';$appKey=getenv('ADZUNA_APP_KEY')?:'';$configPath=dirname(__DIR__,2).'/jobsother-config.php';
if((!$appId||!$appKey)&&is_file($configPath)){$c=require $configPath;if(is_array($c)){$appId=$appId?:($c['ADZUNA_APP_ID']??'');$appKey=$appKey?:($c['ADZUNA_APP_KEY']??'');}}
$params=['app_id'=>$appId,'app_key'=>$appKey,'results_per_page'=>50,'content-type'=>'application/json'];if($q!=='')$params['what']=$q;if($where!=='')$params['where']=$where;
$url='https://api.adzuna.com/v1/api/jobs/ca/search/1?'.http_build_query($params);$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'JobsOther/1.0']);$body=curl_exec($ch);curl_close($ch);$d=json_decode((string)$body,true);
foreach(($d['results']??[]) as $j){if((string)($j['id']??'')===$id){echo json_encode(['id'=>$j['id']??null,'title'=>$j['title']??'','company'=>$j['company']['display_name']??'','location'=>$j['location']['display_name']??'','description'=>strip_tags((string)($j['description']??'')),'created'=>$j['created']??null,'redirect_url'=>$j['redirect_url']??'','salary_min'=>$j['salary_min']??null,'salary_max'=>$j['salary_max']??null,'contract_time'=>$j['contract_time']??''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}}
http_response_code(404);echo json_encode(['error'=>'Job not found']);
