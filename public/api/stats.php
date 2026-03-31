<?php
// Simple analytics dashboard for Camping Zingira
// Protected by password - change the hash below after first setup

// Password: hardcoded hash (bcrypt)
// To change: php -r "echo password_hash('nueva_contraseña', PASSWORD_DEFAULT);"
$passwordHash = getenv('ANALYTICS_PASSWORD_HASH')
    ?: '$2y$10$HPmuGhYse8GHn8eaANB3ReD8m9tSUMIpUnhU6/ziKWOwM5C5khf6W';

// Basic auth (compatible with CGI/FastCGI)
$authUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$authPass = $_SERVER['PHP_AUTH_PW'] ?? '';
if (empty($authUser) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $decoded = base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6));
    if ($decoded && strpos($decoded, ':') !== false) {
        list($authUser, $authPass) = explode(':', $decoded, 2);
    }
}
if (empty($authUser) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $decoded = base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6));
    if ($decoded && strpos($decoded, ':') !== false) {
        list($authUser, $authPass) = explode(':', $decoded, 2);
    }
}
if (empty($authUser) || !password_verify($authPass, $passwordHash)) {
    header('WWW-Authenticate: Basic realm="Analytics"');
    http_response_code(401);
    die('Acceso denegado');
}

$logDir = __DIR__ . '/../analytics_logs';
$month = preg_replace('/[^0-9\-]/', '', $_GET['m'] ?? gmdate('Y-m'));
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'visits');

// ===== VISITS DATA =====
$logFile = $logDir . '/hits_' . $month . '.csv';

if (!file_exists($logFile)) {
    $totalHits = 0;
    $pages = $langs = $days = $referrers = [];
} else {
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
}

// ===== ERRORS/BLOCKS DATA =====
$errFile = $logDir . '/errors_' . $month . '.csv';
$total403 = 0;
$total404 = 0;
$total429 = 0;
$totalScans = 0;
$totalBots = 0;
$totalSuspicious = 0;
$errUrls = [];
$errDays = [];
$botNames = [];
$errIps = [];
$typeStats = ['bot' => 0, 'scan' => 0, 'suspicious' => 0, 'human' => 0, 'ratelimit' => 0];

if (file_exists($errFile)) {
    $errRows = array_map('str_getcsv', file($errFile));
    array_shift($errRows); // skip header

    foreach ($errRows as $row) {
        if (count($row) < 9) continue;
        $errStatus = (int)($row[1] ?? 404);
        $errIp = $row[2] ?? '';
        $errUrl = $row[4] ?? '';
        $errType = $row[7] ?? 'human';
        $errBot = $row[8] ?? '';
        $errDay = substr($row[0] ?? '', 0, 10);

        if ($errStatus === 403) $total403++;
        if ($errStatus === 404) $total404++;
        if ($errStatus === 429) $total429++;

        $typeStats[$errType] = ($typeStats[$errType] ?? 0) + 1;
        $errUrls[$errUrl] = ($errUrls[$errUrl] ?? 0) + 1;
        $errDays[$errDay] = ($errDays[$errDay] ?? 0) + 1;
        $errIps[$errIp] = ($errIps[$errIp] ?? 0) + 1;
        if ($errBot) {
            $botNames[$errBot] = ($botNames[$errBot] ?? 0) + 1;
        }
    }

    arsort($errUrls);
    arsort($botNames);
    arsort($errIps);
    ksort($errDays);
}
$totalErrors = $total403 + $total404 + $total429;

// Available months (from hits and errors logs)
$months = [];
foreach (glob($logDir . '/hits_*.csv') as $f) {
    preg_match('/hits_(\d{4}-\d{2})\.csv/', $f, $m);
    if ($m) $months[] = $m[1];
}
foreach (glob($logDir . '/errors_*.csv') as $f) {
    preg_match('/errors_(\d{4}-\d{2})\.csv/', $f, $m);
    if ($m) $months[] = $m[1];
}
$months = array_unique($months);
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
  .bar-red { background: #c0392b; height: 18px; border-radius: 3px; min-width: 2px; }
  .bar-orange { background: #e67e22; height: 18px; border-radius: 3px; min-width: 2px; }
  .tabs { display: flex; gap: 0; margin: 1.5rem 0 1rem; }
  .tab { padding: 0.6rem 1.5rem; background: #ddd; border: none; font-size: 1rem; cursor: pointer; border-radius: 8px 8px 0 0; text-decoration: none; color: #333; }
  .tab.active { background: #2d5016; color: white; }
  .stat.warn strong { color: #c0392b; }
  .stat.alert strong { color: #e67e22; }
  .url-cell { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 0.85rem; }
</style>
</head>
<body>
<h1>Camping Zingira - Analytics</h1>
<form>
  <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
  Mes: <select name="m" onchange="this.form.submit()">
    <?php foreach ($months as $m): ?>
      <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $m ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="tabs">
  <a class="tab <?= $tab === 'visits' ? 'active' : '' ?>" href="?m=<?= $month ?>&tab=visits">Visitas</a>
  <a class="tab <?= $tab === 'security' ? 'active' : '' ?>" href="?m=<?= $month ?>&tab=security">Seguridad / 404</a>
</div>

<?php if ($tab === 'visits'): ?>
<!-- ==================== VISITS TAB ==================== -->

<div>
  <span class="stat">Visitas<strong><?= $totalHits ?></strong></span>
  <span class="stat">Páginas únicas<strong><?= count($pages) ?></strong></span>
  <span class="stat">Días con tráfico<strong><?= count($days) ?></strong></span>
</div>

<?php if ($totalHits === 0): ?>
<p>No hay datos de visitas para <?= htmlspecialchars($month) ?>.</p>
<?php else: ?>

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
<?php endif; ?>

<?php else: ?>
<!-- ==================== SECURITY TAB ==================== -->

<div>
  <span class="stat warn">Bloqueados (403)<strong><?= $total403 ?></strong></span>
  <span class="stat alert">No encontrados (404)<strong><?= $total404 ?></strong></span>
  <span class="stat warn">Rate-limited (429)<strong><?= $total429 ?></strong></span>
  <span class="stat">Total errores<strong><?= $totalErrors ?></strong></span>
</div>

<h2>Clasificación de accesos</h2>
<table>
  <tr><th>Tipo</th><th>Cantidad</th><th>Descripción</th></tr>
  <tr><td>🤖 bot</td><td><?= $typeStats['bot'] ?></td><td>Bot conocido (crawler, scraper)</td></tr>
  <tr><td>🔴 scan</td><td><?= $typeStats['scan'] ?></td><td>Escáner/ataque (bot + ruta sospechosa)</td></tr>
  <tr><td>⚠️ suspicious</td><td><?= $typeStats['suspicious'] ?></td><td>Ruta sospechosa (UA no-bot)</td></tr>
  <tr><td>👤 human</td><td><?= $typeStats['human'] ?></td><td>Posible usuario real</td></tr>
  <tr><td>🚫 ratelimit</td><td><?= $typeStats['ratelimit'] ?></td><td>Bloqueado por exceso (>5/min)</td></tr>
</table>

<?php if ($totalErrors > 0): ?>

<h2>Errores por día</h2>
<table>
  <tr><th>Día</th><th>Errores</th><th></th></tr>
  <?php $maxErrDay = max($errDays ?: [1]); foreach ($errDays as $d => $c): ?>
  <tr><td><?= htmlspecialchars($d) ?></td><td><?= $c ?></td><td><div class="bar-red" style="width:<?= round($c / $maxErrDay * 300) ?>px"></div></td></tr>
  <?php endforeach; ?>
</table>

<h2>URLs más solicitadas (top 30)</h2>
<table>
  <tr><th>URL</th><th>Veces</th></tr>
  <?php foreach (array_slice($errUrls, 0, 30) as $u => $c): ?>
  <tr><td class="url-cell" title="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></td><td><?= $c ?></td></tr>
  <?php endforeach; ?>
</table>

<?php if ($botNames): ?>
<h2>Bots detectados</h2>
<table>
  <tr><th>Identificador</th><th>Accesos</th></tr>
  <?php foreach (array_slice($botNames, 0, 20) as $b => $c): ?>
  <tr><td><?= htmlspecialchars($b) ?></td><td><?= $c ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>IPs más activas (anonimizadas, top 20)</h2>
<table>
  <tr><th>IP (anon)</th><th>Accesos</th><th></th></tr>
  <?php $maxIp = max($errIps ?: [1]); foreach (array_slice($errIps, 0, 20) as $ip => $c): ?>
  <tr><td style="font-family:monospace"><?= htmlspecialchars($ip) ?></td><td><?= $c ?></td><td><div class="bar-orange" style="width:<?= round($c / $maxIp * 200) ?>px"></div></td></tr>
  <?php endforeach; ?>
</table>

<?php else: ?>
<p>No hay datos de errores/bloqueos para <?= htmlspecialchars($month) ?>.</p>
<?php endif; ?>

<?php endif; ?>
</body>
</html>
