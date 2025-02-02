<?php
class ApiHelper {

    private static function sendRequestWithRetry($url, $headers, $payload, $auth = null, $method = 'POST', $retries = 3, $backoffFactor = 1) {
        for ($attempt = 0; $attempt < $retries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

            if ($auth) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ["Authorization: Basic $auth"]));
            }

            $response = curl_exec($ch);
            


            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                curl_close($ch);

                if ($attempt < $retries - 1) {
                    sleep($backoffFactor * (2 ** $attempt));
                    continue;
                } else {
                    throw new Exception("cURL error after $retries retries: $error_msg");
                }
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return $response;
            } else {
                if ($attempt < $retries - 1) {
                    sleep($backoffFactor * (2 ** $attempt));
                    continue;
                } else {
                    throw new Exception("HTTP error after $retries retries: $httpCode - $response");
                }
            }
        }
    }

    /* private static function sendRequestWithRetry($url, $headers, $payload, $auth = null, $method = 'POST', $retries = 3, $backoffFactor = 1) {
        for ($attempt = 0; $attempt < $retries; $attempt++) {
            // Debug request information
            error_log("Request Attempt #" . ($attempt + 1));
            error_log("URL: " . $url);
            error_log("Method: " . $method);
            error_log("Headers: " . print_r($headers, true));
            error_log("Payload: " . print_r($payload, true));
            if ($auth) {
                error_log("Using Basic Authentication");
            }
    
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
            // Add verbose debugging
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
            if ($auth) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ["Authorization: Basic $auth"]));
            }
    
            $response = curl_exec($ch);
            
            // Debug response information
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $requestInfo = curl_getinfo($ch);
            
            error_log("Response Status Code: " . $httpCode);
            error_log("Total Time: " . $requestInfo['total_time'] . " seconds");
            error_log("Response Size: " . $requestInfo['size_download'] . " bytes");
            error_log("Response: " . $response);
    
            // Get verbose debug information
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            error_log("Verbose cURL log:");
            error_log($verboseLog);
    
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                error_log("cURL Error: " . $error_msg);
                curl_close($ch);
                fclose($verbose);
    
                if ($attempt < $retries - 1) {
                    $sleepTime = $backoffFactor * (2 ** $attempt);
                    error_log("Retrying in $sleepTime seconds...");
                    sleep($sleepTime);
                    continue;
                } else {
                    throw new Exception("cURL error after $retries retries: $error_msg");
                }
            }
    
            curl_close($ch);
            fclose($verbose);
    
            if ($httpCode === 200) {
                error_log("Request successful!");
                return $response;
            } else {
                if ($attempt < $retries - 1) {
                    $sleepTime = $backoffFactor * (2 ** $attempt);
                    error_log("HTTP error $httpCode. Retrying in $sleepTime seconds...");
                    sleep($sleepTime);
                    continue;
                } else {
                    throw new Exception("HTTP error after $retries retries: $httpCode - $response");
                }
            }
        }
    } */

    public static function complianceCSID($certInfo) {
        $csr = $certInfo['csr'];
        $OTP = $certInfo['OTP'];
        $url = $certInfo['complianceCsidUrl'];

        $jsonPayload = json_encode(['csr' => $csr]);
        $headers = [
            'accept: application/json',
            'accept-language: en',
            "OTP: $OTP",
            'Accept-Version: V2',
            'Content-Type: application/json',
        ];

        return self::sendRequestWithRetry($url, $headers, $jsonPayload);
    }

    public static function complianceChecks($certInfo, $jsonPayload) {
        $url = $certInfo['complianceChecksUrl'];
        $auth = base64_encode($certInfo['ccsid_binarySecurityToken'] . ':' . $certInfo['ccsid_secret']);

        $headers = [
            'accept: application/json',
            'accept-language: en',
            'Accept-Version: V2',
            'Content-Type: application/json',
        ];

        return self::sendRequestWithRetry($url, $headers, $jsonPayload, $auth);
    }

    public static function invoiceReporting($certInfo, $jsonPayload) {
        $url = $certInfo['reportingUrl'];
        $auth = base64_encode($certInfo['pcsid_binarySecurityToken'] . ':' . $certInfo['pcsid_secret']);

        $headers = [
            'accept: application/json',
            'accept-language: en',
            'Clearance-Status: 1',
            'Accept-Version: V2',
            'Content-Type: application/json',
        ];

        return self::sendRequestWithRetry($url, $headers, $jsonPayload, $auth);
    }

    public static function invoiceClearance($certInfo, $jsonPayload) {
        $url = $certInfo['clearanceUrl'];
        $auth = base64_encode($certInfo['pcsid_binarySecurityToken'] . ':' . $certInfo['pcsid_secret']);

        $headers = [
            'accept: application/json',
            'accept-language: en',
            'Clearance-Status: 1',
            'Accept-Version: V2',
            'Content-Type: application/json',
        ];

        return self::sendRequestWithRetry($url, $headers, $jsonPayload, $auth);
    }

    public static function productionCSID($certInfo) {
        $requestID = $certInfo['ccsid_requestID'];
        $url = $certInfo['productionCsidUrl'];
        $auth = base64_encode($certInfo['ccsid_binarySecurityToken'] . ':' . $certInfo['ccsid_secret']);

        $jsonPayload = json_encode(['compliance_request_id' => $requestID]);
        $headers = [
            'accept: application/json',
            'accept-language: en',
            'Accept-Version: V2',
            'Content-Type: application/json',
        ];

        return self::sendRequestWithRetry($url, $headers, $jsonPayload, $auth);
    }

    public static function renewalCSID($certInfo) {
        $csr = $certInfo['csr'];
        $OTP = $certInfo['OTP'];
        $url = $certInfo['productionCsidUrl'];
        $auth = base64_encode($certInfo['pcsid_binarySecurityToken'] . ':' . $certInfo['pcsid_secret']);

        $jsonPayload = json_encode(['csr' => $csr]);
        $headers = [
            'accept: application/json',
            'accept-language: en',
            "OTP: $OTP",
            'Accept-Version: V2',
            'Content-Type: application/json',
        ];

        return self::sendRequestWithRetry($url, $headers, $jsonPayload, $auth, 'PATCH');
    }

            
    public static function loadJsonFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }
        
        $jsonData = file_get_contents($filePath);
        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error parsing JSON: " . json_last_error_msg());
        }

        return $data;
    }

    public static function saveJsonToFile($filePath, $data)
    {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error encoding JSON: " . json_last_error_msg());
        }

        file_put_contents($filePath, $jsonData);
    }

    public static function cleanUpJson($apiResponse, $requestType, $apiUrl) {
        // Decode the JSON response to an associative array
        $arrayResponse = json_decode($apiResponse, true);

        // Add new fields at the root level
        $arrayResponse['requestType'] = $requestType;
        $arrayResponse['apiUrl'] = $apiUrl;

        // Remove null values from the array
        $arrayResponse = array_filter($arrayResponse, function($value) {
            return $value !== null;
        });

        // Reorder the array to ensure requestType and apiUrl are at the top
        $reorderedResponse = array_merge(
            ['requestType' => $arrayResponse['requestType'], 'apiUrl' => $arrayResponse['apiUrl']],
            array_diff_key($arrayResponse, ['requestType' => '', 'apiUrl' => ''])
        );

        // Encode the array back to JSON
        return stripslashes(json_encode($reorderedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
?>
