<?php 

class QRCodeGenerator
{
    public static function generateQRCode($canonicalXml, $InvoiceHash, $SignatureValue, $x509CertificateContent)
    {
        try {
            $invoiceDetails = self::getInvoiceDetails($canonicalXml, $InvoiceHash, $SignatureValue);

            // Retrieve the InvoiceTypeCode name (from position 8 in array)
            $invoiceTypeCodeName = $invoiceDetails[8];

            $result = self::getPublicKeyAndSignature($x509CertificateContent); //X509CertificateHelper::getPublicKeyAndSignature($x509CertificateContent);
            $invoiceDetails[8] = $result['public_key_raw'];
        
            // Only add certificateSignature if InvoiceTypeCode name starts with "02"
            if (strpos($invoiceTypeCodeName, "02") === 0) {
                $invoiceDetails[9] =  $result['signature']; //$certificateSignature;
            }
            
            $base64QrCode = self::generateQRCodeFromValues($invoiceDetails);
            
            $binaryData = base64_decode($base64QrCode);

            $hexDump = unpack('H*', $binaryData)[1];

            // Cetak hex dump agar bisa dibandingkan
            echo "Hex Dump QR Code PHP:\n";
            echo chunk_split($hexDump, 2, ' ');
            
            echo "\nGenerated Base64QRCode:\n". $base64QrCode;

            return $base64QrCode;
        } catch (Exception $exception) {
            throw new Exception("Error generating EInvoice QR Code", 0, $exception);
    }
    }

    private static function getInvoiceDetails($xml, $InvoiceHash, $SignatureValue)
    {
        // Load XML into SimpleXMLElement
        $xmlObject = new SimpleXMLElement($xml);
        
        // Register namespaces
        $xmlObject->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xmlObject->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        // Extract InvoiceTypeCode and its name attribute
        $invoiceTypeCode = (string)$xmlObject->xpath('//cbc:InvoiceTypeCode')[0];
        $invoiceTypeCodeName = (string)$xmlObject->xpath('//cbc:InvoiceTypeCode/@name')[0]; 

        // Extracting values using XPath
        $supplierName = (string)$xmlObject->xpath('//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName')[0];
        $companyID = (string)$xmlObject->xpath('//cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID')[0];
        $issueDateTime = (string)$xmlObject->xpath('//cbc:IssueDate')[0] . 'T' . (string)$xmlObject->xpath('//cbc:IssueTime')[0];
        $payableAmount = (string)$xmlObject->xpath('//cac:LegalMonetaryTotal/cbc:PayableAmount')[0];
        $taxAmount = (string)$xmlObject->xpath('//cac:TaxTotal/cbc:TaxAmount')[0];

        return [
            1 => $supplierName,
            2 => $companyID,
            3 => $issueDateTime,
            4 => $payableAmount,
            5 => $taxAmount,
            6 => $InvoiceHash,
            7 => $SignatureValue,
            8 => $invoiceTypeCodeName // InvoiceTypeCode name attribute will replaced with ECSDA Publickey
        ];
    }


    private static function generateQRCodeFromValues($invoiceDetails)
    {
        $data = '';
        foreach ($invoiceDetails as $key => $value) {
            echo "Key: " . $key . ", Value: " . $value . "\n";
            $data .= self::writeTlv($key, $value);
        }
        return base64_encode($data);
    }

    private static function writeLength($length)
    {
        if ($length === null) {
            return chr(0x80);
        }

        if ($length <= 0x7F) {
            return chr($length);
        }

        $bytes = [];
        while ($length > 0) {
            array_unshift($bytes, $length & 0xFF);
            $length >>= 8;
        }

        $lenLen = count($bytes);
        return chr(0x80 | $lenLen) . implode('', array_map('chr', $bytes));
    }

    private static function writeTag($tag)
    {
        $result = '';
        $flag = true;
        for ($i = 3; $i >= 0; $i--) {
            $num = ($tag >> (8 * $i)) & 0xFF;
            if ($num != 0 || !$flag || $i === 0) {
                if ($flag && $i != 0 && ($num & 0x1F) != 0x1F) {
                    throw new Exception("[Error] Invalid tag value");
                }
                $result .= chr($num);
                $flag = false;
            }
        }
        return $result;
    }

    private static function xwriteTlv($tag, $value)
    {
        if ($value === null) {
            throw new Exception("[Error] Please provide a value!");
        }

        $tlv = self::writeTag($tag);
        $tlv .= self::writeLength(strlen($value));
        $tlv .= $value;
        return $tlv;
    }

    private static function writeTlv($tag, $value)
    {
        if ($value === null) {
            throw new Exception("[Error] Please provide a value!");
        }

        // Ensure $value is a byte array
        if (is_array($value)) {
            $value = implode(array_map("chr", $value)); // Convert byte values to characters
        }

        // Start building the TLV structure
        $tlv = self::writeTag($tag);  // Write the tag
        $length = strlen($value);      // Get the length of the value

        // Write the length
        $tlv .= self::writeLength($length);
        
        // Append the value
        $tlv .= $value;

        return $tlv;
    }


    private static function getPublicKeyAndSignature(string $certificateBase64): array 
    {
        try {
            // Step 1: Create a temporary file for the certificate
            $tempFile = tempnam(sys_get_temp_dir(), 'cert');

            // Step 2: Write the certificate content to the temporary file
            $certContent = "-----BEGIN CERTIFICATE-----\n";
            $certContent .= chunk_split($certificateBase64, 64, "\n");
            $certContent .= "-----END CERTIFICATE-----\n";
    
            if (file_put_contents($tempFile, $certContent) === false) {
                throw new Exception("Cannot write certificate to temporary file");
            }
    
            // Step 3: Read the certificate
            $cert = openssl_x509_read(file_get_contents($tempFile));
    
            // Step 4: Extract the public key
            $pubKey = openssl_pkey_get_public($cert);
            $pubKeyDetails = openssl_pkey_get_details($pubKey);
    
            // Step 5: Construct raw public key from x and y components
            $x = $pubKeyDetails['ec']['x'];
            $y = $pubKeyDetails['ec']['y'];
    
            // Ensure x and y are 32 bytes long for secp256k1
            $x = str_pad($x, 32, "\0", STR_PAD_LEFT);
            $y = str_pad($y, 32, "\0", STR_PAD_LEFT);
    
            // Prepare the raw public key in uncompressed DER format
            $publicKeyDER = pack('C*',
                0x30, // SEQUENCE
                0x56, // Total length of the sequence (to be calculated)
                0x30, // SEQUENCE for the algorithm
                0x10, // Length of the OID
                0x06, 0x07, 0x2A, 0x86, 0x48, 0xCE, 0x3D, 0x02, 0x01, // OID for EC
                0x06, 0x05, 0x2B, 0x81, 0x04, 0x00, 0x0A, // OID for secp256k1
                0x03, 0x42, // BIT STRING tag and length
                0x00, 0x04, // Length of the uncompressed public key (2 * 32 bytes)
                ...array_values(unpack('C*', $x)), // x
                ...array_values(unpack('C*', $y))  // y
            );
    
            // Step 6: Extract the ECDSA signature from DER data
            $certPEM = file_get_contents($tempFile);
            if (!preg_match('/-+BEGIN CERTIFICATE-+\s+(.+)\s+-+END CERTIFICATE-+/s', $certPEM, $matches)) {
                throw new Exception("Error extracting DER data from certificate.");
            }
    
            $derData = base64_decode($matches[1]);
            $sequencePos = strpos($derData, "\x30", -72);
            $signature = substr($derData, $sequencePos);
 
            // Return the correctly extracted details
            return [
                'public_key' => base64_encode($publicKeyDER),  // PEM format for public key
                'public_key_raw' => $publicKeyDER, // Raw public key in DER format
                'signature' => $signature         // Raw ECDSA signature bytes
            ];
    
        } catch (Exception $e) {
            throw new Exception("[Error] Failed to process certificate: " . $e->getMessage());
        } finally {
            // Clean up resources
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

}

?>