<?php
// Traefik routing diagnostic
header('Content-Type: application/json; charset=utf-8');

$traefikBase = 'http://dokploy-traefik:8080/api';
$result = [];

// Get all routers
$ch = curl_init("$traefikBase/http/routers");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$routers = curl_exec($ch);
curl_close($ch);

// Get all services
$ch = curl_init("$traefikBase/http/services");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$services = curl_exec($ch);
curl_close($ch);

// Filter routers related to maskedit
$allRouters = json_decode($routers, true) ?: [];
$maskeditRouters = [];
foreach ($allRouters as $router) {
    $name = $router['name'] ?? '';
    $rule = $router['rule'] ?? '';
    if (stripos($name, 'maskedit') !== false || stripos($rule, 'maskedit') !== false) {
        $maskeditRouters[] = $router;
    }
}

// Filter services related to maskedit
$allServices = json_decode($services, true) ?: [];
$maskeditServices = [];
foreach ($allServices as $svc) {
    $name = $svc['name'] ?? '';
    if (stripos($name, 'maskedit') !== false) {
        $maskeditServices[] = $svc;
    }
}

$result['maskedit_routers'] = $maskeditRouters;
$result['maskedit_services'] = $maskeditServices;
$result['total_routers'] = count($allRouters);
$result['total_services'] = count($allServices);

// Also show all router names for context
$result['all_router_names'] = array_map(fn($r) => $r['name'] ?? 'unknown', $allRouters);
$result['all_service_names'] = array_map(fn($s) => $s['name'] ?? 'unknown', $allServices);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
