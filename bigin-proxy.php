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

// Email configuration
$notification_email = 'paul@strataconsultingengineers.com'; // Change this to your email
$from_email = 'noreply@strataconsultingengineers.com'; // Change this to your domain email

// Function to log debug information to a separate file
function logDebug($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    $logMessage .= "\n";
    
    // Write to debug log file
    $logFile = __DIR__ . '/bigin-debug.log';
    
    // Ensure the log file is writable and limit its size
    if (file_exists($logFile) && filesize($logFile) > 5242880) { // 5MB limit
        // Truncate if too large
        file_put_contents($logFile, '');
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Function to refresh access token
function refreshAccessToken($refresh_token, $client_id, $client_secret) {
    logDebug("Starting token refresh process");
    
    $tokenUrl = 'https://accounts.zoho.com/oauth/v2/token';
    
    $tokenData = [
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token'
    ];
    
    logDebug("Token refresh request data prepared", $tokenData);
    
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
    
    logDebug("Making token refresh request to Zoho");
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    logDebug("Token refresh response received", [
        'httpCode' => $httpCode,
        'curlError' => $curlError,
        'response' => $response
    ]);
    
    if ($curlError) {
        logDebug("Token refresh cURL error detected", ['error' => $curlError]);
        return ['success' => false, 'error' => 'Token refresh cURL error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        logDebug("Token refresh failed with non-200 status", ['httpCode' => $httpCode]);
        return ['success' => false, 'error' => 'Token refresh failed with HTTP code: ' . $httpCode];
    }
    
    $tokenResponse = json_decode($response, true);
    
    logDebug("Token refresh response parsed", $tokenResponse);
    
    if (isset($tokenResponse['access_token'])) {
        logDebug("New access token received successfully", ['token_length' => strlen($tokenResponse['access_token'])]);
        return ['success' => true, 'access_token' => $tokenResponse['access_token']];
    } else {
        logDebug("No access token in refresh response", $tokenResponse);
        return ['success' => false, 'error' => 'No access token in refresh response', 'response' => $tokenResponse];
    }
}

// Function to make API request to Bigin
function makeBiginRequest($accessToken, $biginData) {
    logDebug("Making Bigin API request", ['token_length' => strlen($accessToken)]);
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.zohoapis.com/bigin/v2/Contacts',
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
    
    logDebug("Bigin API response received", [
        'httpCode' => $httpCode,
        'curlError' => $curlError,
        'response_length' => strlen($response)
    ]);
    
    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'curlError' => $curlError
    ];
}

// Function to send email notification
function sendEmailNotification($contactData, $to_email, $from_email) {
    logDebug("Preparing to send email notification", ['to' => $to_email]);
    
    $subject = "New Contact Created - " . $contactData['First_Name'] . " " . $contactData['Last_Name'];
    
    $message = "A new contact has been successfully created in Bigin CRM:\n\n";
    $message .= "Name: " . $contactData['First_Name'] . " " . $contactData['Last_Name'] . "\n";
    $message .= "Email: " . $contactData['Email'] . "\n";
    $message .= "Phone: " . ($contactData['Phone'] ?: 'Not provided') . "\n";
    $message .= "Lead Source: " . ($contactData['Lead_Source'] ?: 'Not specified') . "\n";
    $message .= "Description: " . ($contactData['Description'] ?: 'None') . "\n\n";
    $message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    
    $headers = "From: $from_email\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    logDebug("Sending email notification", [
        'subject' => $subject,
        'to' => $to_email,
        'from' => $from_email
    ]);
    
    $emailSent = mail($to_email, $subject, $message, $headers);
    
    if ($emailSent) {
        logDebug("Email notification sent successfully");
        return true;
    } else {
        logDebug("Failed to send email notification");
        return false;
    }
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

logDebug("Processing form submission", $input);

// Validate required fields
$requiredFields = ['firstName', 'lastName', 'email'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        logDebug("Missing required field", ['field' => $field]);
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

logDebug("Bigin data prepared", $biginData);

// Initial access token - UPDATED WITH FRESH TOKEN
$accessToken = '1000.082035dad633b6f86a4e8f5ed1f4e239.069626046138fdc8f294e477b98e42b8';

logDebug("Making initial API request with stored token");

// Make the first API request
$result = makeBiginRequest($accessToken, $biginData);

// If we get a 401 error, try to refresh the token and retry
if ($result['httpCode'] === 401) {
    logDebug("401 error detected - starting token refresh process");
    
    $tokenRefresh = refreshAccessToken($refresh_token, $client_id, $client_secret);
    
    if ($tokenRefresh['success']) {
        logDebug("Token refresh successful - retrying original request");
        
        // Retry the request with the new token
        $result = makeBiginRequest($tokenRefresh['access_token'], $biginData);
        
        logDebug("Retry request completed", ['httpCode' => $result['httpCode']]);
    } else {
        logDebug("Token refresh failed", $tokenRefresh);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Token refresh failed: ' . $tokenRefresh['error']
        ]);
        exit();
    }
} else {
    logDebug("Initial request completed without 401", ['httpCode' => $result['httpCode']]);
}

// Handle cURL errors
if ($result['curlError']) {
    logDebug("cURL error in final result", ['error' => $result['curlError']]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'cURL error: ' . $result['curlError']
    ]);
    exit();
}

// Parse response
$responseData = json_decode($result['response'], true);

logDebug("Final response data", $responseData);

// Check if the request was successful
if ($result['httpCode'] === 200 || $result['httpCode'] === 201) {
    if (isset($responseData['data']) && isset($responseData['data'][0]['code']) && $responseData['data'][0]['code'] === 'SUCCESS') {
        logDebug("Request completed successfully");
        
        // Send email notification on successful contact creation
        $contactData = $biginData['data'][0];
        $emailSent = sendEmailNotification($contactData, $notification_email, $from_email);
        
        echo json_encode([
            'success' => true,
            'data' => $responseData,
            'email_notification_sent' => $emailSent
        ]);
    } else {
        logDebug("API response indicates failure", $responseData);
        echo json_encode([
            'success' => false,
            'error' => 'API response indicates failure',
            'response' => $responseData
        ]);
    }
} else {
    logDebug("Request failed with HTTP error", ['httpCode' => $result['httpCode']]);
    http_response_code($result['httpCode']);
    echo json_encode([
        'success' => false,
        'error' => 'HTTP error: ' . $result['httpCode'],
        'response' => $responseData
    ]);
}
?>
