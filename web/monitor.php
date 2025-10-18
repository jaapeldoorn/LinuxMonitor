<?php
$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

function random_color()
{
  return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}
// Ophalen van devicelist direct uit de database
try {
  $pdo = get_pdo($cfg);
  $stmt = $pdo->query("SELECT DISTINCT SUBSTRING_INDEX(`keystr`, '.', 1) AS prefix FROM metrics");
  $devicelist = $stmt->fetchAll();

  // Ophalen van alle metrics waar view niet 0 of null is
  $stmt2 = $pdo->query("SELECT * FROM metrics WHERE `view` IS NOT NULL AND `view` != 0 ORDER BY `view` asc");
  $viewable_metrics = $stmt2->fetchAll();
} catch (Throwable $e) {
  $devicelist = [];
}
$metrics_by_view = [];
foreach ($viewable_metrics as $metric) {
  $view = $metric['view'];
  if (!isset($metrics_by_view[$view])) {
    $metrics_by_view[$view] = [];
  }
  $metrics_by_view[$view][] = $metric;
}
?>
<!doctype html>
<html lang="nl">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Linux Monitor - Server monitor</title>
  <link rel="icon" href="./img/favicon.ico">
  <link rel="stylesheet" href="style.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/luxon@3"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1"></script>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body>
  <header>
    <div class="flex flex-col sm:flex-row gap-3">
      <div>
        <img src="./img/Logo64.png">
      </div>
      <div>
        <h1>Linux Monitoring</h1>
        <p>Realtime monitoring dashboard</p>
      </div>
    </div>
    <div class="absolute right-6 top-6 flex flex-row gap-2">
      <a href="status.php"><img src="./img/gauge.svg" class="icon w-8 h-8 bg-stone-500 hover:bg-stone-300"/></a>
      <img src="./img/chart-line.svg" class="icon w-8 h-8 bg-stone-700"/>
    </div>
  </header>
  <main>
    <section class="controls flex flex-col md:flex-row">
      <label>Time window:
        <select id="range">
          <option value="60" <?= ($cfg['app']['default_minutes'] == 60) ? ' selected' : '' ?>>1 hour</option>
          <option value="360" <?= ($cfg['app']['default_minutes'] == 360) ? ' selected' : '' ?>>6 hours</option>
          <option value="1440" <?= ($cfg['app']['default_minutes'] == 1440) ? ' selected' : '' ?>>1 day</option>
          <option value="10080" <?= ($cfg['app']['default_minutes'] == 10080) ? ' selected' : '' ?>>1 week</option>
        </select>
      </label>
      <label>Server:
        <select id="device">
          <?php foreach ($devicelist as $d) : ?>
            <option value="<?= htmlspecialchars($d['prefix']) ?>" <?= ($d['prefix'] === $cfg['app']['default_device']) ? ' selected' : '' ?><?= htmlspecialchars($d['prefix']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button id="refresh">Refresh</button>
      <label><input type="checkbox" id="autorefresh" data-interval=" <?= $cfg['app']['mon_refresh_seconds'] ?> " checked /> Auto-refresh (<?= $cfg['app']['mon_refresh_seconds'] ?>s)</label>
    </section>

    <div class="grid">
      <?php foreach ($metrics_by_view as $view => $metrics): ?>
        <div class="card">
          <h2>View <?= htmlspecialchars($view) ?></h2>
          <canvas id="view<?= htmlspecialchars($view) ?>"></canvas>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
  <script src="appmon.js"></script>
  <script>
    <?php foreach ($metrics_by_view as $view => $metrics): ?>
      addMetricToLoadAll({
        canvasId: 'view<?= htmlspecialchars($view) ?>',
        label: 'View <?= htmlspecialchars($view) ?>',
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
