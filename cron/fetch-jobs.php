<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$configPath = dirname(__DIR__, 2) . '/jobsother-config.php';
$config = is_file($configPath) ? require $configPath : [];
$appId = getenv('ADZUNA_APP_ID') ?: (string)($config['ADZUNA_APP_ID'] ?? '');
$appKey = getenv('ADZUNA_APP_KEY') ?: (string)($config['ADZUNA_APP_KEY'] ?? '');
if (!$appId || !$appKey) { fwrite(STDERR, "Missing Adzuna credentials\n"); exit(1); }

$dataDir = dirname(__DIR__, 2) . '/jobsother-data';
if (!is_dir($dataDir)) mkdir($dataDir, 0700, true);
$cacheFile = $dataDir . '/jobs.json';

$cities = ['Vancouver','Burnaby','Surrey','Richmond','Toronto','Calgary','Edmonton','Ottawa','Winnipeg','Victoria'];
$jobs = [];
if (is_file($cacheFile)) {
    $old = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($old)) foreach ($old as $j) if (is_array($j) && !empty($j['id'])) $jobs[(string)$j['id']] = $j;
}

function fetchJobs(string $city, string $appId, string $appKey): array {
    $params = ['app_id'=>$appId,'app_key'=>$appKey,'results_per_page'=>50,'content-type'=>'application/json','where'=>$city];
    $url = 'https://api.adzuna.com/v1/api/jobs/ca/search/1?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'JobsOther/1.0']);
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) return [];
    $d = json_decode((string)$body,true);
    return is_array($d['results'] ?? null) ? $d['results'] : [];
}
function norm(array $j): array {
    return [
        'id'=>(string)($j['id']??''),
        'external_id'=>(string)($j['id']??''),
        'source'=>'Adzuna',
        'title'=>(string)($j['title']??''),
        'company'=>(string)($j['company']['display_name']??''),
        'company_logo'=>'',
        'location'=>(string)($j['location']['display_name']??''),
        'city'=>'',
        'province'=>'',
        'country'=>'CA',
        'postal_code'=>'',
        'latitude'=>$j['latitude']??null,
        'longitude'=>$j['longitude']??null,
        'salary_min'=>$j['salary_min']??null,
        'salary_max'=>$j['salary_max']??null,
        'salary_currency'=>'CAD',
        'salary_period'=>null,
        'employment_type'=>(string)($j['contract_time']??''),
        'contract_type'=>(string)($j['contract_type']??''),
        'category'=>(string)($j['category']['label']??''),
        'description'=>strip_tags((string)($j['description']??'')),
        'short_description'=>mb_substr(strip_tags((string)($j['description']??'')),0,320),
        'apply_url'=>(string)($j['redirect_url']??''),
        'source_url'=>(string)($j['redirect_url']??''),
        'posted_at'=>$j['created']??null,
        'expires_at'=>null,
        'created_at'=>gmdate('c'),
        'updated_at'=>gmdate('c'),
        'is_featured'=>false,
        'is_sponsored'=>false,
        'is_remote'=>str_contains(mb_strtolower((string)($j['title']??'').' '.(string)($j['description']??'')),'remote'),
        'is_new'=>!empty($j['created']) && strtotime((string)$j['created']) >= time()-172800,
    ];
}

$hashes=[];
foreach ($jobs as $id=>$j) {
    $key=mb_strtolower(trim((string)($j['title']??''))).'|'.mb_strtolower(trim((string)($j['company']??''))).'|'.mb_strtolower(trim((string)($j['location']??'')));
    $hashes[hash('sha256',$key)]=$id;
}
foreach ($cities as $city) {
    foreach (fetchJobs($city,$appId,$appKey) as $raw) {
        if (!is_array($raw)) continue;
        $j=norm($raw); if ($j['id']==='') continue;
        $key=mb_strtolower(trim($j['title'])).'|'.mb_strtolower(trim($j['company'])).'|'.mb_strtolower(trim($j['location']));
        $h=hash('sha256',$key);
        if (isset($hashes[$h]) && $hashes[$h] !== $j['id']) continue;
        $hashes[$h]=$j['id']; $jobs[$j['id']]=$j;
    }
    usleep(150000);
}
$cutoff=time()-45*86400;
$jobs=array_filter($jobs,function($j)use($cutoff){$t=!empty($j['posted_at'])?strtotime((string)$j['posted_at']):time();return $t===false||$t>=$cutoff;});
usort($jobs,fn($a,$b)=>strtotime((string)($b['posted_at']??''))<=>strtotime((string)($a['posted_at']??'')));
$jobs=array_slice($jobs,0,5000);
$tmp=$cacheFile.'.tmp';
file_put_contents($tmp,json_encode(array_values($jobs),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
rename($tmp,$cacheFile);
echo "Cached ".count($jobs)." jobs at ".gmdate('c')."\n";
