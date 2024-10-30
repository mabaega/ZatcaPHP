<?php 
class ApiHelper {

    public static function complianceCSID($certInfo) {
        $csr = $certInfo['csr'];
        $OTP = $certInfo['OTP'];
        $url= $certInfo['complianceCsidUrl'];

        $jsonPayload = json_encode([
            'csr' => $csr
        ]);

       
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'accept-language: en',
            'OTP: $OTP',
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

    public static function productionCSID($certInfo) {
        $requestID = $certInfo['ccsid_requestID'];
        
        $id = $certInfo['ccsid_binarySecurityToken'];
        $secret = $certInfo['ccsid_secret'];
        
        $url= $certInfo['productionCsidUrl'];

        $jsonPayload = json_encode([
            'compliance_request_id' => $requestID
        ]);

        $ch = curl_init($url);

        $auth = base64_encode("$id:$secret");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'accept-language: en',
            'Accept-Version: V2',
            "Authorization: Basic $auth",  
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

    public static function complianceChecks($certInfo, $jsonPayload) {
        $id = $certInfo['ccsid_binarySecurityToken'];
        $secret = $certInfo['ccsid_secret'];
        $url = $certInfo["complianceChecksUrl"];

        $ch = curl_init($url);

        $auth = base64_encode("$id:$secret");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'accept-language: en',
            'Accept-Version: V2',
            "Authorization: Basic $auth",  
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

    public static function invoiceReporting($certInfo, $jsonPayload) {

        $id = $certInfo['ccsid_binarySecurityToken'];
        $secret = $certInfo['ccsid_secret'];
        $url = $certInfo["complianceChecksUrl"];

    $ch = curl_init($url);

    $auth = base64_encode("$id:$secret");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json',
        'accept-language: en',
        'Clearance-Status: 1',
        'Accept-Version: V2',
        "Authorization: Basic $auth",  
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

public static function invoiceClearance($certInfo, $jsonPayload) {
    $id = $certInfo['ccsid_binarySecurityToken'];
    $secret = $certInfo['ccsid_secret'];
    $url = $certInfo["complianceChecksUrl"];

    $ch = curl_init($url);

    $auth = base64_encode("$id:$secret");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json',
        'accept-language: en',
        'Clearance-Status: 1',
        'Accept-Version: V2',
        "Authorization: Basic $auth",  
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
    // Decode JSON to associative array
    $arrayResponse = json_decode($apiResponse, true);
    
    // Check if the removeNulls function exists before declaring it
    if (!function_exists('removeNulls')) {
        function removeNulls(&$array) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    removeNulls($array[$key]);
                }
                if (is_null($value)) {
                    unset($array[$key]);
                }
            }
        }
    }

    // Call the function to remove null values
    removeNulls($arrayResponse);

    // Adding new fields at the root level
    $arrayResponse['requestType'] = $requestType; // Insert new field
    $arrayResponse['apiUrl'] = $apiUrl; // Insert new field

    // Encode the cleaned array back to JSON
    return json_encode($arrayResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}


}
?>