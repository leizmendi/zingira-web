<?php
// Simple analytics dashboard for Camping Zingira
// Protected by password - change the hash below after first setup

// Password: set via environment or change this hash
// Generate a new hash: php -r "echo password_hash('tu_contraseña', PASSWORD_DEFAULT);"
$passwordHash = getenv('ANALYTICS_PASSWORD_HASH');
if (!$passwordHash) {
    die('Configure ANALYTICS_PASSWORD_HASH en el servidor.');
}

// Basic auth
if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'] ?? '', $passwordHash)) {
    header('WWW-Authenticate: Basic realm="Analytics"');
    http_response_code(401);
    die('Acceso denegado');
}

$logDir = __DIR__ . '/../analytics_logs';
$month = preg_replace('/[^0-9\-]/', '', $_GET['m'] ?? gmdate('Y-m'));
$logFile = $logDir . '/hits_' . $month . '.csv';

if (!file_exists($logFile)) {
    die("No hay datos para $month");
}

$rows = array_map('str_getcsv', file($logFile));
$header = array_shift($rows);

// Stats
$totalHits = count($rows);
$pages = [];
$langs = [];
$days = [];
$referrers = [];

foreach ($rows as $row) {
    if (count($row) < 5) continue;
    $page = $row[2] ?? '';
    $ref = $row[3] ?? '';
    $lang = $row[4] ?? '';
    $day = substr($row[0] ?? '', 0, 10);

    $pages[$page] = ($pages[$page] ?? 0) + 1;
    $langs[$lang] = ($langs[$lang] ?? 0) + 1;
    $days[$day] = ($days[$day] ?? 0) + 1;
    if ($ref && $ref !== '') {
        $referrers[$ref] = ($referrers[$ref] ?? 0) + 1;
    }
}

arsort($pages);
arsort($langs);
arsort($referrers);
ksort($days);

// Available months
$months = [];
foreach (glob($logDir . '/hits_*.csv') as $f) {
    preg_match('/hits_(\d{4}-\d{2})\.csv/', $f, $m);
    if ($m) $months[] = $m[1];
}
sort($months);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Analytics - Camping Zingira</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; background: #f5f5f0; color: #333; }
  h1 { color: #2d5016; }
  h2 { color: #4a7c2e; border-bottom: 2px solid #4a7c2e; padding-bottom: 0.3rem; }
  table { width: 100%; border-collapse: collapse; margin: 1rem 0 2rem; }
  th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
  th { background: #2d5016; color: white; }
  tr:nth-child(even) { background: #e8e8e0; }
  .stat { display: inline-block; background: white; padding: 1rem 1.5rem; border-radius: 8px; margin: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
  .stat strong { display: block; font-size: 2rem; color: #2d5016; }
  select { padding: 0.4rem; font-size: 1rem; }
  .bar { background: #4a7c2e; height: 18px; border-radius: 3px; min-width: 2px; }
</style>
</head>
<body>
<h1>Camping Zingira - Analytics</h1>
<form>
  Mes: <select name="m" onchange="this.form.submit()">
    <?php foreach ($months as $m): ?>
      <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $m ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div>
  <span class="stat">Visitas<strong><?= $totalHits ?></strong></span>
  <span class="stat">Páginas únicas<strong><?= count($pages) ?></strong></span>
  <span class="stat">Días con tráfico<strong><?= count($days) ?></strong></span>
</div>

<h2>Visitas por día</h2>
<table>
  <tr><th>Día</th><th>Visitas</th><th></th></tr>
  <?php $maxDay = max($days ?: [1]); foreach ($days as $d => $c): ?>
  <tr><td><?= htmlspecialchars($d) ?></td><td><?= $c ?></td><td><div class="bar" style="width:<?= round($c / $maxDay * 300) ?>px"></div></td></tr>
  <?php endforeach; ?>
</table>

<h2>Páginas más visitadas</h2>
<table>
  <tr><th>Página</th><th>Visitas</th></tr>
  <?php foreach (array_slice($pages, 0, 20) as $p => $c): ?>
  <tr><td><?= htmlspecialchars($p) ?></td><td><?= $c ?></td></tr>
  <?php endforeach; ?>
</table>

<h2>Idiomas</h2>
<table>
  <tr><th>Idioma</th><th>Visitas</th></tr>
  <?php foreach ($langs as $l => $c): ?>
  <tr><td><?= htmlspecialchars($l) ?></td><td><?= $c ?></td></tr>
  <?php endforeach; ?>
</table>

<?php if ($referrers): ?>
<h2>Referrers</h2>
<table>
  <tr><th>Origen</th><th>Visitas</th></tr>
  <?php foreach (array_slice($referrers, 0, 20) as $r => $c): ?>
  <tr><td><?= htmlspecialchars($r) ?></td><td><?= $c ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</body>
</html>
