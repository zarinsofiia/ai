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
$mysqli->set_charset('utf8mb4');

/** Helpers **/
function read_int($key, $default = null, $min = null, $max = null) {
  if (!isset($_GET[$key]) || $_GET[$key] === '') return $default;
  $v = intval($_GET[$key]);
  if ($min !== null && $v < $min) $v = $min;
  if ($max !== null && $v > $max) $v = $max;
  return $v;
}
function read_str($key, $default = null) {
  if (!isset($_GET[$key])) return $default;
  $v = trim((string)$_GET[$key]);
  return ($v === '') ? $default : $v;
}
function to_mysql_ts($s) {
  if (!$s) return null;
  $t = strtotime($s);
  if ($t === false) return null;
  return date('Y-m-d H:i:s', $t);
}

/* ── Query params ─────────────────────────────────────────── */
$user_id   = read_int('user_id', null, 0);
$source    = read_str('source', null);

$limit     = read_int('limit', 30, 1, 200);
$order_in  = strtolower(read_str('order', 'desc'));
$order     = ($order_in === 'asc') ? 'ASC' : 'DESC';

$offset    = read_int('offset', 0, 0); // used only when no cursor is provided

$before_id = read_int('before_id', null, 1);
$before_ts = to_mysql_ts(read_str('before_ts', null));

$after_id  = read_int('after_id', null, 1);
$after_ts  = to_mysql_ts(read_str('after_ts', null));

/* ── Base filters ─────────────────────────────────────────── */
$where = [];
$types = '';
$params = [];

if ($user_id !== null) { $where[] = 'user_id = ?'; $types .= 'i'; $params[] = $user_id; }
if ($source !== null)  { $where[] = 'source = ?';  $types .= 's'; $params[] = $source; }

/* ── Resolve cursor timestamps if only *_id provided ─────── */
$cursor_ts_for_before = $before_ts;
$cursor_id_for_before = $before_id;

$cursor_ts_for_after  = $after_ts;
$cursor_id_for_after  = $after_id;

if ($before_id !== null && $before_ts === null) {
  $stmt = $mysqli->prepare('SELECT created_at FROM ai_query_logs WHERE id = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('i', $before_id);
    if ($stmt->execute()) {
      $r = $stmt->get_result()->fetch_assoc();
      if ($r && isset($r['created_at'])) $cursor_ts_for_before = $r['created_at'];
    }
    $stmt->close();
  }
}
if ($after_id !== null && $after_ts === null) {
  $stmt = $mysqli->prepare('SELECT created_at FROM ai_query_logs WHERE id = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('i', $after_id);
    if ($stmt->execute()) {
      $r = $stmt->get_result()->fetch_assoc();
      if ($r && isset($r['created_at'])) $cursor_ts_for_after = $r['created_at'];
    }
    $stmt->close();
  }
}

/* ── Cursor windows (keyset pagination on (created_at,id)) ── */
$using_cursor = false;

if ($order === 'DESC') {
  if ($cursor_ts_for_before !== null || $cursor_id_for_before !== null) {
    $using_cursor = true;
    if ($cursor_ts_for_before !== null && $cursor_id_for_before !== null) {
      $where[] = '(created_at < ? OR (created_at = ? AND id < ?))';
      $types  .= 'ssi';
      $params[] = $cursor_ts_for_before;
      $params[] = $cursor_ts_for_before;
      $params[] = $cursor_id_for_before;
    } elseif ($cursor_ts_for_before !== null) {
      $where[] = 'created_at < ?';
      $types  .= 's';
      $params[] = $cursor_ts_for_before;
    } else {
      $where[] = 'id < ?';
      $types  .= 'i';
      $params[] = $cursor_id_for_before;
    }
  }
} else { // ASC
  if ($cursor_ts_for_after !== null || $cursor_id_for_after !== null) {
    $using_cursor = true;
    if ($cursor_ts_for_after !== null && $cursor_id_for_after !== null) {
      $where[] = '(created_at > ? OR (created_at = ? AND id > ?))';
      $types  .= 'ssi';
      $params[] = $cursor_ts_for_after;
      $params[] = $cursor_ts_for_after;
      $params[] = $cursor_id_for_after;
    } elseif ($cursor_ts_for_after !== null) {
      $where[] = 'created_at > ?';
      $types  .= 's';
      $params[] = $cursor_ts_for_after;
    } else {
      $where[] = 'id > ?';
      $types  .= 'i';
      $params[] = $cursor_id_for_after;
    }
  }
}

$whereSQL = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* ── Main page query (keyset preferred, offset fallback) ──── */
$sql = "
  SELECT
    id, user_id, source, question, summary, sql_text, result_json, error_text,
    latency_ms, ip, user_agent, created_at
  FROM ai_query_logs
  $whereSQL
  ORDER BY created_at $order, id $order
  LIMIT ?
";
$types_final = $types . 'i';
$params_final = $params;
$params_final[] = $limit;

if (!$using_cursor && $offset > 0) {
  $sql = rtrim($sql, "; \n\r\t") . " OFFSET ?";
  $types_final .= 'i';
  $params_final[] = $offset;
}

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Prepare failed: '.$mysqli->error]);
  exit;
}
if ($types_final !== '') $stmt->bind_param($types_final, ...$params_final);
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Execute failed: '.$stmt->error]);
  $stmt->close();
  exit;
}
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Stats (all-time + today) using the same base filters ─── */
function stats_query($mysqli, $where, $types, $params, $extraWhere = '') {
  $wSQL = $where ? ('WHERE '.implode(' AND ', $where)) : '';
  if ($extraWhere !== '') $wSQL .= ($wSQL ? ' AND ' : ' WHERE ') . $extraWhere;

  $sql = "
    SELECT
      COUNT(*) AS total,
      AVG(latency_ms) AS avg_ms,
      SUM(CASE WHEN (error_text IS NULL OR TRIM(error_text) = '') THEN 1 ELSE 0 END) AS success,
      SUM(CASE WHEN (error_text IS NOT NULL AND TRIM(error_text) <> '') THEN 1 ELSE 0 END) AS failed
    FROM ai_query_logs
    $wSQL
  ";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) return ['total'=>0,'success'=>0,'failed'=>0,'avg_ms'=>null];

  if ($extraWhere !== '') {
    // extraWhere for today adds 2 string params (start,end)
    $t = $types . 'ss';
    $p = $params;
    $todayStart    = date('Y-m-d 00:00:00');
    $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
    $p[] = $todayStart;
    $p[] = $tomorrowStart;
    $stmt->bind_param($t, ...$p);
  } else {
    if ($types !== '') $stmt->bind_param($types, ...$params);
  }

  if (!$stmt->execute()) {
    $stmt->close();
    return ['total'=>0,'success'=>0,'failed'=>0,'avg_ms'=>null];
  }
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $total   = intval($r['total'] ?? 0);
  $success = intval($r['success'] ?? 0);
  $failed  = intval($r['failed'] ?? 0);
  $avg_ms  = isset($r['avg_ms']) ? (int)round($r['avg_ms']) : null;

  $spct = $total > 0 ? round(($success / $total) * 100, 1) : 0.0;
  $fpct = $total > 0 ? round(($failed  / $total) * 100, 1) : 0.0;

  return [
    'total'       => $total,
    'success'     => $success,
    'failed'      => $failed,
    'success_pct' => $spct,
    'failed_pct'  => $fpct,
    'avg_ms'      => $avg_ms
  ];
}

$stats_all   = stats_query($mysqli, $where, $types, $params, '');
$stats_today = stats_query($mysqli, $where, $types, $params, 'created_at >= ? AND created_at < ?');

/* next_cursor for the returned page */
$next_cursor = null;
$next_cursor_ts = null;
if (!empty($rows)) {
  $last = $rows[count($rows)-1];
  $next_cursor    = $last['id'];
  $next_cursor_ts = $last['created_at'];
}

echo json_encode([
  'ok'             => true,
  'items'          => $rows,
  'order'          => strtolower($order),
  'limit'          => $limit,
  'using_cursor'   => $using_cursor,
  'next_cursor'    => $next_cursor,      // pass as before_id on the next call when order=desc
  'next_cursor_ts' => $next_cursor_ts,   // optional: pass as before_ts
  'has_more'       => (count($rows) === $limit),
  'stats'          => [
    'all'   => $stats_all,
    'today' => $stats_today
  ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
