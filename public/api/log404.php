<?php
// Server-side access logger for Camping Zingira
// Captures ALL 403/404 requests including bots that don't execute JS
// Called via ErrorDocument 403/404 in .htaccess

// ===== RATE LIMITING: block IP after 5 errors in the same minute =====
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateDir = __DIR__ . '/../analytics_logs';
if (!is_dir($rateDir)) {
    mkdir($rateDir, 0750, true);
}
$rateFile = $rateDir . '/ratelimit.json';
$currentMinute = gmdate('Y-m-d\TH:i');
$rateLimit = 5;
$isRateLimited = false;

// Read current rate data (with file locking)
$rateData = [];
if (file_exists($rateFile)) {
    $rfp = fopen($rateFile, 'r');
    if ($rfp && flock($rfp, LOCK_SH)) {
        $raw = stream_get_contents($rfp);
        $rateData = json_decode($raw, true) ?: [];
        flock($rfp, LOCK_UN);
    }
    if ($rfp) fclose($rfp);
}

// Clean expired entries (different minute) and check current IP
$rateDataClean = [];
foreach ($rateData as $rip => $entry) {
    if (($entry['min'] ?? '') === $currentMinute) {
        $rateDataClean[$rip] = $entry;
    }
}

// Check and increment
$ipCount = ($rateDataClean[$ip]['count'] ?? 0) + 1;
$rateDataClean[$ip] = ['min' => $currentMinute, 'count' => $ipCount];

if ($ipCount > $rateLimit) {
    $isRateLimited = true;
}

// Write back rate data
$wfp = fopen($rateFile, 'w');
if ($wfp && flock($wfp, LOCK_EX)) {
    fwrite($wfp, json_encode($rateDataClean));
    flock($wfp, LOCK_UN);
}
if ($wfp) fclose($wfp);

// If rate limited: log it and return 429 immediately
if ($isRateLimited) {
    // Anonymize IP for logging
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ipAnon = preg_replace('/\.\d+$/', '.0', $ip);
    } else {
        $parts = explode(':', $ip);
        $ipAnon = implode(':', array_slice($parts, 0, 3)) . '::';
    }

    $logFile = $rateDir . '/errors_' . gmdate('Y-m') . '.csv';
    $isNew = !file_exists($logFile);
    $fp = fopen($logFile, 'a');
    if ($fp && flock($fp, LOCK_EX)) {
        if ($isNew) {
            fputcsv($fp, ['timestamp', 'status', 'ip_anon', 'method', 'url', 'referer', 'user_agent', 'type', 'bot_name']);
        }
        $reqSafe = substr(preg_replace('/[\r\n]/', '', $_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
        fputcsv($fp, [gmdate('Y-m-d\TH:i:s\Z'), 429, $ipAnon, $_SERVER['REQUEST_METHOD'] ?? 'GET', $reqSafe, '', '', 'ratelimit', '']);
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);

    http_response_code(429);
    header('Retry-After: 60');
    echo '<!DOCTYPE html><html><head><title>429</title></head><body><h1>429 - Too Many Requests</h1></body></html>';
    exit;
}

// Determine the HTTP status (403 = blocked, 404 = not found)
$status = (int)($_SERVER['REDIRECT_STATUS'] ?? 404);
if (!in_array($status, [403, 404], true)) {
    $status = 404;
}

// Known bot patterns (user-agent substrings)
$botPatterns = [
    'bot', 'crawl', 'spider', 'slurp', 'wget', 'curl', 'python', 'scrapy',
    'httpclient', 'java/', 'go-http', 'ruby', 'perl', 'libwww', 'lwp-',
    'scan', 'nikto', 'nmap', 'masscan', 'sqlmap', 'dirbust', 'gobuster',
    'nessus', 'openvas', 'ahrefs', 'semrush', 'mj12bot', 'dotbot',
    'bytespider', 'gptbot', 'claudebot', 'ccbot', 'anthropic',
    'zgrab', 'censys', 'shodan', 'nuclei', 'httpx', 'fuzz',
    'wp-cron', 'wordpress', 'joomla', 'drupal',
    'headlesschrome', 'phantomjs', 'selenium',
];

// Suspicious path patterns (common attack probes)
$suspiciousPaths = [
    '/wp-', '/wordpress', '/wp-admin', '/wp-login', '/wp-content', '/wp-includes',
    '/admin', '/administrator', '/phpmyadmin', '/pma',
    '/.env', '/.git', '/.svn', '/.htaccess', '/.htpasswd',
    '/config', '/backup', '/db', '/database', '/dump',
    '/shell', '/cmd', '/exec', '/eval',
    '/xmlrpc', '/api/jsonws', '/solr', '/actuator',
    '/vendor/', '/node_modules/',
    '.sql', '.bak', '.old', '.zip', '.tar', '.gz',
    '.asp', '.aspx', '.jsp', '.cgi',
];

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$uaLower = strtolower($ua);
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Detect bot
$isBot = false;
$botName = '';
foreach ($botPatterns as $pattern) {
    if (stripos($uaLower, $pattern) !== false) {
        $isBot = true;
        $botName = $pattern;
        break;
    }
}

// Empty user agent = likely bot/scanner
if (empty(trim($ua))) {
    $isBot = true;
    $botName = 'empty-ua';
}

// Detect suspicious path
$isSuspicious = false;
$requestLower = strtolower($requestUri);
foreach ($suspiciousPaths as $pattern) {
    if (stripos($requestLower, $pattern) !== false) {
        $isSuspicious = true;
        break;
    }
}

// Anonymize IP: keep only first 3 octets (IPv4) or first 3 groups (IPv6)
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $ipAnon = preg_replace('/\.\d+$/', '.0', $ip);
} else {
    $parts = explode(':', $ip);
    $ipAnon = implode(':', array_slice($parts, 0, 3)) . '::';
}

// Classify the request
$type = 'human';
if ($isBot && $isSuspicious) {
    $type = 'scan';      // Scanner/attack probe
} elseif ($isBot) {
    $type = 'bot';       // Known bot
} elseif ($isSuspicious) {
    $type = 'suspicious'; // Suspicious path from non-bot UA (possibly spoofed)
}

$timestamp = gmdate('Y-m-d\TH:i:s\Z');

// Sanitize for CSV
$requestSafe = substr(preg_replace('/[\r\n]/', '', $requestUri), 0, 500);
$refererSafe = substr(preg_replace('/[\r\n]/', '', $referer), 0, 500);
$uaSafe = substr(preg_replace('/[\r\n]/', '', $ua), 0, 300);

// Log directory
$logDir = __DIR__ . '/../analytics_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}

// One log file per month
$logFile = $logDir . '/errors_' . gmdate('Y-m') . '.csv';
$isNew = !file_exists($logFile);

$fp = fopen($logFile, 'a');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        if ($isNew) {
            fputcsv($fp, ['timestamp', 'status', 'ip_anon', 'method', 'url', 'referer', 'user_agent', 'type', 'bot_name']);
        }
        fputcsv($fp, [$timestamp, $status, $ipAnon, $method, $requestSafe, $refererSafe, $uaSafe, $type, $botName]);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// Serve the appropriate error page
http_response_code($status);
if ($status === 404) {
    $html404 = __DIR__ . '/../404.html';
    if (file_exists($html404)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($html404);
    } else {
        echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 - Not Found</h1></body></html>';
    }
} else {
    // 403 Forbidden - minimal response (don't leak info to attackers)
    echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 - Forbidden</h1></body></html>';
}
