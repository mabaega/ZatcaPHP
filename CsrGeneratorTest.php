<?php
require("Helpers/CsrGenerator.php");

$config = [
    "csr.common.name" => "TST-886431145-399999999900003",
    "csr.serial.number" => "1-TST|2-TST|3-ed22f1d8-e6a2-1118-9b58-d9a8f11e445f",
    "csr.organization.identifier" => "399999999900003",
    "csr.organization.unit.name" => "Riyadh Branch",
    "csr.organization.name" => "Maximum Speed Tech Supply LTD",
    "csr.country.name" => "SA",
    "csr.invoice.type" => "1100",
    "csr.location.address" => "RRRD2929",
    "csr.industry.business.category" => "Supply activities"
];

$environmentType = "NonProduction";

// Instantiate CSR Generator
$csrGen = new CsrGenerator($config, $environmentType);
list($privateKeyContent, $csrBase64) = $csrGen->generateCsr();

echo "Private Key (without header and footer):\n";
echo $privateKeyContent . "\n\n";
echo "Base64 Encoded CSR:\n";
echo $csrBase64 . "\n\n";

// Define ZATCA endpoint and OTP
$otp = '123456';
$url = "https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance";

// Prepare JSON payload
$jsonPayload = json_encode([
    'csr' => $csrBase64
]);

// Set cURL options
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'accept-language: en',
    'OTP: ' . $otp,
    'Accept-Version: V2',
    'Content-Type: application/json'
]);

// Execute cURL and get the response
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Output server response
if ($response === false) {
    echo "cURL error: " . curl_error($ch);
} elseif ($httpCode !== 200) {
    echo "Error: Received HTTP code " . $httpCode . "\n";
} else {
    echo "\n\nServer Response: \n" . json_encode(json_decode($response), JSON_PRETTY_PRINT);
}

?>