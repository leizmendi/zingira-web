<?php
// Lightweight analytics endpoint for Camping Zingira
// Logs page views to a protected CSV file

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// CORS - only accept from own domain
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://zingiracamping.com', 'https://www.zingiracamping.com', 'http://localhost:4321'];
if (!in_array($origin, $allowed, true)) {
    http_response_code(403);
    exit;
}
header("Access-Control-Allow-Origin: $origin");

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['p'])) {
    http_response_code(400);
    exit;
}

// Sanitize input
$page = substr(preg_replace('/[^a-zA-Z0-9\/\-_#?=&.]/', '', $input['p']), 0, 500);
$ref  = substr(preg_replace('/[^a-zA-Z0-9\/\-_.:?=&]/', '', $input['r'] ?? ''), 0, 500);
$lang = substr(preg_replace('/[^a-z]/', '', $input['l'] ?? ''), 0, 5);

// Anonymize IP: keep only first 3 octets (IPv4) or first 3 groups (IPv6)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $ip = preg_replace('/\.\d+$/', '.0', $ip);
} else {
    $parts = explode(':', $ip);
    $ip = implode(':', array_slice($parts, 0, 3)) . '::';
}

$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
$timestamp = gmdate('Y-m-d\TH:i:s\Z');

// Log directory (one level above public_html for extra protection)
$logDir = __DIR__ . '/../analytics_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}

// One log file per month
$logFile = $logDir . '/hits_' . gmdate('Y-m') . '.csv';
$isNew = !file_exists($logFile);

$fp = fopen($logFile, 'a');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        if ($isNew) {
            fputcsv($fp, ['timestamp', 'ip_anon', 'page', 'referrer', 'lang', 'user_agent']);
        }
        fputcsv($fp, [$timestamp, $ip, $page, $ref, $lang, $ua]);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

http_response_code(204);
