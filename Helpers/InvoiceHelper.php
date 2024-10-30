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
        $guidString = strtoupper(bin2hex(random_bytes(16))); // generating a GUID equivalent

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
}
?>