<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

function fail(int $status, string $message): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$appId = getenv('ADZUNA_APP_ID') ?: '';
$appKey = getenv('ADZUNA_APP_KEY') ?: '';

$configPath = dirname(__DIR__, 2) . '/jobsother-config.php';
if ((!$appId || !$appKey) && is_file($configPath)) {
    $config = require $configPath;
    if (is_array($config)) {
        $appId = $appId ?: (string)($config['ADZUNA_APP_ID'] ?? '');
        $appKey = $appKey ?: (string)($config['ADZUNA_APP_KEY'] ?? '');
    }
}

if (!$appId || !$appKey) {
    fail(500, 'Adzuna API credentials are not configured on the server.');
}

$q = trim((string)($_GET['q'] ?? ''));
$where = trim((string)($_GET['location'] ?? ''));
$country = strtolower(trim((string)($_GET['country'] ?? 'ca')));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['results_per_page'] ?? 20)));

$allowedCountries = ['ca', 'us', 'gb', 'au', 'nz'];
if (!in_array($country, $allowedCountries, true)) {
    $country = 'ca';
}

$params = [
    'app_id' => $appId,
    'app_key' => $appKey,
    'results_per_page' => $perPage,
    'content-type' => 'application/json',
];
if ($q !== '') $params['what'] = $q;
if ($where !== '') $params['where'] = $where;

$url = 'https://api.adzuna.com/v1/api/jobs/' . rawurlencode($country) . '/search/' . $page . '?' . http_build_query($params);

$body = false;
$status = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'JobsOther/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "Accept: application/json\r\nUser-Agent: JobsOther/1.0\r\n",
            'ignore_errors' => true,
        ]
    ]);
    $body = @file_get_contents($url, false, $context);
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
}

if ($body === false || $status < 200 || $status >= 300) {
    fail(502, 'Job provider returned an error.');
}

$data = json_decode($body, true);
if (!is_array($data)) {
    fail(502, 'Invalid response from job provider.');
}

$results = [];
foreach (($data['results'] ?? []) as $job) {
    if (!is_array($job)) continue;
    $results[] = [
        'id' => $job['id'] ?? null,
        'title' => $job['title'] ?? 'Untitled job',
        'company' => $job['company']['display_name'] ?? '',
        'location' => $job['location']['display_name'] ?? '',
        'description' => $job['description'] ?? '',
        'created' => $job['created'] ?? null,
        'redirect_url' => $job['redirect_url'] ?? '',
        'salary_min' => $job['salary_min'] ?? null,
        'salary_max' => $job['salary_max'] ?? null,
        'contract_time' => $job['contract_time'] ?? '',
        'contract_type' => $job['contract_type'] ?? '',
        'category' => $job['category']['label'] ?? '',
    ];
}

echo json_encode([
    'count' => (int)($data['count'] ?? 0),
    'page' => $page,
    'results' => $results,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
