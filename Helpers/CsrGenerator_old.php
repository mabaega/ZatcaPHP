<?php 

class CsrGenerator{

    //Generate CSR and Private Key
    public static function GenerateCsrAndPrivateKey($certInfo) 
    {
        // 1. Generate CSR & Private Key
        $configFilePath = 'certificate/csr.config';
        $configPath = 'certificate/config.cnf';
        $privateKeyFile = 'certificate/PrivateKey.pem';
        $csrFile = 'certificate/taxpayer.csr';

        // // 1.1 Read data from csr.config
        $data = Self::readConfigFile($configFilePath);
        
        // 1.2 Generate Config.cnf
        file_put_contents($configPath, Self::generateCnfContent($data, $certInfo['environmentType']));
        echo "\n\nConfig file generated successfully!";

        // 1.3 Execute functions
        Self::generateEcPrivateKey($privateKeyFile);
        $certInfo['csr'] = Self::generateCsr($privateKeyFile, $configPath, $csrFile);

        $certInfo['privateKey'] = Self::cleanPrivateKey($privateKeyFile); 

        // Output success message
        echo "\n\nPrivate Key (cleaned), CSR (Base64), and Public Key generated successfully.";
        return $certInfo;
    }


    // Function to read the configuration file and return data as an associative array
    private static function readConfigFile($filePath) {
        $configData = [];
        
        if (file_exists($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $configData[trim($key)] = trim($value);
                }
            }
            
            // Organize data into the structure expected by the generateCnfContent function
            $data = [
                'csr' => [
                    'common_name' => $configData['csr.common.name'],
                    'serial_number' => $configData['csr.serial.number'],
                    'organization_identifier' => $configData['csr.organization.identifier'],
                    'organization_unit_name' => $configData['csr.organization.unit.name'],
                    'organization_name' => $configData['csr.organization.name'],
                    'country_name' => $configData['csr.country.name'],
                    'invoice_type' => $configData['csr.invoice.type'],
                    'location_address' => $configData['csr.location.address'],
                    'industry_business_category' => $configData['csr.industry.business.category']
                ]
            ];
            
        } else {
            die("\n\nConfig file not found: " . $filePath);
        }
        
        return $data;
    }

    // Function to generate the content for config.cnf
    private static function generateCnfContent($data, $environmentType) {
        
        $asnTemplate = "TSTZATCA-Code-Signing";

        switch ($environmentType) {
            case 'NonProduction':
                $asnTemplate = 'TSTZATCA-Code-Signing';
                break;
            case 'Simulation':
                $asnTemplate = 'PREZATCA-Code-Signing';
                break;
            case 'Production':  
                $asnTemplate = 'ZATCA-Code-Signing';
                break;
        }

        $cnfContent = '';

        // OID Section
        $cnfContent .= "oid_section = OIDs\n";
        $cnfContent .= "[OIDs]\n";
        $cnfContent .= "certificateTemplateName=1.3.6.1.4.1.1311.20.2\n\n";

        // req Section
        $cnfContent .= "[req]\n";
        $cnfContent .= "default_bits = 2048\n";
        $cnfContent .= "emailAddress = email@email.com\n";
        $cnfContent .= "req_extensions = v3_req\n";
        $cnfContent .= "x509_extensions = v3_ca\n";
        $cnfContent .= "prompt = no\n";
        $cnfContent .= "default_md = sha256\n";
        $cnfContent .= "req_extensions = req_ext\n";
        $cnfContent .= "distinguished_name = dn\n\n";

        // dn Section
        $cnfContent .= "[dn]\n";
        foreach ($data['csr'] as $key => $value) {
            switch ($key) {
                case 'common_name':
                    $cnfContent .= "CN=$value\n";
                    break;
                case 'country_name':
                    $cnfContent .= "C=$value\n";
                    break;
                case 'organization_unit_name':
                    $cnfContent .= "OU=$value\n";
                    break;
                case 'organization_name':
                    $cnfContent .= "O=$value\n";
                    break;
                default:
                    // Handle other fields if necessary
                    break;
            }
        }
        
        // v3_req Section
        $cnfContent .= "\n[v3_req]\n";
        $cnfContent .= "basicConstraints = CA:FALSE\n";
        $cnfContent .= "keyUsage = digitalSignature, nonRepudiation, keyEncipherment\n";

        // req_ext Section
        $cnfContent .= "\n[req_ext]\n";
        $cnfContent .= "certificateTemplateName = ASN1:PRINTABLESTRING:" . $asnTemplate ."\n"; 
        $cnfContent .= "subjectAltName = dirName:alt_names\n";

        // alt_names Section
        $cnfContent .= "\n[alt_names]\n";
        $cnfContent .= "SN=" . htmlspecialchars($data['csr']['serial_number']) . "\n";  
        $cnfContent .= "UID=" . htmlspecialchars($data['csr']['organization_identifier']) . "\n";  
        $cnfContent .= "title=" . htmlspecialchars($data['csr']['invoice_type']) . "\n";  
        $cnfContent .= "registeredAddress=" . htmlspecialchars($data['csr']['location_address']) . "\n"; 
        $cnfContent .= "businessCategory=" . htmlspecialchars($data['csr']['industry_business_category']) . "\n";

        return $cnfContent;
    }

    // Generate EC private key
    private static function generateEcPrivateKey($outputFilePath) {
        $cmd = "openssl ecparam -name secp256k1 -genkey -noout -out " . escapeshellarg($outputFilePath);
        exec($cmd, $output, $returnVar);
        echo($cmd);
        if ($returnVar !== 0) {
            die("Error generating EC private key: " . implode("\n", $output));
        }
    }

    // Generate CSR
    private static function generateCsr($privateKeyFilePath, $configPath, $csrOutputFilePath) {
        $cmd = "openssl req -new -sha256 -key " . escapeshellarg($privateKeyFilePath) . 
            " -config " . escapeshellarg($configPath) . 
            " -out " . escapeshellarg($csrOutputFilePath);
            echo($cmd);
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

    // Clean up the private key for output (after CSR generation)
    private static function cleanPrivateKey($privateKeyFilePath) {
        $privateKeyContent = file_get_contents($privateKeyFilePath);
        $cleanedKey = preg_replace('/-+BEGIN[^-]+-+|-+END[^-]+-+/', '', $privateKeyContent);  
        $cleanedKey = str_replace(["\r", "\n"], '', $cleanedKey);  
        file_put_contents($privateKeyFilePath, $cleanedKey);  
        return $cleanedKey;
    }

    // Generate CSR
    public static function generateRenewalCsr($privateKeyFile, $configPath, ) {
        $tmpPrivateKeyFilePath = "certificate\TmpPrivateKey.pem";
        $csrOutputFilePath = "certificate\TmpTaxPayer.csr";

        $cleanedKey = file_get_contents($privateKeyFile);

        // Define the header and footer for the private key
        $header = "-----BEGIN PRIVATE KEY-----\n";
        $footer = "-----END PRIVATE KEY-----\n";
    
        // Format the cleaned key into lines of 64 characters
        $formattedKey = wordwrap($cleanedKey, 64, "\n", true);
    
        // Combine the header, formatted key, and footer
        $fullKey = $header . $formattedKey . "\n" . $footer;
    
        // Save the full key back to the file
        file_put_contents($tmpPrivateKeyFilePath, $fullKey);

        $cmd = "openssl req -new -sha256 -key " . escapeshellarg($tmpPrivateKeyFilePath) . 
            " -config " . escapeshellarg($configPath) . 
            " -out " . escapeshellarg($csrOutputFilePath);
            echo($cmd);
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            die("Error generating CSR: " . implode("\n", $output));
        }

        // Read the CSR content and encode it in Base64
        $csrContent = file_get_contents($csrOutputFilePath);
        $csrContent = base64_encode($csrContent);

        return $csrContent;
    }
}

?>