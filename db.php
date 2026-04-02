<?php
require 'vendor/autoload.php';

// Google Sheets Setup
$spreadsheetId = '1VyXfPMVSZnnccq3XcF6zHcbMkWWFDx1duRYYNck2bPw'; // Replace with your ID
$range = 'Sheet1!A:AG'; // Assumes your sheet is named 'Sheet1'

$client = new \Google_Client();
$client->setApplicationName('Tech Requirements Portal');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAuthConfig(__DIR__ . '/credentials.json'); // Path to the JSON key you downloaded

$service = new Google_Service_Sheets($client);

// Helper function to get all data
function getAllMerchants($service, $spreadsheetId, $range) {
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    return $response->getValues() ?: [];
}
?>