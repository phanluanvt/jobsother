<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$lat=(float)($_GET['lat']??0);$lng=(float)($_GET['lng']??0);
if(!$lat&&!$lng){http_response_code(422);echo json_encode(['error'=>'Coordinates required']);exit;}
$url='https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=10&lat='.rawurlencode((string)$lat).'&lon='.rawurlencode((string)$lng);
$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['User-Agent: JobsOther/1.0 (support@jobsother.com)']]);$body=curl_exec($ch);curl_close($ch);$d=json_decode((string)$body,true);$a=$d['address']??[];$city=$a['city']??$a['town']??$a['municipality']??$a['village']??'';$province=$a['state']??'';echo json_encode(['location'=>trim($city.($province?', '.$province:''))],JSON_UNESCAPED_UNICODE);
