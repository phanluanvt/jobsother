<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }
$firstName = trim((string)($_POST['first_name'] ?? ''));
$consent = (string)($_POST['consent'] ?? '') === '1';
$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$keyword = trim((string)($_POST['keyword'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
$frequency = in_array(($_POST['frequency'] ?? 'daily'), ['daily','weekly'], true) ? $_POST['frequency'] : 'daily';
if (!$email || $keyword === '' || !$consent) { http_response_code(422); echo json_encode(['error'=>'Enter a valid email and job keyword.']); exit; }
$dir = dirname(__DIR__, 2) . '/jobsother-data';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) { http_response_code(500); echo json_encode(['error'=>'Could not save alert.']); exit; }
$file = $dir . '/alerts.jsonl';
$record = ['first_name'=>mb_substr($firstName,0,80),'email'=>$email,'keyword'=>mb_substr($keyword,0,120),'location'=>mb_substr($location,0,120),'frequency'=>$frequency,'status'=>'active','unsubscribe_token'=>bin2hex(random_bytes(24)),'created_at'=>gmdate('c')];
if (file_put_contents($file, json_encode($record, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND|LOCK_EX) === false) { http_response_code(500); echo json_encode(['error'=>'Could not save alert.']); exit; }
echo json_encode(['ok'=>true,'message'=>'Your job alert has been created.']);
