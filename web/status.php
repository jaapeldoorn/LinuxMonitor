<?php
// index.php
$cfg = require __DIR__ . '/config.php';
date_default_timezone_set($cfg['app']['timezone'] ?? 'UTC');
?>
<!doctype html>
<html lang="nl">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Linux Monitor - Server status</title>
  <link rel="icon" href="./img/favicon.ico">
  <link rel="stylesheet" href="style.css" />
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
    <section class="lm-menu">
      <label><input type="checkbox" id="autorefresh" data-interval=" <?= $cfg['app']['mon_refresh_seconds'] ?> " checked /> Auto-refresh (<?= $cfg['app']['mon_refresh_seconds'] ?>s)</label>
    </section>
  <main>
  <div class="container">
    <h1 style="margin:0 0 8px;">Titel tbd</h1>
    <div class="muted">Laatste update: <span id="lastUpdate">—</span></div>

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
