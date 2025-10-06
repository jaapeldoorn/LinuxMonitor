<?php
// api.php
header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

$pdo = get_pdo($cfg);

function error_json($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

$action = $_GET['action'] ?? 'latest';
$metricKey = $_GET['metric_key'] ?? null;
if (!$metricKey) {
  error_json('metric_key is verplicht');
}

// resolve metric_id by key
$sqlMetric = sprintf("SELECT id, keystr, description, unit FROM metrics WHERE keystr = :key LIMIT 1");
$stmt = $pdo->prepare($sqlMetric);
$stmt->execute([':key' => $metricKey]);
$metric = $stmt->fetch();
if (!$metric) error_json("Onbekende metric_key: $metricKey", 404);

$metricId = $metric['id'];

// latest value
if ($action === 'latest') {
  $sql = sprintf("SELECT ts, value FROM samples WHERE metric_id = :mid ORDER BY ts DESC LIMIT 1");
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':mid' => $metricId]);
  $row = $stmt->fetch();

  if (!$row) {
    echo json_encode(['ok' => true, 'metric' => $metric, 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode([
    'ok' => true,
    'metric' => $metric,
    'data' => [
      'ts' => $row['ts'],
      'value' => is_numeric($row['value']) ? (float)$row['value'] : $row['value'],
    ],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// timeseries
if ($action === 'timeseries') {
  $minutes = max(1, (int)($_GET['minutes'] ?? 60));
  $cutoff = (new DateTime("-{$minutes} minutes", new DateTimeZone($cfg['app']['timezone'] ?? 'UTC')))->format('Y-m-d H:i:s');

  $sql = sprintf("SELECT ts, value FROM samples WHERE metric_id = :mid AND ts >= :cutoff ORDER BY ts ASC");
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':mid' => $metricId, ':cutoff' => $cutoff]);

  $rows = $stmt->fetchAll();
  $series = array_map(function($r) {
    return [
      't' => $r['ts'],
      'v' => is_numeric($r['value']) ? (float)$r['value'] : null,
    ];
  }, $rows);

  echo json_encode(['ok' => true, 'metric' => $metric, 'data' => $series], JSON_UNESCAPED_UNICODE);
  exit;
}

