<?php
require_once(dirname(__FILE__) ."\QRCodeGenerator.php");

class EInvoiceSigner{

    public static function GetRequestApiFromFile($xmlFilePath, $x509CertificateContent, $privateKeyContent) {
        // 1. Open XML document with preserveWhiteSpace = true
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = true;
        $xml->formatOutput = false;

        // Load XML document from given path
        if (!$xml->load($xmlFilePath)) {
            throw new Exception("Failed to load XML file: $xmlFilePath");
        }

        return Self::GetRequestApi($xml, $x509CertificateContent, $privateKeyContent);
    }

    public static function GetRequestApi($xml, $x509CertificateContent, $privateKeyContent) {
        // Resource files
        $xslFilePath = 'Resources/xslfile.xsl';
        $ublTemplatePath = 'Resources/ZatcaDataUbl.xml'; 
        $signaturePath = 'Resources/ZatcaDataSignature.xml';
        $xmlDeclaration = '<?xml version="1.0" encoding="utf-8"?>';

        // 1a. Get UUID from element <cbc:UUID>
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $uuidNode = $xpath->query('//cbc:UUID')->item(0);
        
        if (!$uuidNode) {
            throw new Exception("UUID not found in the XML document.");
        }

        $uuid = $uuidNode->nodeValue;

        // 1b. Check if it is a simplified invoice
        $isSimplifiedInvoice = false;
        $invoiceTypeCodeNode = $xpath->query('//cbc:InvoiceTypeCode')->item(0);
        if ($invoiceTypeCodeNode) {
            $nameAttribute = $invoiceTypeCodeNode->getAttribute('name');
            $isSimplifiedInvoice = strpos($nameAttribute, '02') === 0;
        }

        // 2. Apply XSL transform
        $xsl = new DOMDocument();
        if (!$xsl->load($xslFilePath)) {
            throw new Exception("Failed to load XSL file: $xslFilePath");
        }

        $proc = new XSLTProcessor();
        $proc->importStylesheet($xsl);

        // Transform document
        $transformedXml = $proc->transformToDoc($xml);
        if (!$transformedXml) {
            throw new Exception("XSL Transformation failed.");
        }
        
        // 3. Canonicalize (C14N) transformed document
        $canonicalXml = $transformedXml->C14N();  // C14N format
        
        //echo $canonicalXml;
        
        // 4. Get byte hash256 from transformed document
        $hash = hash('sha256', $canonicalXml, true);  // result hash SHA-256 in binary data

        // 5. Encode hash to Base64
        $base64Hash = base64_encode($hash);

        // 6. Encode canonicalized XML to Base64
        $base64Invoice = base64_encode($xmlDeclaration . "\n" . $canonicalXml);

        // Return early for non-simplified invoices
        if (!$isSimplifiedInvoice) {
            $result = array(
                "invoiceHash" => $base64Hash,
                "uuid" => $uuid,
                "invoice" => $base64Invoice
            );
            return json_encode($result);
        }

        // 7. Sign the simplified invoice
        return Self::SignSimplifiedInvoice($canonicalXml, $base64Hash, $x509CertificateContent, $privateKeyContent, $ublTemplatePath, $signaturePath, $uuid);
    }

    private static function SignSimplifiedInvoice($canonicalXml, $base64Hash, $x509CertificateContent, $privateKeyContent, $ublTemplatePath, $signaturePath, $uuid) {
        
        $xmlDeclaration = '<?xml version="1.0" encoding="utf-8"?>';

        // Signing Simplified Invoice Document
        $signatureTimestamp = (new DateTime())->format('Y-m-d\TH:i:s');
        //$signatureTimestamp = '2024-10-26T04:15:31';

        // Decode the X.509 certificate
        $certificateBytes = base64_decode($x509CertificateContent);

        // Generate public key hashing
        $hashBytes = hash('sha256', $x509CertificateContent, true);
        $hashHex = bin2hex($hashBytes);
        $publicKeyHashing = base64_encode($hashHex);

        // Parse the X.509 certificate
        $parsedCertificate = openssl_x509_read("-----BEGIN CERTIFICATE-----\n" . 
            chunk_split($x509CertificateContent, 64, "\n") . 
            "-----END CERTIFICATE-----\n");

        // Extract certificate information
        $certInfo = openssl_x509_parse($parsedCertificate);
        $issuerName = Self::getIssuerName($certInfo);
        $serialNumber = Self::getSerialNumberForCertificateObject($certInfo);
        $signedPropertiesHash = Self::getSignedPropertiesHash($signatureTimestamp, $publicKeyHashing, $issuerName, $serialNumber);
        $SignatureValue = Self::getDigitalSignature($base64Hash, $privateKeyContent);

        // Populate UBLExtension Template
        $stringUBLExtension = file_get_contents($ublTemplatePath);
        $stringUBLExtension = str_replace("INVOICE_HASH", $base64Hash, $stringUBLExtension);
        $stringUBLExtension = str_replace("SIGNED_PROPERTIES", $signedPropertiesHash, $stringUBLExtension);
        $stringUBLExtension = str_replace("SIGNATURE_VALUE", $SignatureValue, $stringUBLExtension);
        $stringUBLExtension = str_replace("CERTIFICATE_CONTENT", $x509CertificateContent, $stringUBLExtension);
        $stringUBLExtension = str_replace("SIGNATURE_TIMESTAMP", $signatureTimestamp, $stringUBLExtension);
        $stringUBLExtension = str_replace("PUBLICKEY_HASHING", $publicKeyHashing, $stringUBLExtension);
        $stringUBLExtension = str_replace("ISSUER_NAME", $issuerName, $stringUBLExtension);
        $stringUBLExtension = str_replace("SERIAL_NUMBER", $serialNumber, $stringUBLExtension);

        // Insert UBL into XML
        $insertPosition = strpos($canonicalXml, '>') + 1; // Find position after the first '>'
        $updatedXmlString = substr_replace($canonicalXml, $stringUBLExtension, $insertPosition, 0);

        // Generate QR Code
        $qrCode = QRCodeGenerator::generateQRCode($canonicalXml, $base64Hash, $SignatureValue, $x509CertificateContent);
        
        // Load signature template content
        $stringSignature = file_get_contents($signaturePath);
        $stringSignature = str_replace("BASE64_QRCODE", $qrCode, $stringSignature);

        // Insert signature string before <cac:AccountingSupplierParty>
        $insertPositionSignature = strpos($updatedXmlString, '<cac:AccountingSupplierParty>'); // Find position of the opening tag
        if ($insertPositionSignature !== false) {
            $updatedXmlString = substr_replace($updatedXmlString, $stringSignature, $insertPositionSignature, 0);
        } else {
            throw new Exception("The <cac:AccountingSupplierParty> tag was not found in the XML.");
        }

        $base64Invoice = base64_encode($xmlDeclaration . "\n" . $updatedXmlString);

        // Generate Array Result
        $result = array(
            "invoiceHash" => $base64Hash,
            "uuid" => $uuid,
            "invoice" => $base64Invoice,
        );

        // Convert Array to JSON string
        return json_encode($result);
    }

    private static function getIssuerName($certInfo) {
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

    private static function getSerialNumberForCertificateObject($certInfo) {
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

    private static function getSignedPropertiesHash($signingTime, $digestValue, $x509IssuerName, $x509SerialNumber) {

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

    private static function getDigitalSignature($xmlHashing, $privateKeyContent) {
        $hashBytes = base64_decode($xmlHashing);

        if ($hashBytes === false) {
            throw new Exception("Failed to decode the base64-encoded XML hashing.");
        }
        
        $privateKeyContent = str_replace(["\n", "\t"], '', $privateKeyContent);
        
        if (strpos($privateKeyContent, "-----BEGIN EC PRIVATE KEY-----") === false &&
            strpos($privateKeyContent, "-----END EC PRIVATE KEY-----") === false) {
            $privateKeyContent = "-----BEGIN EC PRIVATE KEY-----\n" . 
                                chunk_split($privateKeyContent, 64, "\n") . 
                                "-----END EC PRIVATE KEY-----\n";
        }

        $privateKey = openssl_pkey_get_private($privateKeyContent);
        
        if ($privateKey === false) {
            throw new Exception("Failed to read private key.");
        }

        $signature = '';
        
        if (!openssl_sign($hashBytes, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Failed to sign the data.");
        }

        return base64_encode($signature);
    }

}
?>