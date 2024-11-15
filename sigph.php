<?php
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


$signingTime = "2024-01-17T19:06:11";
$digestValue = "ZDMwMmI0MTE1NzVjOTU2NTk4YzVlODhhYmI0ODU2NDUyNTU2YTVhYjhhMDFmN2FjYjk1YTA2OWQ0NjY2MjQ4NQ==";
$x509IssuerName = "CN=PRZEINVOICESCA4-CA, DC=extgazt, DC=gov, DC=local";
$x509SerialNumber = "379112742831380471835263969587287663520528387";

// Calculate and print the Signed Properties Hash
$signedPropertiesHash = getSignedPropertiesHash($signingTime, $digestValue, $x509IssuerName, $x509SerialNumber);
echo "Signed Properties Hash: " . $signedPropertiesHash . "\n";

?>
