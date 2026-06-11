<?php
// Network diagnostic for Traefik routing fix
header('Content-Type: application/json; charset=utf-8');

$result = [];

// Container info
$result['hostname'] = gethostname();
$result['php_version'] = PHP_VERSION;

// Network interfaces
$result['ip_addresses'] = [];
$ifconfig = @shell_exec('ip addr 2>/dev/null || ifconfig 2>/dev/null');
if ($ifconfig) {
    preg_match_all('/inet\s+(\d+\.\d+\.\d+\.\d+)/', $ifconfig, $matches);
    $result['ip_addresses'] = $matches[1] ?? [];
}

// /etc/hosts
$result['hosts_file'] = @file_get_contents('/etc/hosts');

// Environment variables (Dokploy-related)
$envVars = [];
foreach ($_ENV as $key => $val) {
    if (stripos($key, 'DOKPLOY') !== false || stripos($key, 'TRAEFIK') !== false || 
        stripos($key, 'DOCKER') !== false || stripos($key, 'NETWORK') !== false ||
        stripos($key, 'HOSTNAME') !== false || stripos($key, 'COMPOSE') !== false) {
        $envVars[$key] = $val;
    }
}
$result['relevant_env'] = $envVars;

// Try to list Docker networks from inside container
$result['docker_networks'] = @shell_exec('cat /proc/net/if_inet6 2>/dev/null; echo "---"; ls /var/run/docker.sock 2>/dev/null; echo "---"; cat /etc/resolv.conf 2>/dev/null');

// Check DNS resolution for common Dokploy service names
$dnsTests = ['traefik', 'dokploy-traefik', 'dokploy', 'gateway'];
$result['dns_resolution'] = [];
foreach ($dnsTests as $host) {
    $ip = @gethostbyname($host);
    $result['dns_resolution'][$host] = $ip !== $host ? $ip : 'not_resolved';
}

// Try to reach Traefik API (usually on port 8080 or 8443 internally)
$result['traefik_ping'] = [];
foreach (['http://traefik:8080/api/version', 'http://dokploy-traefik:8080/api/version'] as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result['traefik_ping'][$url] = ['code' => $code, 'body' => substr($resp ?: '', 0, 200)];
}

// Check what port Dokploy domain is configured on by checking from inside
$ch = curl_init('https://maskedit.weburl.cloudns.be/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$result['self_check_domain'] = ['code' => $code, 'body' => substr($resp ?: '', 0, 300)];

// Check route to localhost:8080 (the exposed port)
$ch = curl_init('http://127.0.0.1:8080/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$result['self_check_localhost'] = ['code' => $code];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
