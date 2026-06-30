<?php
header('Content-Type: application/json');

// Get data from the user
$data = json_decode(file_get_contents('php://input'), true);

$provider = $data['provider'] ?? 'hostinger';
$domain = $data['domain'] ?? '';
$username = $data['username'] ?? '';
$api_token = $data['api_token'] ?? '';
$subdomain = $data['subdomain'] ?? '';

// Validation
if (empty($domain) || empty($username) || empty($api_token) || empty($subdomain)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields (domain, username, token, subdomain).']);
    exit;
}

// --- Route to the appropriate provider ---
if ($provider === 'hostinger') {
    createHostingerSubdomain($domain, $username, $api_token, $subdomain);
} elseif ($provider === 'godaddy') {
    createGoDaddySubdomain($domain, $username, $api_token, $subdomain);
} else {
    echo json_encode(['success' => false, 'message' => 'Unsupported provider.']);
}

function createHostingerSubdomain($domain, $username, $api_token, $subdomain) {
    // Step 1: Get website ID
    $url_websites = "https://api.hostinger.com/api/hosting/v1/accounts/{$username}/websites";
    $ch = curl_init($url_websites);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_token]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(['success' => false, 'message' => 'Invalid API token or username. (HTTP '.$http_code.')']);
        return;
    }

    $websites = json_decode($response, true);
    $website_id = null;
    if (isset($websites['data']) && is_array($websites['data'])) {
        foreach ($websites['data'] as $site) {
            if ($site['domain'] === $domain) {
                $website_id = $site['id'];
                break;
            }
        }
    }

    if (!$website_id) {
        echo json_encode(['success' => false, 'message' => 'Domain not found in your Hostinger account.']);
        return;
    }

    // Step 2: Create subdomain
    $url_create = "https://api.hostinger.com/api/hosting/v1/websites/{$website_id}/subdomains";
    $payload = ['subdomain' => $subdomain];

    $ch = curl_init($url_create);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 201) {
        echo json_encode(['success' => true, 'message' => "✅ {$subdomain}.{$domain} created successfully!"]);
    } else {
        $error = json_decode($response, true);
        $msg = $error['error'] ?? $error['message'] ?? 'Unknown Hostinger API error.';
        echo json_encode(['success' => false, 'message' => "❌ $msg"]);
    }
}

function createGoDaddySubdomain($domain, $api_key, $api_secret, $subdomain) {
    // GoDaddy API: Add DNS A record for the subdomain
    // Note: The user's server IP is needed. We'll use the server's own IP as default.
    $server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

    $url = "https://api.godaddy.com/v1/domains/{$domain}/records/A/{$subdomain}";
    
    $payload = [
        ['data' => $server_ip, 'ttl' => 600]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: sso-key ' . $api_key . ':' . $api_secret,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 201) {
        echo json_encode(['success' => true, 'message' => "✅ {$subdomain}.{$domain} created successfully!"]);
    } else {
        $error = json_decode($response, true);
        $msg = $error['message'] ?? 'Unknown GoDaddy API error.';
        echo json_encode(['success' => false, 'message' => "❌ $msg"]);
    }
}
?>
