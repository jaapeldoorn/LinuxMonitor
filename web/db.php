<?php
// db.php
function get_pdo(array $cfg): PDO {
  $dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['db']['host'],
    $cfg['db']['port'],
    $cfg['db']['dbname'],
    $cfg['db']['charset']
  );

  $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Zorg dat de DB timezone klopt met app/timezone
  if (!empty($cfg['app']['timezone'])) {
    $tz = new DateTimeZone($cfg['app']['timezone']);
    $now = new DateTime('now', $tz);
    $offset = $now->format('P'); // ±HH:MM
    $pdo->exec("SET time_zone = '$offset'");
  }

  return $pdo;
}
