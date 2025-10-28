<?php
$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

function normalize_id($string)
{
  return preg_replace('/[^a-zA-Z0-9_]/', '_', $string);
}

function random_color()
{
  return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}
try {
  // Retrieve devicelist from database
  $pdo = get_pdo($cfg);
  $stmt = $pdo->query("SELECT DISTINCT SUBSTRING_INDEX(`keystr`, '.', 1) AS prefix FROM metrics");
  $devicelist = $stmt->fetchAll();

  // Retrieve all metrics where view not 0 or null
  $stmt2 = $pdo->query("SELECT metrics.*, views.view_name FROM metrics LEFT JOIN views ON metrics.view = views.view_id WHERE metrics.view IS NOT NULL AND metrics.view != 0 ORDER BY metrics.view asc");
  $viewable_metrics = $stmt2->fetchAll();
} catch (Throwable $e) {
  $devicelist = [];
}
$metrics_by_view = [];
foreach ($viewable_metrics as $metric) {
  $view_label = $metric['view_name'] ?? $metric['view']; // fallback naar view-id
  if (!isset($metrics_by_view[$view_label])) {
    $metrics_by_view[$view_label] = [];
  }
  $metrics_by_view[$view_label][] = $metric;
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Linux Monitor - Server monitor</title>
  <link rel="icon" href="./img/favicon.ico">
  <link rel="stylesheet" href="style.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/luxon@3"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1"></script>
</head>

<body>
  <header>
    <div class="lm-header">
      <div>
        <img src="./img/Logo64.png">
      </div>
      <div>
        <p class="lm-appname">Linux Monitoring</p>
        <p>Realtime monitoring dashboard</p>
      </div>
    </div>
    <div class="lm-page-button">
      <a href="status.php"><img src="./img/gauge.svg" class="icon inactive"></a>
      <img src="./img/chart-line.svg" class="icon active" />
    </div>
  </header>
  <section class="lm-menu">
    <label>Time window:
      <select id="range">
        <option value="60" <?= ($cfg['app']['default_minutes'] == 60) ? ' selected' : '' ?>>1 hour</option>
        <option value="360" <?= ($cfg['app']['default_minutes'] == 360) ? ' selected' : '' ?>>6 hours</option>
        <option value="1440" <?= ($cfg['app']['default_minutes'] == 1440) ? ' selected' : '' ?>>1 day</option>
        <option value="10080" <?= ($cfg['app']['default_minutes'] == 10080) ? ' selected' : '' ?>>1 week</option>
        <option value="43200" <?= ($cfg['app']['default_minutes'] == 43200) ? ' selected' : '' ?>>1 month</option>
        <option value="131500" <?= ($cfg['app']['default_minutes'] == 131500) ? ' selected' : '' ?>>3 months</option>
        <option value="10080">1 year</option>
        <option value="10080">2 years</option>
        <option value="10080">5 years</option>
      </select>
    </label>
    <label>Server:
      <select id="device">
        <?php foreach ($devicelist as $d) : ?>
          <option value="<?= htmlspecialchars($d['prefix']) ?>" <?= ($d['prefix'] === $cfg['app']['default_device']) ? ' selected' : '' ?>><?= htmlspecialchars($d['prefix']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><input type="checkbox" id="autorefresh" data-interval=" <?= $cfg['app']['mon_refresh_seconds'] ?> " checked /> Auto-refresh (<?= $cfg['app']['mon_refresh_seconds'] ?>s)</label>
  </section>
  <main>
    <div class="grid">
      <?php foreach ($metrics_by_view as $view_label => $metrics):
        $canvasId = 'view' . normalize_id($view_label);
      ?>
        <div class="card">
          <canvas id=<?= $canvasId ?>></canvas>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
  <script src="appmon.js"></script>
  <script>
    <?php foreach ($metrics_by_view as $view_label => $metrics):
      $canvasId = 'view' . normalize_id($view_label);
    ?>
      addMetricToLoadAll({
        canvasId: '<?= $canvasId ?>',
        label: '<?= htmlspecialchars($view_label) ?>',
        series: [
          <?php foreach ($metrics as $metric): ?> {
              metric: '<?= htmlspecialchars($metric['keystr']) ?>',
              color: '<?= htmlspecialchars($metric['color'] ?? random_color()) ?>',
              label: '<?= htmlspecialchars($metric['name']) ?>'
            },
          <?php endforeach; ?>
        ]
      });
    <?php endforeach; ?>
  </script>
</body>

</html>
