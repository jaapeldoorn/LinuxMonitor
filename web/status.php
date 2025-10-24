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

<?php
  $fontAwesomeID = $config['app']['FontAwesomeID'] ?? '';
  if ($fontAwesomeID <> '') {
    echo '  <script src="https://kit.fontawesome.com/' . $fontAwesomeID . '.js" crossorigin="anonymous"></script>';
  }
?>
  <script src="https://unpkg.com/justgage@latest/dist/justgage.umd.js"></script>
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
      <img src="./img/gauge.svg" class="icon active"/>
      <a href="monitor.php"><img src="./img/chart-line.svg" class="icon inactive"/></a>
    </div>
  </header>
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
  <main>
    <div class="lm-columns">
 <?php foreach ($cfg['sections'] as $sIndex => $section):
   if ($section['system'] === 'all' || $section['system'] === $cfg['app']['default_device']) {
     echo '<div class="lm-section">';
     echo '<div class="lm-section-title">';
     $logo = htmlspecialchars($section['logo'] ?? 'NoLogo');
     if (strpos($logo, '.') !== false) {
       echo '<img src="img/' . $logo . '" alt="Logo">';
     } else {
       // Geen punt, dus waarschijnlijk een Font Awesome icoon
       echo '<i class="fa-solid fa-2x fa-' . $logo . '"></i>';
     }
     echo htmlspecialchars($section['title'] ?? 'NoSectionTitle');
        echo '</div>';
        echo '<div class="lm-section-text">';
          foreach (($section['elements'] ?? []) as $cIndex => $element):
            switch ($element['type']){
              case 'badge':
                $pdo = get_pdo($cfg);
                $stmt = $pdo->query("SELECT `txt-status`.string, metrics.name FROM `txt-status` JOIN metrics on `txt-status`.metric_id = metrics.id where `txt-status`.metric_id = " . $element['ID']);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                echo '<p><span class="badge bg-primary">' .  $row['string'] . '</span> '. $row['name'].'</p>';
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
                $strPreTxt = $element['pre_txt'] ?? '';
                $strPostTxt = $element['post_txt'] ?? '';
                echo '<p>' . $strPreTxt . '<b>' . $str . '</b>' . $strPostTxt .'</p>';
                break;
              case 'gauge':
                $gaugeMetricID1 = $element['ID1'] ?? '';
                $gaugeMetricID2 = $element['ID2'] ?? '';
                $gaugeMetricID3 = $element['ID3'] ?? '';
                echo '<div class="lm-gauges">';
                $standardgaugestyles = 'valueFontColor: "#FFFFFF"';
                foreach ([$gaugeMetricID1,$gaugeMetricID2,$gaugeMetricID3] as $gaugeMetric) {
                  if ($gaugeMetric <> '') {
                    $pdo = get_pdo($cfg);
                    $stmt = $pdo->query("SELECT samples.value, metrics.name, metrics.unit FROM `samples` JOIN metrics on samples.metric_id = metrics.id where samples.metric_id = " . $gaugeMetric . " order by samples.ts DESC limit 1;");
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo '<div class="lm-gauge" id="lm-gauge-' . $gaugeMetric . '"></div>';
                    echo '<script> new JustGage({id: "lm-gauge-' . $gaugeMetric . '", value: ' . $row['value'] . ', min: ' . $element['min'] . ', max: ' . $element['max'] . ', decimals: ' .
 $element['decimals'] .', title: "' . $row['name'] . '", label: "' . $row['unit'] . '", '. $standardgaugestyles .' }); </script>';
                  }
                }
                echo '</div>';
                break;
              case 'package':
                echo '<p>PACKAGE placeholder</p>';
              default:
                echo '<p>Unknown element type: ' . $element['type'] . '</p>';
            }
          endforeach;
        echo '</div>';
      echo '</div>';
    } endforeach; ?>

    </div>
    <div class="muted">Last update: <span id="lastUpdate">—</span></div>
  </main>

  <script src="autorefresh.js"></script>

  <script>
    const REFRESH_MS = <?= (int)($cfg['app']['refresh_seconds'] ?? 10) ?> * 1000;

    async function refreshAll() {
      document.getElementById('lastUpdate').textContent = new Date().toLocaleString();
    }

    window.addEventListener('load', () => {
      refreshAll();
      setInterval(refreshAll, REFRESH_MS);
    });
  </script>
</body>
