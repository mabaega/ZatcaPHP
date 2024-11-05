<?php

class InvoiceHelper {

    public static function isSimplifiedInvoice($xml) {
        $xpath = new DOMXPath($xml);
        $invoiceTypeCodeNode = $xpath->query('//cbc:InvoiceTypeCode')->item(0);
        if ($invoiceTypeCodeNode) {
            $nameAttribute = $invoiceTypeCodeNode->getAttribute('name');
            return strpos($nameAttribute, '02') === 0;
        }
        return false;
    }

    public static function ModifyXml($xml, $id, $invoiceTypeCodename, $invoiceTypeCodeValue, $icv, $pih, $instructionNote)
    {
        // Clone the document to keep the original intact
        $newDoc = $xml->cloneNode(true);
        $newDoc->preserveWhiteSpace = true;

        $xpath = new DOMXPath($newDoc);
        $xpath->registerNamespace("cbc", "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
        $xpath->registerNamespace("cac", "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");

        // Generate a new GUID for the UUID
        $guidString = Self::generateUUIDv4(); // generating a GUID equivalent

        // Modify the ID node
        $idNode = $xpath->query("//cbc:ID")->item(0);
        if ($idNode !== null) {
            $idNode->nodeValue = $id;
        }

        // Modify the UUID node
        $uuidNode = $xpath->query("//cbc:UUID")->item(0);
        if ($uuidNode !== null) {
            $uuidNode->nodeValue = $guidString;
        }

        // Modify InvoiceTypeCode node and set 'name' attribute
        $invoiceTypeCodeNode = $xpath->query("//cbc:InvoiceTypeCode")->item(0);
        if ($invoiceTypeCodeNode !== null) {
            $invoiceTypeCodeNode->nodeValue = $invoiceTypeCodeValue;
            $invoiceTypeCodeNode->setAttribute("name", $invoiceTypeCodename);
        }

        // Update AdditionalDocumentReference for ICV
        $additionalReferenceNode = $xpath->query("//cac:AdditionalDocumentReference[cbc:ID='ICV']/cbc:UUID")->item(0);
        if ($additionalReferenceNode !== null) {
            $additionalReferenceNode->nodeValue = (string)$icv;
        } else {
            echo "UUID node not found for ICV.\n";
        }
        // Update AdditionalDocumentReference for PIH
        $pihNode = $xpath->query("//cac:AdditionalDocumentReference[cbc:ID='PIH']/cac:Attachment/cbc:EmbeddedDocumentBinaryObject")->item(0);
        if ($pihNode !== null) {
            $pihNode->nodeValue = $pih;
        } else {
            echo "EmbeddedDocumentBinaryObject node not found for PIH.\n";
        }

        // Conditionally add InstructionNote or remove BillingReference elements
        if (!empty($instructionNote)) {
            // Add InstructionNote element to PaymentMeans
            $paymentMeansNode = $xpath->query("//cac:PaymentMeans")->item(0);
            if ($paymentMeansNode !== null) {
                $instructionNoteElement = $newDoc->createElementNS(
                    "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2",
                    "cbc:InstructionNote",
                    $instructionNote
                );
                $paymentMeansNode->appendChild($instructionNoteElement);
            }
        } else {
            // Remove BillingReference elements
            $billingReferenceNodes = $xpath->query("//cac:BillingReference");
            foreach ($billingReferenceNodes as $billingReferenceNode) {
                $billingReferenceNode->parentNode->removeChild($billingReferenceNode);
            }
        }

        return $newDoc;
    }

    private static function generateUUIDv4() {
        $data = random_bytes(16); 
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100 
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10 
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)); 
    }

    public static function ExtractInvoiceHashAndBase64QrCode($xmlInput) {
        
        if (is_string($xmlInput)) {
            $decodedXml = base64_decode($xmlInput);
            if ($decodedXml === false) {
                throw new InvalidArgumentException("Invalid Base64 string provided.");
            }
            $xmlInput = $decodedXml;
        } elseif (!($xmlInput instanceof DOMDocument)) {
            throw new InvalidArgumentException("Input must be a string or DOMDocument.");
        }
        
        // Load XML into DOMDocument
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        if (is_string($xmlInput)) {
            $doc->loadXML($xmlInput);
        } else {
            $doc = $xmlInput; // Assume it's already a DOMDocument object
        }

        // Initialize DOMXPath with namespaces
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ext', "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2");
        $xpath->registerNamespace('ds', "http://www.w3.org/2000/09/xmldsig#");
        $xpath->registerNamespace('cbc', "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
        $xpath->registerNamespace('cac', "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");

        // Extract invoiceHash
        $invoiceHashNode = $xpath->query("//ds:Reference[@Id='invoiceSignedData']/ds:DigestValue")->item(0);
        $invoiceHash = $invoiceHashNode ? $invoiceHashNode->nodeValue : null;

        // Extract base64QRCode
        $base64QrCodeNode = $xpath->query("//cac:AdditionalDocumentReference[cbc:ID='QR']/cac:Attachment/cbc:EmbeddedDocumentBinaryObject")->item(0);
        $base64QRCode = $base64QrCodeNode ? $base64QrCodeNode->nodeValue : null;

        return [$invoiceHash, $base64QRCode];
    }

}
?>