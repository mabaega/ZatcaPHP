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

    public static function renewalCSID($certInfo) {
        $csr = $certInfo['csr'];
        $OTP = $certInfo['OTP'];
        $url = $certInfo['productionCsidUrl'];

        $id = $certInfo['pcsid_binarySecurityToken'];
        $secret = $certInfo['pcsid_secret'];
        
        $jsonPayload = json_encode([
            'csr' => $csr
        ]);
    
        $ch = curl_init($url);

        $auth = base64_encode("$id:$secret");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'accept-language: en',
            "OTP: $OTP",
            'Accept-Version: V2',
            "Authorization: Basic $auth",
            'Content-Type: application/json',
        ));
        
        // Use CURLOPT_CUSTOMREQUEST to specify PATCH
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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

        //echo $certInfo['ccsid_binarySecurityToken'];
        
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

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        //echo "\n$httpCode";

        curl_close($ch);

        return $response;
    }

    public static function invoiceReporting($certInfo, $jsonPayload) {

        $id = $certInfo['pcsid_binarySecurityToken'];
        $secret = $certInfo['pcsid_secret'];
        $url = $certInfo["reportingUrl"];

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
    $id = $certInfo['pcsid_binarySecurityToken'];
    $secret = $certInfo['pcsid_secret'];
    $url = $certInfo["clearanceUrl"];

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