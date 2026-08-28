<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$type=(string)($_GET['type']??'job');$q=mb_strtolower(trim((string)($_GET['q']??'')));
$jobs=['Warehouse Associate','Warehouse Worker','Warehouse Manager','Warehouse Supervisor','Delivery Driver','Truck Driver','Customer Service Representative','Administrative Assistant','Cashier','Cleaner','Construction Labourer','Registered Nurse','Restaurant Server','Software Developer','Sales Associate','Personal Assistant','Human Resources','Work From Home','Part Time','Full Time'];
$locations=['Vancouver, BC','North Vancouver, BC','West Vancouver, BC','Burnaby, BC','Richmond, BC','Surrey, BC','Langley, BC','Coquitlam, BC','Toronto, ON','Mississauga, ON','Brampton, ON','Calgary, AB','Edmonton, AB','Ottawa, ON','Winnipeg, MB','Victoria, BC'];
$list=$type==='location'?$locations:$jobs;$out=[];
foreach($list as $v){if($q===''||str_contains(mb_strtolower($v),$q))$out[]=$v;if(count($out)>=8)break;}
echo json_encode(['results'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
