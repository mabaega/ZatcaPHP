<?php 
require_once("Helpers/CsrGenerator.php");
require_once("Helpers/ApiHelper.php");
require_once("Helpers/InvoiceHelper.php");
require_once("Signer/EInvoiceSigner.php");

echo "\n\nPHP DEVICE ONBOARDING\n\n";

// Set EnvironmentType to CertificateInfo JSON File 

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

$OTP = '123456'; // for Simulation and Production Get OTP from fatoora Portal 

$apipath = 'developer-portal';  // Default value

switch ($environmentType) {
    case 'NonProduction':
        $apipath = 'developer-portal';
        break;
    case 'Simulation':
        $apipath = 'simulation';
        break;
    case 'Production':  
        $apipath = 'production';
        break;
}

// Prepare certificate information
$certInfo = [
    "environmentType" => $environmentType,  
    "csr" => "",
    "privateKey" => "",
    "OTP" => $OTP,
    "ccsid_requestID" => "",
    "ccsid_binarySecurityToken" => "",
    "ccsid_secret" => "",
    "pcsid_requestID" => "",
    "pcsid_binarySecurityToken" => "",
    "pcsid_secret" => "",
    "lastICV" => "0",
    "lastInvoiceHash" => "NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==",
    "complianceCsidUrl" => "https://gw-fatoora.zatca.gov.sa/e-invoicing/" . $apipath . "/compliance",
    "complianceChecksUrl" => "https://gw-fatoora.zatca.gov.sa/e-invoicing/" . $apipath . "/compliance/invoices",
    "productionCsidUrl" => "https://gw-fatoora.zatca.gov.sa/e-invoicing/" . $apipath . "/production/csids",
    "reportingUrl" => "https://gw-fatoora.zatca.gov.sa/e-invoicing/" . $apipath . "/invoices/reporting/single",
    "clearanceUrl" => "https://gw-fatoora.zatca.gov.sa/e-invoicing/" . $apipath . "/invoices/clearance/single",
];

echo "\nStep 1. Generate CSR and PrivateKey\n";
//$certInfo = CsrGenerator::GenerateCsrAndPrivateKey($certInfo);
$csrGen = new CsrGenerator($config, $environmentType);
list($privateKeyContent, $csrBase64) = $csrGen->generateCsr();
$certInfo["privateKey"] = $privateKeyContent;
$certInfo["csr"] = $csrBase64;

echo "\nPrivate Key (without header and footer):\n";
echo $privateKeyContent . "\n";
echo "\nBase64 Encoded CSR:\n";
echo $csrBase64 . "\n";

ApiHelper::saveJsonToFile("certificate/certificateInfo.json", $certInfo);

echo "\n\nStep 2. Get Compliance CSID";
$response = ApiHelper::complianceCSID($certInfo);
$requestType = "Compliance CSID"; 
$apiUrl = $certInfo["complianceCsidUrl"]; 

$cleanResponse = ApiHelper::cleanUpJson($response, $requestType, $apiUrl);

if ($jsonDecodedResponse = json_decode($response, true)) {
    
    $certInfo["ccsid_requestID"] = $jsonDecodedResponse["requestID"];
    $certInfo["ccsid_binarySecurityToken"] = $jsonDecodedResponse["binarySecurityToken"];
    $certInfo["ccsid_secret"] = $jsonDecodedResponse["secret"];

    ApiHelper::saveJsonToFile("certificate/certificateInfo.json", $certInfo);

    echo "\nCompliance CSID Server Response: \n" . $cleanResponse;
    
} else {
    echo "\nCompliance CSID Server Response: \n" . $cleanResponse;
}

// 3. Send Sample Documents

echo "\n\nStep 3: Sending Sample Documents\n";

$certInfo = ApiHelper::loadJsonFromFile("certificate/certificateInfo.json");

$xmlTemplatePath = "Resources/Invoice.xml";

$privateKey = $certInfo["privateKey"];
$x509CertificateContent = base64_decode($certInfo["ccsid_binarySecurityToken"]);

$baseDocument = new DOMDocument();
$baseDocument->preserveWhiteSpace = true;
$baseDocument->load($xmlTemplatePath);

$documentTypes = [
    ["STDSI", "388", "Standard Invoice",""],
    ["STDCN", "383", "Standard CreditNote","InstructionNotes for Standard CreditNote"],
    ["STDDN", "381", "Standard DebitNote" , "InstructionNotes for Standard DebitNote"],
    ["SIMSI", "388", "Simplified Invoice",""],
    ["SIMCN", "383", "Simplified CreditNote", "InstructionNotes for Simplified CreditNote"],
    ["SIMDN", "381", "Simplified DebitNote", "InstructionNotes for Simplified DebitNote"]
];

$icv = 0;
$pih = "NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==";

foreach ($documentTypes as $docType) {
    list($prefix, $typeCode, $description, $instructionNote) = $docType;
    $icv++;
    $isSimplified = strpos($prefix, "SIM") === 0;

    echo "{$icv} - Processing {$description}...\n";

    $newDoc = InvoiceHelper::ModifyXml($baseDocument, "{$prefix}-0001", $isSimplified ? "0200000" : "0100000", $typeCode, $icv, $pih, $instructionNote);

    $jsonPayload = EInvoiceSigner::GetRequestApi($newDoc, $x509CertificateContent, $privateKey, true);

    $response = ApiHelper::complianceChecks($certInfo, $jsonPayload);
    $requestType = "Compliance Checks"; 
    $apiUrl = $certInfo["complianceChecksUrl"]; 

    $cleanResponse = ApiHelper::cleanUpJson($response, $requestType, $apiUrl);

    $jsonDecodedResponse = json_decode($response, true);

    if ($jsonDecodedResponse) {
        echo "\ncomplianceChecks Server Response: \n" . $cleanResponse;
    } else {
        echo "\nInvalid JSON Response: \n" . $response;
        return false;
    }

    if ($response === null) {
        echo "Failed to process {$description}: serverResult is null.\n";
        return false;
    }

    $status = $isSimplified ? $jsonDecodedResponse["reportingStatus"] : $jsonDecodedResponse["clearanceStatus"];

    if (strpos($status, "REPORTED") !== false || strpos($status, "CLEARED") !== false) {
        $jsonPayload = json_decode($jsonPayload, true);
        $pih = $jsonPayload["invoiceHash"];
        echo "\n{$description} processed successfully\n\n";
    } else {
        echo "Failed to process {$description}: status is {$status}\n";
        return false;
    }
    //usleep(200 * 1000);  // 200 ms delay
}


echo "\n\nStep 4. Get Production CSID";

$response = ApiHelper::productionCSID($certInfo);
$requestType = "Production CSID"; 
$apiUrl = $certInfo["productionCsidUrl"]; 

$cleanResponse = ApiHelper::cleanUpJson($response, $requestType, $apiUrl);

if ($jsonDecodedResponse = json_decode($response, true)) {
    
    $certInfo["pcsid_requestID"] = $jsonDecodedResponse["requestID"];
    $certInfo["pcsid_binarySecurityToken"] = $jsonDecodedResponse["binarySecurityToken"];
    $certInfo["pcsid_secret"] = $jsonDecodedResponse["secret"];

    ApiHelper::saveJsonToFile("certificate/certificateInfo.json", $certInfo);

    echo "\n\nPproduction CSID Server Response: \n" . $cleanResponse;
    
} else {
    echo "\n\nProduction CSID Server Response: \n" . $cleanResponse;
}

?>