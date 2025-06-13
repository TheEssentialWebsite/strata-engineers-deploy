
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

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://www.zohoapis.com/crm/v2/Contacts',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($biginData),
    CURLOPT_HTTPHEADER => [
        'Authorization: Zoho-oauthtoken 1000.3e14242f25c6024ad378bf849e90a0a0.a63eabc2f4a52722bfae9d0d54421567',
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true
]);

// Execute cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// Handle cURL errors
if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'cURL error: ' . $curlError
    ]);
    exit();
}

// Parse response
$responseData = json_decode($response, true);

// Check if the request was successful
if ($httpCode === 200 || $httpCode === 201) {
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
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => 'HTTP error: ' . $httpCode,
        'response' => $responseData
    ]);
}
?>
