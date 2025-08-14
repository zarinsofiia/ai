<?php
// sql/save_log.php
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Asia/Kuala_Lumpur');

/* === DB CONFIG (localhost) === */
$host = '127.0.0.1';
$user = 'root';
$pass = '';            // <-- set your local password
$db   = 'ai';// <-- set your local DB

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'DB connect failed: '.$mysqli->connect_error]);
  exit;
}

/* Read JSON */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'Invalid JSON']);
  exit;
}

/* Extract fields */
$user_id    = isset($data['user_id']) ? $data['user_id'] : null;
$source     = isset($data['source']) ? $data['source'] : 'sql';
$question   = isset($data['question']) ? $data['question'] : '';
$summary    = isset($data['summary']) ? $data['summary'] : null;
$sql_text   = isset($data['sql_text']) ? $data['sql_text'] : null;
$result_json= isset($data['result_json']) ? $data['result_json'] : null;
$error_text = isset($data['error_text']) ? $data['error_text'] : null;
$latency_ms = isset($data['latency_ms']) ? intval($data['latency_ms']) : null;

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

/* Insert (prepared) */
$sql = "INSERT INTO ai_query_logs
(user_id, source, question, summary, sql_text, result_json, error_text, latency_ms, ip, user_agent)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Prepare failed: '.$mysqli->error]);
  exit;
}

$stmt->bind_param(
  "issssssiss",
  $user_id,
  $source,
  $question,
  $summary,
  $sql_text,
  $result_json,
  $error_text,
  $latency_ms,
  $ip,
  $ua
);

$ok = $stmt->execute();
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Execute failed: '.$stmt->error]);
  exit;
}

echo json_encode(['ok'=>true, 'id'=>$stmt->insert_id]);
