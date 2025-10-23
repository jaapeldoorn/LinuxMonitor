<?php
$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
date_default_timezone_set($cfg['app']['timezone'] ?? 'UTC');

try {
  // Retrieve devicelist direct from database
  $pdo = get_pdo($cfg);
  $stmt = $pdo->query("SELECT DISTINCT SUBSTRING_INDEX(`keystr`, '.', 1) AS prefix FROM metrics");
  $devicelist = $stmt->fetchAll();
  }
catch (Throwable $e) {
  $devicelist = [];
  }

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Linux Monitor - Server status</title>
  <link rel="icon" href="./img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css" />
  <script src="https://kit.fontawesome.com/c0aedb2046.js" crossorigin="anonymous"></script>
  <script src="https://unpkg.com/justgage@latest/dist/justgage.umd.js"></script>
</head>

<body>
  <header>
    <div class="lm-header">
      <div>
        <img src="./img/Logo64.png">
      </div>
      <div>
        <h1>Linux Monitoring</h1>
        <p>Realtime monitoring dashboard</p>
      </div>
    </div>
    <div class="lm-page-button">
      <img src="./img/gauge.svg" class="icon active"/>
      <a href="monitor.php"><img src="./img/chart-line.svg" class="icon inactive"/></a>
    </div>
  </header>
  <main>
    <section class="lm-menu">
      <label>Server IS NOT WORKING:
        <select id="device">
          <?php foreach ($devicelist as $d) : ?>
            <option value="<?= htmlspecialchars($d['prefix']) ?>" <?= ($d['prefix'] === $cfg['app']['default_device']) ? ' selected' : '' ?>><?= htmlspecialchars($d['prefix']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button id="refresh">Refresh</button>
      <label><input type="checkbox" id="autorefresh" data-interval=" <?= $cfg['app']['mon_refresh_seconds'] ?> " checked /> Auto-refresh (<?= $cfg['app']['mon_refresh_seconds'] ?>s)</label>
    </section>
    <div class="lm-columns">
      <?php foreach ($cfg['sections'] as $sIndex => $section):
    ?>


<?php
if ($section['system'] === 'all' || $section['system'] === $cfg['app']['default_device']) {
?>



      <div class="lm-section">
        <div class="lm-section-title">
<?php
$logo = htmlspecialchars($section['logo'] ?? 'NoLogo');
if (strpos($logo, '.') !== false) {
    echo '<img src="img/' . $logo . '" alt="Logo">';
} else {
    // Geen punt, dus waarschijnlijk een Font Awesome icoon
echo '<i class="fa-solid fa-2x fa-' . $logo . '"></i>';
}
?>
        <?= htmlspecialchars($section['title'] ?? 'NoSectionTitle') ?>
        </div>
        <div class='lm-section-text'>

          <?php foreach (($section['elements'] ?? []) as $cIndex => $element):
            switch ($element['type']){
              case 'badge':
                $pdo = get_pdo($cfg);
                $stmt = $pdo->query("SELECT `txt-status`.string, metrics.description FROM `txt-status` JOIN metrics on `txt-status`.metric_id = metrics.id where `txt-status`.metric_id = " . $element['ID']);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                echo '<p><span class="badge bg-primary">' .  $row['string'] . '</span> '. $row['description'].'</p>';
                break;
              case 'UFT-string':
                $pdo = get_pdo($cfg);
                $stmt = $pdo->query("SELECT samples.value, metrics.unit FROM `samples` JOIN metrics on samples.metric_id = metrics.id where samples.metric_id = " . $element['ID-used'] . " order by samples.ts DESC limit 1;");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $used = $row['value'];
                $unit = $row['unit'];
                $stmt = $pdo->query("SELECT value FROM `samples` where metric_id = " . $element['ID-total'] . " order by ts DESC limit 1;");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $total = $row['value'];
                $free = $total - $used;
                echo '<p>Used: <b>' . round($used,$element['decimals']) . $unit  . '</b> | Free: <b>' . round($free,$element['decimals']) . $unit .'</b> | Total: <b>' . round($total,$element['decimals']) . $unit . '</b></p>';
                break;
              case 'subtitle':
                echo '<p><b>' . $element['txt'] . '</b></p>';
                break;
              case 'bar':
                $pdo = get_pdo($cfg);
                $stmt = $pdo->query("SELECT value FROM `samples` where metric_id = " . $element['ID-part'] . " order by ts DESC limit 1;");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $part = $row['value'];
                $stmt = $pdo->query("SELECT value FROM `samples` where metric_id = " . $element['ID-total'] . " order by ts DESC limit 1;");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $total = $row['value'];
                $fraction = $part / $total * 100;
                echo '<div class="progress" style="height:20px">';
                echo '<div class="progress-bar" style="width:' . $fraction . '%">' . round($fraction) . '%</div>';
                echo '</div>';
                break;
              case 'text':
                switch ($element['vartype']){
                  case 'string':
                    $pdo = get_pdo($cfg);
                    $stmt = $pdo->query("SELECT string FROM `txt-status` where metric_id = " . $element['ID'] . ";");
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {$str = htmlspecialchars($row['string']);} else {$str = "No record found";}
                    break;
                  case 'float':
                    $pdo = get_pdo($cfg);
                    $stmt = $pdo->query("SELECT value FROM `samples` where metric_id = " . $element['ID'] . " order by ts DESC limit 1;");
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (is_numeric($element['decimals'] ?? '0')) {
                      if ($row) {
                        $str = round($row['value'], (int)$element['decimals']);
                      } else {
                        $str = "No record found";
                      }
                    }
                    break;
                  default:
                    $str = 'Unknown element type: ' . $element['type'];
                }
                echo '<p>' . $element['pre_txt'] . '<b>' . $str . '</b>' . $element['post_txt'] .'</p>';
                break;
              case 'gauge':
                $gaugeMetricID1 = $element['ID1'] ?? '';
                $gaugeMetricID2 = $element['ID2'] ?? '';
                $gaugeMetricID3 = $element['ID3'] ?? '';
                if ($gaugeMetricID1 <> '') {
                  $pdo = get_pdo($cfg);
                  $stmt = $pdo->query("SELECT samples.value, metrics.description, metrics.unit FROM `samples` JOIN metrics on samples.metric_id = metrics.id where samples.metric_id = " . $gaugeMetricID1 . " order by samples.ts DESC limit 1;");
                  $row = $stmt->fetch(PDO::FETCH_ASSOC);
                  echo '<style> #lm-gauge-' . $gaugeMetricID1 .'{ width: 100px; height: 100px; } </style>';
                  echo '<div class="lm-gauge" id="lm-gauge-' . $gaugeMetricID1 . '"></div>';
                  echo '<script> new JustGage({id: "lm-gauge-' . $gaugeMetricID1 . '", value: ' . $row['value'] . ', min: ' . $element['min'] . ', max: ' . $element['max'] . ', decimals: ' . $element['decimals'] .', title: "' . $row['description'] . '", label: "' . $row['unit'] . '" }); </script>';
                }
                if ($gaugeMetricID2 <> '') {
                  $pdo = get_pdo($cfg);
                  $stmt = $pdo->query("SELECT samples.value, metrics.description, metrics.unit FROM `samples` JOIN metrics on samples.metric_id = metrics.id where samples.metric_id = " . $gaugeMetricID2 . " order by samples.ts DESC limit 1;");
                  $row = $stmt->fetch(PDO::FETCH_ASSOC);
                  echo '<style> #lm-gauge-' . $gaugeMetricID2 .'{ width: 100px; height: 100px; } </style>';
                  echo '<div class="lm-gauge" id="lm-gauge-' . $gaugeMetricID2 . '"></div>';
                  echo '<script> new JustGage({id: "lm-gauge-' . $gaugeMetricID2 . '", value: ' . $row['value'] . ', min: ' . $element['min'] . ', max: ' . $element['max'] . ', decimals: ' . $element['decimals'] .', title: "' . $row['description'] . '", label: "' . $row['unit'] . '" }); </script>';
                }
                if ($gaugeMetricID3 <> '') {
                  $pdo = get_pdo($cfg);
                  $stmt = $pdo->query("SELECT samples.value, metrics.description, metrics.unit FROM `samples` JOIN metrics on samples.metric_id = metrics.id where samples.metric_id = " . $gaugeMetricID3 . " order by samples.ts DESC limit 1;");
                  $row = $stmt->fetch(PDO::FETCH_ASSOC);
                  echo '<style> #lm-gauge-' . $gaugeMetricID3 .'{ width: 100px; height: 100px; } </style>';
                  echo '<div class="lm-gauge" id="lm-gauge-' . $gaugeMetricID3 . '"></div>';
                  echo '<script>  new JustGage({id: "lm-gauge-' . $gaugeMetricID3 . '", value: ' . $row['value'] . ', min: ' . $element['min'] . ', max: ' . $element['max'] . ', decimals: ' . $element['decimals'] .', title: "' . $row['description'] . '", label: "' . $row['unit'] . '" }); </script>';
                }
                break;
              case 'package':
                echo '<p>PACKAGE placeholder</p>';
              default:
                echo '<p>Unknown element type: ' . $element['type'] . '</p>';
            }




          ?>
          <?php endforeach; ?>


        </div>
      </div>
    <?php } endforeach; ?>

    </div>
    <div class="muted">Laatste update: <span id="lastUpdate">—</span></div>
  <div class="container">

    <?php foreach ($cfg['sections'] as $sIndex => $section):
      $cols = max(1, (int)($section['columns'] ?? 3));
      $gridStyle = "grid-template-columns: repeat($cols, minmax(0, 1fr));";
    ?>
      <div class="section">
        <div class="section-title"><?= htmlspecialchars($section['title'] ?? 'Sectie') ?></div>
        <div class="grid" style="<?= $gridStyle ?>">
          <?php foreach (($section['cards'] ?? []) as $cIndex => $card):
            $cid = "card-{$sIndex}-{$cIndex}";
            $metricKey = $card['metric_key'];
            $type = $card['type'] ?? 'text';
            $label = $card['label'] ?? $metricKey;
            $unit  = $card['unit'] ?? '';
            $min   = $card['min'] ?? 0;
            $max   = $card['max'] ?? 100;
            $dec   = $card['decimals'] ?? ($cfg['defaults']['decimals'] ?? 1);
            $timespan = $card['timespan_minutes'] ?? 60;
            $thresholds = $card['thresholds'] ?? ($cfg['defaults']['thresholds'] ?? []);
            $scale  = $card['scale']  ?? 1;
            $offset = $card['offset'] ?? 0;
            $clamp  = $card['clamp']  ?? false;
          ?>
            <div class="card"
                 id="<?= $cid ?>"
                 data-metric-key="<?= htmlspecialchars($metricKey) ?>"
                 data-type="<?= htmlspecialchars($type) ?>"
                 data-label="<?= htmlspecialchars($label) ?>"
                 data-unit="<?= htmlspecialchars($unit) ?>"
                 data-min="<?= htmlspecialchars($min) ?>"
                 data-max="<?= htmlspecialchars($max) ?>"
                 data-decimals="<?= htmlspecialchars($dec) ?>"
                 data-timespan="<?= htmlspecialchars($timespan) ?>"
                 data-thresholds='<?= json_encode($thresholds) ?>'
                 data-scale="<?= htmlspecialchars($scale) ?>"
                 data-offset="<?= htmlspecialchars($offset) ?>"
                 data-clamp='<?= json_encode($clamp) ?>'
            >
              <h3><?= htmlspecialchars($label) ?></h3>
              <?php if (in_array($type, ['progress','gauge','line'])): ?>
                <canvas></canvas>
              <?php endif; ?>
              <?php if ($type === 'text'): ?>
                <div class="value-row"><span class="value">—</span> <span class="unit"><?= htmlspecialchars($unit) ?></span></div>
              <?php endif; ?>
              <div class="muted ts">—</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
<i class="fa-sharp fa-solid fa-user"></i>
  </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="autorefresh.js"></script>

  <script>
    const REFRESH_MS = <?= (int)($cfg['app']['refresh_seconds'] ?? 10) ?> * 1000;

    function fmt(value, decimals) {
      if (value === null || value === undefined || isNaN(value)) return '—';
      return Number(value).toFixed(decimals);
    }

    function pickColor(value, thresholds, min, max) {
      if (!Array.isArray(thresholds) || thresholds.length === 0) return '#3498db';
      // Normaliseer indien min/max opgegeven
      let v = Number(value);
      if (isFinite(min) && isFinite(max) && max > min) {
        v = Math.max(min, Math.min(max, v));
        const pct = ((v - min) / (max - min)) * 100;
        for (const th of thresholds) {
          if (typeof th.upto === 'number' && pct <= th.upto) return th.color;
        }
        return thresholds[thresholds.length - 1].color || '#3498db';
      } else {
        // Zonder normalisatie: gebruik absolute waardes
        for (const th of thresholds) {
          if (typeof th.upto === 'number' && v <= th.upto) return th.color;
        }
        return thresholds[thresholds.length - 1].color || '#3498db';
      }
    }

    function applyTransform(x, scale, offset, clamp) {
      let y = Number(x) * Number(scale || 1) + Number(offset || 0);
      if (Array.isArray(clamp) && clamp.length === 2) {
        y = Math.max(Number(clamp[0]), Math.min(Number(clamp[1]), y));
      }
      return y;
    }

    function isoToLocal(iso) {
      try {
        const d = new Date(iso.replace(' ', 'T'));
        return d.toLocaleString();
      } catch { return '—'; }
    }

    const charts = new Map();

    async function fetchLatest(metricKey) {
      const res = await fetch(`apistat.php?action=latest&metric_key=${encodeURIComponent(metricKey)}`);
      return res.json();
    }
    async function fetchSeries(metricKey, minutes) {
      const res = await fetch(`apistat.php?action=timeseries&metric_key=${encodeURIComponent(metricKey)}&minutes=${minutes}`);
      return res.json();
    }

    function ensureChart(canvas, type, label, min, max, thresholds) {
      if (charts.has(canvas)) return charts.get(canvas);

      let cfg;
      if (type === 'progress') {
        cfg = {
          type: 'bar',
          data: { labels: [label], datasets: [{
            label, data: [0],
            backgroundColor: ['#3498db'],
            borderWidth: 0,
          }]},
          options: {
            indexAxis: 'y',
            scales: {
              x: { min, max, grid: { display: false } },
              y: { grid: { display: false } },
            },
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            responsive: true,
            animation: false
          }
        };
      } else if (type === 'gauge') {
        // half doughnut (gauge)
        cfg = {
          type: 'doughnut',
          data: {
            labels: ['Value', 'Remainder'],
            datasets: [{
              data: [0, (max - min)],
              backgroundColor: ['#3498db', '#ecf0f1'],
              borderWidth: 0,
              circumference: 180,
              rotation: -90,
              cutout: '70%',
            }]
          },
          options: {
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            responsive: true,
            animation: false
          }
        };
      } else if (type === 'line') {
        cfg = {
          type: 'line',
          data: { labels: [], datasets: [{
            label, data: [], tension: 0.2, borderColor: '#3498db', fill: false, pointRadius: 0
          }]},
          options: {
            plugins: { legend: { display: false } },
            scales: {
              x: { grid: { display: false } },
              y: { grid: { color: '#eee' } }
            },
            responsive: true,
            animation: false
          }
        };
      } else {
        return null;
      }

      const chart = new Chart(canvas.getContext('2d'), cfg);
      charts.set(canvas, chart);
      return chart;
    }

    async function updateCard(cardEl) {
      const metricKey = cardEl.dataset.metricKey;
      const type = cardEl.dataset.type;
      const label = cardEl.dataset.label || metricKey;
      const unit = cardEl.dataset.unit || '';
      const min = Number(cardEl.dataset.min ?? 0);
      const max = Number(cardEl.dataset.max ?? 100);
      const decimals = Number(cardEl.dataset.decimals ?? 1);
      const thresholds = JSON.parse(cardEl.dataset.thresholds || '[]');
      const scale = Number(cardEl.dataset.scale || 1);
      const offset = Number(cardEl.dataset.offset || 0);
      const clamp = JSON.parse(cardEl.dataset.clamp || 'false');
      const timespan = Number(cardEl.dataset.timespan || 60);

      try {
        if (type === 'line') {
          const { ok, data } = await fetchSeries(metricKey, timespan);
          if (!ok) throw new Error('API fout');
          const canvas = cardEl.querySelector('canvas');
          const chart = ensureChart(canvas, type, label, min, max, thresholds);
          const labels = data.map(p => isoToLocal(p.t));
          const values = data.map(p => applyTransform(p.v, scale, offset, clamp));
          chart.data.labels = labels;
          chart.data.datasets[0].data = values;
          chart.update();
          const last = data.length ? data[data.length - 1] : null;
          cardEl.querySelector('.ts').textContent = last ? isoToLocal(last.t) : '—';
        } else {
          const { ok, data } = await fetchLatest(metricKey);
          if (!ok) throw new Error('API fout');
          const tsEl = cardEl.querySelector('.ts');
          if (!data) {
            if (tsEl) tsEl.textContent = '—';
            if (type === 'text') {
              const vEl = cardEl.querySelector('.value');
              if (vEl) vEl.textContent = '—';
            }
            return;
          }
          const v = applyTransform(data.value, scale, offset, clamp);
          const ts = data.ts;

          if (type === 'text') {
            const vEl = cardEl.querySelector('.value');
            if (vEl) vEl.textContent = fmt(v, decimals);
          } else if (type === 'progress') {
            const canvas = cardEl.querySelector('canvas');
            const chart = ensureChart(canvas, type, label, min, max, thresholds);
            const color = pickColor(v, thresholds, min, max);
            chart.data.datasets[0].data = [v];
            chart.data.datasets[0].backgroundColor = [color];
            chart.update();
          } else if (type === 'gauge') {
            const canvas = cardEl.querySelector('canvas');
            const chart = ensureChart(canvas, type, label, min, max, thresholds);
            const span = max - min;
            const valueSpan = Math.max(0, Math.min(span, v - min));
            const color = pickColor(v, thresholds, min, max);
            chart.data.datasets[0].data = [valueSpan, Math.max(0, span - valueSpan)];
            chart.data.datasets[0].backgroundColor = [color, '#ecf0f1'];
            chart.update();
          }

          if (tsEl) tsEl.textContent = isoToLocal(ts) + (unit ? ` • ${fmt(v, decimals)} ${unit}` : ` • ${fmt(v, decimals)}`);
        }
      } catch (e) {
        console.error('updateCard error', e);
      }
    }

    async function refreshAll() {
      const cards = document.querySelectorAll('.card');
      await Promise.all(Array.from(cards).map(updateCard));
      document.getElementById('lastUpdate').textContent = new Date().toLocaleString();
    }

    window.addEventListener('load', () => {
      refreshAll();
      setInterval(refreshAll, REFRESH_MS);
    });
  </script>
</body>
