
<?php
// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// OAuth credentials
$refresh_token = '1000.aa598619b2e247577eff3dd50f4a0c67.eab00e87cd2d9d042511a70ea5e604cd';
$client_id = '1000.HURZ86KGVR7DUYRSEP698XGKX0KSOD';
$client_secret = '86b80e63b847d03e395c4c80df6a510dbe8589b2d9';

// Function to refresh access token
function refreshAccessToken($refresh_token, $client_id, $client_secret) {
    $tokenUrl = 'https://accounts.zoho.com/oauth/v2/token';
    
    $tokenData = [
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $tokenUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($tokenData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => 'Token refresh cURL error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'Token refresh failed with HTTP code: ' . $httpCode];
    }
    
    $tokenResponse = json_decode($response, true);
    
    if (isset($tokenResponse['access_token'])) {
        return ['success' => true, 'access_token' => $tokenResponse['access_token']];
    } else {
        return ['success' => false, 'error' => 'No access token in refresh response', 'response' => $tokenResponse];
    }
}

// Function to make API request to Bigin
function makeBiginRequest($accessToken, $biginData) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.zohoapis.com/crm/v2/Contacts',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($biginData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Zoho-oauthtoken ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'curlError' => $curlError
    ];
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['firstName', 'lastName', 'email'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

// Prepare data for Bigin API
$biginData = [
    'data' => [[
        'First_Name' => $input['firstName'],
        'Last_Name' => $input['lastName'],
        'Email' => $input['email'],
        'Phone' => $input['phone'] ?? '',
        'Description' => "Service Interest: " . ($input['serviceInterest'] ?? '') . "\n\nMessage: " . ($input['message'] ?? ''),
        'Lead_Source' => 'Website Form'
    ]]
];

// Initial access token
$accessToken = '1000.3e14242f25c6024ad378bf849e90a0a0.a63eabc2f4a52722bfae9d0d54421567';

// Make the first API request
$result = makeBiginRequest($accessToken, $biginData);

// If we get a 401 error, try to refresh the token and retry
if ($result['httpCode'] === 401) {
    $tokenRefresh = refreshAccessToken($refresh_token, $client_id, $client_secret);
    
    if ($tokenRefresh['success']) {
        // Retry the request with the new token
        $result = makeBiginRequest($tokenRefresh['access_token'], $biginData);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Token refresh failed: ' . $tokenRefresh['error']
        ]);
        exit();
    }
}

// Handle cURL errors
if ($result['curlError']) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'cURL error: ' . $result['curlError']
    ]);
    exit();
}

// Parse response
$responseData = json_decode($result['response'], true);

// Check if the request was successful
if ($result['httpCode'] === 200 || $result['httpCode'] === 201) {
    if (isset($responseData['data']) && isset($responseData['data'][0]['code']) && $responseData['data'][0]['code'] === 'SUCCESS') {
        echo json_encode([
            'success' => true,
            'data' => $responseData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'API response indicates failure',
            'response' => $responseData
        ]);
    }
} else {
    http_response_code($result['httpCode']);
    echo json_encode([
        'success' => false,
        'error' => 'HTTP error: ' . $result['httpCode'],
        'response' => $responseData
    ]);
}
?>
