
<?php
header('Content-Type: application/json');
$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

function db_connect($cfg)
{
    try {
        return get_pdo($cfg);
    } catch (Throwable $e) {
        error_response(500, 'DB connection failed');
    }
}

function error_response($code, $msg)
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function fetch_metric_meta($pdo, $key)
{
    $stmt = $pdo->prepare("SELECT id, name, unit FROM metrics WHERE `keystr` = ?");
    $stmt->execute([$key]);
    $meta = $stmt->fetch();
    if (!$meta) {
        error_response(404, 'metric not found');
    }
    return $meta;
}

function fetch_metric_data($pdo, $metric_id, $minutes)
{
    $stmt = $pdo->prepare(
        "SELECT ts, value FROM samples WHERE metric_id = ? AND ts >= (UTC_TIMESTAMP(6) - INTERVAL ? MINUTE) ORDER BY ts ASC"
    );
    $stmt->execute([$metric_id, $minutes]);
    return $stmt->fetchAll();
}

function fetch_device_list($pdo)
{
    $stmt = $pdo->query("SELECT DISTINCT SUBSTRING_INDEX(`keystr`, '.', 1) AS prefix FROM metrics");
    return $stmt->fetchAll();
}

function format_output($key, $meta, $data)
{
    return [
        'metric' => $key,
        'name' => $meta['name'],
        'unit' => $meta['unit'],
        'points' => array_map(function ($r) {
            return [
                't' => $r['ts'],
                'v' => (float)$r['value']
            ];
        }, $data)
    ];
}


$pdo = db_connect($cfg);
$action = $_GET['action'] ?? 'data';

if ($action === 'list') {
    $stmt = $pdo->query("SELECT id, `keystr`, name, unit, description FROM metrics ORDER BY `keystr`");
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'viewable') {
    $stmt = $pdo->query("SELECT * FROM metrics WHERE `view` IS NOT NULL AND `view` != 0 ORDER BY `keystr`");
    echo json_encode($stmt->fetchAll());
    exit;
}

$key = $_GET['metric'] ?? null;
$minutes = intval($_GET['minutes'] ?? $cfg['default_minutes']);
if (!$key) {
    error_response(400, 'parameter "metric" is missing');
}

$meta = fetch_metric_meta($pdo, $key);
$data = fetch_metric_data($pdo, $meta['id'], $minutes);
$out = format_output($key, $meta, $data);

echo json_encode($out);
