<?php
header('Content-Type: application/json');

// যেকোনো পাবলিক ব্যবহারকারীর কাছ থেকে ডেটা নিন
$data = json_decode(file_get_contents('php://input'), true);

$domain = $data['domain'] ?? '';
$username = $data['username'] ?? '';
$api_token = $data['api_token'] ?? '';
$subdomain = $data['subdomain'] ?? '';

// ভ্যালিডেশন
if (empty($domain) || empty($username) || empty($api_token) || empty($subdomain)) {
    echo json_encode(['success' => false, 'message' => 'সব তথ্য পূরণ করুন (ডোমেইন, ইউজার, টোকেন, সাবডোমেইন)']);
    exit;
}

// --- ধাপ ১: ওয়েবসাইট আইডি বের করা (ডোমেইন দিয়ে) ---
$url_websites = "https://api.hostinger.com/api/hosting/v1/accounts/{$username}/websites";
$ch = curl_init($url_websites);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_token]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode(['success' => false, 'message' => 'API টোকেন বা ইউজারনেম ভুল। (HTTP '.$http_code.')']);
    exit;
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
    echo json_encode(['success' => false, 'message' => 'আপনার অ্যাকাউন্টে এই ডোমেইনটি খুঁজে পাওয়া যায়নি।']);
    exit;
}

// --- ধাপ ২: সাবডোমেইন তৈরি করা ---
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

// রেসপন্স চেক
if ($http_code === 200 || $http_code === 201) {
    echo json_encode(['success' => true, 'message' => "✅ {$subdomain}.{$domain} সফলভাবে তৈরি হয়েছে!"]);
} else {
    $error = json_decode($response, true);
    $msg = $error['error'] ?? $error['message'] ?? 'Hostinger API থেকে অজানা ত্রুটি';
    echo json_encode(['success' => false, 'message' => "❌ $msg"]);
}
?>
