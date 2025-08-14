<?php
// ai/sql/get_logs.php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Kuala_Lumpur');

/* === DB CONFIG (localhost) === */
$host = '127.0.0.1';
$user = 'root';
$pass = '';             // <-- your local password
$db   = 'ai';           // <-- your DB

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'DB connect failed: '.$mysqli->connect_error]);
  exit;
}

/* Query params (filters only, no limit/offset) */
$user_id = (isset($_GET['user_id']) && $_GET['user_id'] !== '') ? intval($_GET['user_id']) : null;
$source  = isset($_GET['source']) ? trim($_GET['source']) : null;

$where = [];
$types = '';
$params = [];

if ($user_id !== null) { $where[] = 'user_id = ?'; $types .= 'i'; $params[] = $user_id; }
if ($source)          { $where[] = 'source = ?';  $types .= 's'; $params[] = $source; }

$whereSQL = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$sql = "
  SELECT
    id, user_id, source, question, summary, sql_text, result_json, error_text,
    latency_ms, ip, user_agent, created_at
  FROM ai_query_logs
  $whereSQL
  ORDER BY created_at ASC, id ASC
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Prepare failed: '.$mysqli->error]);
  exit;
}

/* Bind only if we actually have params */
if ($types !== '') {
  $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Execute failed: '.$stmt->error]);
  exit;
}

$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

echo json_encode(['ok'=>true, 'items'=>$rows]);
