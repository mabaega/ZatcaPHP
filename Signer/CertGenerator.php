<?php

class X509CertificateHelper {

    public static function getPublicKeyAndSignature(string $certificateBase64): array 
    {
        try {
            // Step 1: Create a temporary file for the certificate
            $tempFile = tempnam(sys_get_temp_dir(), 'cert');
            if ($tempFile === false) {
                throw new Exception("Cannot create temporary file");
            }
    
            // Step 2: Write the certificate content to the temporary file
            $certContent = "-----BEGIN CERTIFICATE-----\n";
            $certContent .= chunk_split($certificateBase64, 64, "\n");
            $certContent .= "-----END CERTIFICATE-----\n";
    
            if (file_put_contents($tempFile, $certContent) === false) {
                throw new Exception("Cannot write certificate to temporary file");
            }
    
            // Step 3: Read the certificate
            $cert = openssl_x509_read(file_get_contents($tempFile));
            if ($cert === false) {
                throw new Exception("Cannot read certificate. Check if the certificate format is correct.");
            }
    
            // Step 4: Extract the public key
            $pubKey = openssl_pkey_get_public($cert);
            if ($pubKey === false) {
                throw new Exception("Cannot extract public key from certificate.");
            }
    
            $pubKeyDetails = openssl_pkey_get_details($pubKey);
            if ($pubKeyDetails === false) {
                throw new Exception("Cannot retrieve public key details.");
            }
    
            // Debugging output for public key details
            //var_dump($pubKeyDetails);
    
            // Ensure the EC components exist
            //if (!isset($pubKeyDetails['ec']['x']) || !isset($pubKeyDetails['ec']['y'])) {
            //    throw new Exception("EC public key components 'x' and 'y' are missing.");
            //}
    
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
    
            // Step 6: Check the length of the public key for debugging
            if (strlen($publicKeyDER) < 65) {
                throw new Exception("Public key length is incorrect: " . strlen($publicKeyDER));
            }
    
            // Step 7: Extract the ECDSA signature from DER data
            $certPEM = file_get_contents($tempFile);
            if (!preg_match('/-+BEGIN CERTIFICATE-+\s+(.+)\s+-+END CERTIFICATE-+/s', $certPEM, $matches)) {
                throw new Exception("Error extracting DER data from certificate.");
            }
    
            $derData = base64_decode($matches[1]);
            if ($derData === false) {
                throw new Exception("Error decoding base64 certificate content.");
            }
    
            // Locate the SEQUENCE tag (0x30) near the end of the data for the signature
            $sequencePos = strpos($derData, "\x30", -72);
            if ($sequencePos === false) {
                throw new Exception("Error locating ECDSA SEQUENCE tag in DER data.");
            }
    
            $signature = substr($derData, $sequencePos);
    
            // Debugging output for signature
            //var_dump(bin2hex($signature));  // Show the signature in hex format
    
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

// Example usage:
try {
    $certificateBase64 = "MIID3jCCA4SgAwIBAgITEQAAOAPF90Ajs/xcXwABAAA4AzAKBggqhkjOPQQDAjBiMRUwEwYKCZImiZPyLGQBGRYFbG9jYWwxEzARBgoJkiaJk/IsZAEZFgNnb3YxFzAVBgoJkiaJk/IsZAEZFgdleHRnYXp0MRswGQYDVQQDExJQUlpFSU5WT0lDRVNDQTQtQ0EwHhcNMjQwMTExMDkxOTMwWhcNMjkwMTA5MDkxOTMwWjB1MQswCQYDVQQGEwJTQTEmMCQGA1UEChMdTWF4aW11bSBTcGVlZCBUZWNoIFN1cHBseSBMVEQxFjAUBgNVBAsTDVJpeWFkaCBCcmFuY2gxJjAkBgNVBAMTHVRTVC04ODY0MzExNDUtMzk5OTk5OTk5OTAwMDAzMFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEoWCKa0Sa9FIErTOv0uAkC1VIKXxU9nPpx2vlf4yhMejy8c02XJblDq7tPydo8mq0ahOMmNo8gwni7Xt1KT9UeKOCAgcwggIDMIGtBgNVHREEgaUwgaKkgZ8wgZwxOzA5BgNVBAQMMjEtVFNUfDItVFNUfDMtZWQyMmYxZDgtZTZhMi0xMTE4LTliNTgtZDlhOGYxMWU0NDVmMR8wHQYKCZImiZPyLGQBAQwPMzk5OTk5OTk5OTAwMDAzMQ0wCwYDVQQMDAQxMTAwMREwDwYDVQQaDAhSUlJEMjkyOTEaMBgGA1UEDwwRU3VwcGx5IGFjdGl2aXRpZXMwHQYDVR0OBBYEFEX+YvmmtnYoDf9BGbKo7ocTKYK1MB8GA1UdIwQYMBaAFJvKqqLtmqwskIFzVvpP2PxT+9NnMHsGCCsGAQUFBwEBBG8wbTBrBggrBgEFBQcwAoZfaHR0cDovL2FpYTQuemF0Y2EuZ292LnNhL0NlcnRFbnJvbGwvUFJaRUludm9pY2VTQ0E0LmV4dGdhenQuZ292LmxvY2FsX1BSWkVJTlZPSUNFU0NBNC1DQSgxKS5jcnQwDgYDVR0PAQH/BAQDAgeAMDwGCSsGAQQBgjcVBwQvMC0GJSsGAQQBgjcVCIGGqB2E0PsShu2dJIfO+xnTwFVmh/qlZYXZhD4CAWQCARIwHQYDVR0lBBYwFAYIKwYBBQUHAwMGCCsGAQUFBwMCMCcGCSsGAQQBgjcVCgQaMBgwCgYIKwYBBQUHAwMwCgYIKwYBBQUHAwIwCgYIKoZIzj0EAwIDSAAwRQIhALE/ichmnWXCUKUbca3yci8oqwaLvFdHVjQrveI9uqAbAiA9hC4M8jgMBADPSzmd2uiPJA6gKR3LE03U75eqbC/rXA==";
    
    $result = X509CertificateHelper::getPublicKeyAndSignature($certificateBase64);
    
    echo "Public Key (PEM):\n" . $result['public_key'] . "\n\n";
    echo "Raw Public Key:\n" . $result['public_key_raw'] . "\n\n";
    echo "Signature (hex):\n" . $result['signature'] . "\n";
    
} catch (Exception $e) {
    echo $e->getMessage();
}
?>