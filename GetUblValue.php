<?php

echo "SIMPLE UBL VALIDATOR\n\n";

// Load the XML file
//$xmlFile = "C:\zatca-einvoicing-sdk-238-R3.3.6\Data\Samples\Simplified\Invoice\Simplified_Invoice.xml"; // Replace with your XML file path
$xmlFile = "php.xml"; // Replace with your XML file path
$dom = new DOMDocument();
$dom->preserveWhiteSpace = true;

// Suppress warnings and load the XML
libxml_use_internal_errors(true);
$dom->load($xmlFile);
libxml_clear_errors();

// Create a new XPath object
$xpath = new DOMXPath($dom);

// Register the necessary namespaces
$xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
$xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
$xpath->registerNamespace('sig', 'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2');
$xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

// Extract values using XPath
$invoiceHashNode = $xpath->query('//ds:Reference[@Id="invoiceSignedData"]/ds:DigestValue');
$SignedPropertiesHashNode = $xpath->query('//ds:Reference[@Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties"]/ds:DigestValue');
$signatureValueNode = $xpath->query('//ds:SignatureValue');
$x509CertificateContentNode = $xpath->query('//ds:X509Certificate');
$SigningTimeNode = $xpath->query('//xades:SigningTime');
$CertDigestValueNode = $xpath->query('//xades:CertDigest/ds:DigestValue');
$X509IssuerNameNode = $xpath->query('//xades:IssuerSerial/ds:X509IssuerName');
$X509SerialNumberNode = $xpath->query('//xades:IssuerSerial/ds:X509SerialNumber');

// Assign values to variables
$invoiceHash = $invoiceHashNode->length > 0 ? $invoiceHashNode->item(0)->nodeValue : 'Not found';
$SignedPropertiesHash = $SignedPropertiesHashNode->length > 0 ? $SignedPropertiesHashNode->item(0)->nodeValue : 'Not found';
$signatureValue = $signatureValueNode->length > 0 ? $signatureValueNode->item(0)->nodeValue : 'Not found';
$x509CertificateContent = $x509CertificateContentNode->length > 0 ? $x509CertificateContentNode->item(0)->nodeValue : 'Not found';
$SigningTime = $SigningTimeNode->length > 0 ? $SigningTimeNode->item(0)->nodeValue : 'Not found';
$CertDigestValue = $CertDigestValueNode->length > 0 ? $CertDigestValueNode->item(0)->nodeValue : 'Not found';
$X509IssuerName = $X509IssuerNameNode->length > 0 ? $X509IssuerNameNode->item(0)->nodeValue : 'Not found';
$X509SerialNumber = $X509SerialNumberNode->length > 0 ? $X509SerialNumberNode->item(0)->nodeValue : 'Not found';

// Output the results
echo "Result From : \n$xmlFile\n\n";
echo "InvoiceHash: $invoiceHash\n\n";
echo "SignedPropertiesHash: $SignedPropertiesHash\n\n";
echo "SignatureValue: $signatureValue\n\n";
echo "x509CertificateContent: $x509CertificateContent\n\n";
echo "SigningTime: $SigningTime\n\n";
echo "CertDigestValue: $CertDigestValue\n\n";
echo "X509IssuerName: $X509IssuerName\n\n";
echo "X509SerialNumber: $X509SerialNumber\n\n";


echo "\nSIGNATUREVALUE TEST\n";

try {
    $publicKey = getPublicKeyFromCertificate($x509CertificateContent);
    //echo "Public Key:\n" . $publicKey . "\n";

    // Contoh invoice hash dan signature yang diberikan
    $xmlHashing = base64_decode($invoiceHash); // Decode invoice hash
   
    // Verifikasi tanda tangan
    if (verifySignature($xmlHashing, $signatureValue, $publicKey)) {
        echo "Signature Value is VALID.\n\n";
    } else {
        echo "Signature Value is INVALID.\n\n";
    }
} catch (Exception $ex) {
    echo "Error: " . $ex->getMessage() . "\n\n";
}


echo "\nSIGNED PROPERTIES HASH TEST";

    try {
        $certificateBytes = base64_decode($x509CertificateContent);
        
        if ($certificateBytes === false) {
            throw new Exception("Failed to decode the base64-encoded X.509 certificate.");
        }

        $hashBytes = hash('sha256', $x509CertificateContent, true);
        $hashHex = bin2hex($hashBytes);
        $publicKeyHashing = base64_encode($hashHex);

        $parsedCertificate = openssl_x509_read("-----BEGIN CERTIFICATE-----\n" . chunk_split($x509CertificateContent, 64, "\n") . "-----END CERTIFICATE-----\n");

        if ($parsedCertificate === false) {
            throw new Exception("Failed to parse the X.509 certificate.");
        }

        $certInfo = openssl_x509_parse($parsedCertificate);

        $_issuerName = getIssuerName($certInfo);

        $_serialNumber = getSerialNumberForCertificateObject($certInfo);

        $_signedPropertiesHash = getSignedPropertiesHash($SigningTime, $publicKeyHashing, $_issuerName, $_serialNumber);

        echo "\nOutput:";
        echo "\nCertDigest: $publicKeyHashing";
        echo "\n\nIssuer Name: $_issuerName";
        echo "\n\nSerial Number: $_serialNumber\n";

        echo "\n\nSigned Properties Hash: $_signedPropertiesHash\n";

        if($SignedPropertiesHash === $_signedPropertiesHash) 
        {
            echo "SignedPropertiesHash: VALID\n";
        }else{
            echo "SignedPropertiesHash: INVALID\n";
        }

    } catch (Exception $ex) {
        echo "An error occurred: " . $ex->getMessage() . "\n";
    }

    function getIssuerName($certInfo) {
        $issuer = $certInfo['issuer'];
    
        if (isset($issuer['DC']) && is_array($issuer['DC'])) {
            $issuer['DC'] = array_reverse($issuer['DC']);
        }
    
        $issuerNameParts = [];
        if (!empty($issuer['CN'])) {
            $issuerNameParts[] = "CN=" . $issuer['CN'];
        }
    
        if (!empty($issuer['DC']) && is_array($issuer['DC'])) {
            foreach ($issuer['DC'] as $dc) {
                if (!empty($dc)) {
                    $issuerNameParts[] = "DC=" . $dc;
                }
            }
        }
    
        return implode(", ", $issuerNameParts);
    }
    
    function getSerialNumberForCertificateObject($certInfo) {
        $serialNumberHex = $certInfo['serialNumberHex'];
    
        $serialNumberDec = '0';
        $hexLength = strlen($serialNumberHex);
        for ($i = 0; $i < $hexLength; $i++) {
            $hexDigit = hexdec($serialNumberHex[$i]);
            $serialNumberDec = bcmul($serialNumberDec, '16', 0);
            $serialNumberDec = bcadd($serialNumberDec, $hexDigit, 0);
        }
    
        return $serialNumberDec;
    }
    
    function getSignedPropertiesHash($signingTime, $digestValue, $x509IssuerName, $x509SerialNumber) {
        // Construct the XML string with exactly 36 spaces in front of <xades:SignedSignatureProperties>
        $xmlString = '<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">' . "\n" .
                     '                                    <xades:SignedSignatureProperties>' . "\n" .
                     '                                        <xades:SigningTime>' . $signingTime . '</xades:SigningTime>' . "\n" .                 
                     '                                        <xades:SigningCertificate>' . "\n" .
                     '                                            <xades:Cert>' . "\n" .
                     '                                                <xades:CertDigest>' . "\n" .
                     '                                                    <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' . "\n" .
                     '                                                    <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $digestValue . '</ds:DigestValue>' . "\n" .
                     '                                                </xades:CertDigest>' . "\n" .
                     '                                                <xades:IssuerSerial>' . "\n" .
                     '                                                    <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $x509IssuerName . '</ds:X509IssuerName>' . "\n" .
                     '                                                    <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $x509SerialNumber . '</ds:X509SerialNumber>' . "\n" .
                     '                                                </xades:IssuerSerial>' . "\n" .
                     '                                            </xades:Cert>' . "\n" .
                     '                                        </xades:SigningCertificate>' . "\n" .
                     '                                    </xades:SignedSignatureProperties>' . "\n" .
                     '                                </xades:SignedProperties>';
    
        $xmlString = str_replace("\r\n", "\n", $xmlString);
        $xmlString = trim($xmlString);
    
        $hashBytes = hash('sha256', $xmlString, true);
        
        $hashHex = bin2hex($hashBytes);
        
        return base64_encode($hashHex);
    }
    
    function getPublicKeyFromCertificate($certificateContent) {
        // Format sertifikat
        $certificateContent = "-----BEGIN CERTIFICATE-----\n" . 
                              chunk_split($certificateContent, 64, "\n") . 
                              "-----END CERTIFICATE-----\n";
    
        // Membaca sertifikat
        $certificate = openssl_x509_read($certificateContent);
        if ($certificate === false) {
            throw new Exception("Failed to read the X.509 certificate.");
        }
    
        // Mendapatkan kunci publik
        $publicKey = openssl_pkey_get_public($certificate);
        if ($publicKey === false) {
            throw new Exception("Failed to extract public key from the certificate.");
        }
    
        // Mengembalikan kunci publik dalam format PEM
        $publicKeyDetails = openssl_pkey_get_details($publicKey);
        //openssl_free_key($publicKey); // Membersihkan kunci dari memori
        return $publicKeyDetails['key'];
    }
    
    // Fungsi untuk memverifikasi tanda tangan
    function verifySignature($hashToVerify, $signature, $publicKeyContent) {
        // Decode the base64 signature
        $signature = base64_decode($signature);
    
        // Create a new public key from the provided content
        $publicKey = openssl_pkey_get_public($publicKeyContent);
        if ($publicKey === false) {
            throw new Exception("Invalid public key.");
        }
    
        // Verify the signature using the original data hash
        $isValid = openssl_verify($hashToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    
        // Free the key from memory
        //openssl_free_key($publicKey);
    
        return $isValid === 1; // Returns true if the signature is valid
    }

?>