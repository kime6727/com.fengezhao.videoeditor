<?php
// Deep Traefik diagnostic - test from inside the network
header('Content-Type: application/json; charset=utf-8');

$result = [];

// 1. Test HTTPS via domain (goes through Traefik)
$ch = curl_init('https://maskedit.weburl.cloudns.be/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);
$resp = curl_exec($ch);
$result['https_via_domain'] = [
    'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'body' => substr($resp ?: '', 0, 500),
    'error' => curl_error($ch),
    'ssl_verify_result' => curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT),
    'local_port' => curl_getinfo($ch, CURLINFO_LOCAL_PORT),
    'primary_ip' => curl_getinfo($ch, CURLINFO_PRIMARY_IP),
    'primary_port' => curl_getinfo($ch, CURLINFO_PRIMARY_PORT),
];
rewind($verbose);
$result['https_via_domain']['verbose'] = stream_get_contents($verbose);
curl_close($ch);

// 2. Test HTTP via domain (should redirect to HTTPS)
$ch = curl_init('http://maskedit.weburl.cloudns.be/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$resp = curl_exec($ch);
$result['http_via_domain'] = [
    'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'redirect_url' => curl_getinfo($ch, CURLINFO_REDIRECT_URL),
    'body' => substr($resp ?: '', 0, 300),
];
curl_close($ch);

// 3. Test direct IP of Traefik on port 443
$ch = curl_init('https://10.0.1.9/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: maskedit.weburl.cloudns.be']);
$resp = curl_exec($ch);
$result['https_via_traefik_ip'] = [
    'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'body' => substr($resp ?: '', 0, 500),
    'error' => curl_error($ch),
];
curl_close($ch);

// 4. Test Traefik entrypoints
$ch = curl_init('http://dokploy-traefik:8080/api/overview');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$resp = curl_exec($ch);
curl_close($ch);
$overview = json_decode($resp, true);
$result['traefik_entrypoints'] = $overview['entryPoints'] ?? 'not_available';

// 5. Check if htmleditorcloud works (for comparison)
$ch = curl_init('https://html.niceapp.eu.cc/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resp = curl_exec($ch);
$result['htmleditorcloud_test'] = [
    'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'body_len' => strlen($resp ?: ''),
    'error' => curl_error($ch),
];
curl_close($ch);

// 6. Check certificate info
$certOutput = @shell_exec("echo | openssl s_client -connect 10.0.1.9:443 -servername maskedit.weburl.cloudns.be 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName 2>/dev/null");
$result['certificate_info'] = $certOutput ?: 'unable to retrieve';

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
