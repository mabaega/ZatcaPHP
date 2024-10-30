<?php

require_once("Helpers/ApiHelper.php");
require_once("Helpers/InvoiceHelper.php");
require_once("Signer/EInvoiceSigner.php");

//function step3SendSampleDocuments() {
    echo "\nStep 3: Sending Sample Documents\n";

    $certInfo = ApiHelper::loadJsonFromFile("certificate/certificateInfo.json");

    // Menyiapkan proses penandatanganan dan mengambil RequestApi dari xmlFile
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

    // Kode lainnya

foreach ($documentTypes as $docType) {
    list($prefix, $typeCode, $description, $instructionNote) = $docType;
    $icv++;
    $isSimplified = strpos($prefix, "SIM") === 0;

    echo "Processing {$description}...\n";

    $newDoc = InvoiceHelper::ModifyXml($baseDocument, "{$prefix}-0001", $isSimplified ? "0200000" : "0100000", $typeCode, $icv, $pih, $instructionNote);

    $jsonPayload = EInvoiceSigner::GetRequestApi($newDoc, $x509CertificateContent, $privateKey, true);

    $response = ApiHelper::complianceChecks($certInfo, $jsonPayload);
    $requestType = "Compliance Checks"; 
    $apiUrl = $certInfo["complianceChecksUrl"]; 

    $cleanResponse = ApiHelper::cleanUpJson($response, $requestType, $apiUrl);

    $jsonDecodedResponse = json_decode($response, true);

    if ($jsonDecodedResponse) {
        echo "\n\ncomplianceChecks Server Response: \n" . $cleanResponse;
    } else {
        echo "\n\nInvalid JSON Response: \n" . $response;
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
       usleep(200 * 1000);  // 200 ms delay
}

    //return true;
//}

?>
