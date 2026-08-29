<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=900');

$supported=['CA'=>'ca','US'=>'us','GB'=>'gb','AU'=>'au','NZ'=>'nz'];
$cfCountry=strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY']??'')));
$ip=trim((string)($_SERVER['HTTP_CF_CONNECTING_IP']??$_SERVER['REMOTE_ADDR']??''));
$countryCode=preg_match('/^[A-Z]{2}$/',$cfCountry)?$cfCountry:'';
$city='';$region='';$postal='';$countryName='';

if($ip!=='' && filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)){
    $url='https://ipwho.is/'.rawurlencode($ip);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>7,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'JobsOther/1.0']);
    $body=curl_exec($ch);curl_close($ch);
    $d=json_decode((string)$body,true);
    if(is_array($d) && ($d['success']??false)){
        $apiCountry=strtoupper((string)($d['country_code']??''));
        if($countryCode==='' && preg_match('/^[A-Z]{2}$/',$apiCountry))$countryCode=$apiCountry;
        $countryName=trim((string)($d['country']??''));
        $city=trim((string)($d['city']??''));
        $region=trim((string)($d['region']??''));
        $postal=trim((string)($d['postal']??''));
    }
}
$location=trim($city.($region!==''?', '.$region:''));
echo json_encode([
  'country'=>strtolower($countryCode),
  'country_code'=>$countryCode,
  'country_name'=>$countryName,
  'provider_country'=>$supported[$countryCode]??null,
  'job_search_supported'=>isset($supported[$countryCode]),
  'city'=>$city,
  'region'=>$region,
  'postal'=>$postal,
  'location'=>$location
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
