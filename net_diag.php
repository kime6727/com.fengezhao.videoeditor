<?php
// Compare Traefik configs between maskedit and htmleditorcloud
header('Content-Type: application/json; charset=utf-8');

$traefikBase = 'http://dokploy-traefik:8080/api';

function traefikGet($path) {
    global $traefikBase;
    $ch = curl_init("$traefikBase$path");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true) ?: [];
}

$routers = traefikGet('/http/routers');
$services = traefikGet('/http/services');

// Get full details of htmleditorcloud routers and services
$htmleditorRouters = [];
$htmleditorServices = [];
foreach ($routers as $r) {
    if (stripos($r['name'] ?? '', 'htmleditor') !== false) $htmleditorRouters[] = $r;
}
foreach ($services as $s) {
    if (stripos($s['name'] ?? '', 'htmleditor') !== false) $htmleditorServices[] = $s;
}

// Get maskedit for comparison
$maskeditRouters = [];
$maskeditServices = [];
foreach ($routers as $r) {
    if (stripos($r['name'] ?? '', 'maskedit') !== false) $maskeditRouters[] = $r;
}
foreach ($services as $s) {
    if (stripos($s['name'] ?? '', 'maskedit') !== false) $maskeditServices[] = $s;
}

// Test actual connectivity from this container to maskedit service IP
$maskeditIp = $maskeditServices[0]['loadBalancer']['servers'][0]['url'] ?? 'unknown';
$connTest = [];
if (preg_match('/http:\/\/([\d.]+):(\d+)/', $maskeditIp, $m)) {
    $ip = $m[1];
    $port = (int)$m[2];
    $conn = @fsockopen($ip, $port, $errno, $errstr, 3);
    $connTest['ip'] = $ip;
    $connTest['port'] = $port;
    $connTest['reachable'] = $conn ? true : false;
    $connTest['error'] = $conn ? null : "$errno: $errstr";
    if ($conn) fclose($conn);
    
    // Try HTTP request directly
    $ch = curl_init("http://$ip:$port/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $connTest['http_test'] = ['code' => $code, 'body_len' => strlen($resp ?: '')];
}

// Check this container's own IP
$hostname = gethostname();
$myIps = [];
$ifconfig = @shell_exec('hostname -i 2>/dev/null');
$myIps = explode(' ', trim($ifconfig ?: ''));

echo json_encode([
    'my_hostname' => $hostname,
    'my_ips' => $myIps,
    'maskedit_service_target' => $maskeditIp,
    'connectivity_test' => $connTest,
    'htmleditor_routers' => $htmleditorRouters,
    'htmleditor_services' => $htmleditorServices,
    'maskedit_routers' => $maskeditRouters,
    'maskedit_services' => $maskeditServices,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
