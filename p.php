<?php

 // Generate EC private key
 function generateEcPrivateKey($outputFilePath) {
    $cmd = "openssl ecparam -name secp256k1 -genkey -noout -out " . escapeshellarg($outputFilePath);
    exec($cmd, $output, $returnVar);

    if ($returnVar !== 0) {
        die("Error generating EC private key: " . implode("\n", $output));
    }
}

// Generate CSR
function generateCsr($privateKeyFilePath, $configPath, $csrOutputFilePath) {
    $cmd = "openssl req -new -sha256 -key " . escapeshellarg($privateKeyFilePath) . 
        " -config " . escapeshellarg($configPath) . 
        " -out " . escapeshellarg($csrOutputFilePath);

    exec($cmd, $output, $returnVar);
    
    if ($returnVar !== 0) {
        die("Error generating CSR: " . implode("\n", $output));
    }

    // Read the CSR content and encode it in Base64
    $csrContent = file_get_contents($csrOutputFilePath);
    $csrContent = base64_encode($csrContent);

    file_put_contents($csrOutputFilePath, $csrContent);

    return $csrContent;
}

function complianceCSID($csr, $OTP, $url) {

    $jsonPayload = json_encode([
        'csr' => $csr
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json',
        'accept-language: en',
        "OTP: $OTP",
        'Accept-Version: V2',
        'Content-Type: application/json',
    ));
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error: $error_msg");
    }

    curl_close($ch);

    return $response;
}

$config_data = array(
    'emailAddress' => 'test_email@gmail.com',
    'commonName' => 'mydomain.com',
    'country' => 'SA',
    'organizationalUnitName' => 'Demora Branch',
    'organizationName' => 'Test Company',
    'serialNumber' => '1-Model|2-3492842|3-49182743421',
    'vatNumber' => '317460736806263',
    'invoiceType' => '1100',
    'registeredAddress' => 'TestAddress',
    'businessCategory' => 'Software Development'
);

define('CONFIG_CNF_FILE_TEMPLATE', 
"oid_section=OIDS
[ OIDS ]
certificateTemplateName= 1.3.6.1.4.1.311.20.2

[req]
default_bits=2048
emailAddress=__emailAddress
req_extensions=v3_req
x509_extensions=v3_Ca
prompt=no
default_md=sha256
req_extensions=req_ext
distinguished_name=dn

[dn]
CN=__commonName
C=__country
OU=__organizationalUnitName
O=__organizationName

[v3_req]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment

[req_ext]
certificateTemplateName = ASN1:PRINTABLESTRING:PREZATCA-code-Signing
subjectAltName = dirName:alt_names

[alt_names]
SN=__serialNumber
UID=__vatNumber
title=__invoiceType
registeredAddress=__registeredAddress
businessCategory=__businessCategory");


$template = CONFIG_CNF_FILE_TEMPLATE;

foreach ($config_data as $key => $value) {
    $configCnf = str_replace("__$key", $value, $template);
    $template = $configCnf;
}

echo $template;

$configPath = 'config.cnf';
$privateKeyFile = 'PrivateKey.pem';
$csrFile = 'taxpayer.csr';

file_put_contents($configPath, $template);

generateEcPrivateKey($privateKeyFile);
$csr = generateCsr($privateKeyFile, $configPath, $csrFile);

$OTP = "123456";
$url = "https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance";

$response = complianceCSID($csr, $OTP, $url);

if (is_string($response) && is_array(json_decode($response, true)) && (json_last_error() == JSON_ERROR_NONE)) {
    echo "\n\ncomplianceCSID Server Response: \n" . json_encode(json_decode($response), JSON_PRETTY_PRINT);
} else {
    echo "\n\ncomplianceCSID Server Response: \n" . $response;
}

?>