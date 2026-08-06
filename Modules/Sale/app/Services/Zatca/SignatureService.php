<?php

namespace Modules\Sale\Services\Zatca;

use Illuminate\Support\Facades\Storage;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * Signature Service for ZATCA Invoice Signing
 * Implements ECDSA/SHA-256 digital signing for XML documents
 */
class SignatureService
{
    /**
     * Sign XML document with ECDSA/SHA-256
     * 
     * @param string $xml The UBL XML to sign
     * @param string $privateKeyPath Path to private key file
     * @param string $certificatePath Path to certificate file
     * @return string Signed XML
     * @throws \Exception
     */
    public function signXml(string $xml, string $privateKeyPath, string $certificatePath): string
    {
        // Load private key and certificate
        $privateKey = $this->loadPrivateKey($privateKeyPath);
        $certificate = $this->loadCertificate($certificatePath);
        
        // Create DOM document from XML
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        
        // Create XMLSecurityDSig object
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        
        // Add reference to the invoice
        $objDSig->addReferenceList(
            [$doc->documentElement],
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => false]
        );
        
        // Create security key
        $objKey = new XMLSecurityKey(XMLSecurityKey::ECDSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($privateKey, false, true);
        
        // Add certificate to the signature
        $objDSig->add509Cert($certificate, true);
        
        // Sign the XML
        $objDSig->sign($objKey);
        
        // Append signature to the document
        $objDSig->appendSignature($doc->documentElement);
        
        return $doc->saveXML();
    }

    /**
     * Load private key from file
     * 
     * @param string $keyPath Path to private key file
     * @return string Private key content
     * @throws \Exception
     */
    protected function loadPrivateKey(string $keyPath): string
    {
        $keyContent = null;
        
        // Try to resolve path - check if absolute or relative
        if (str_starts_with($keyPath, '/') || preg_match('/^[A-Z]:\\\\/', $keyPath)) {
            // Absolute path
            if (file_exists($keyPath)) {
                $keyContent = file_get_contents($keyPath);
            }
        } else {
            // Relative path - try storage first, then storage_path
            if (Storage::exists($keyPath)) {
                $keyContent = Storage::get($keyPath);
            } elseif (file_exists(storage_path($keyPath))) {
                $keyContent = file_get_contents(storage_path($keyPath));
            } elseif (file_exists($keyPath)) {
                $keyContent = file_get_contents($keyPath);
            }
        }
        
        if ($keyContent === null) {
            throw new \Exception("Private key file not found: {$keyPath}. Checked: " . ($keyPath) . (Storage::exists($keyPath) ? ' (storage)' : '') . (file_exists($keyPath) ? ' (file)' : ''));
        }
        
        // Validate it's a private key
        if (strpos($keyContent, 'BEGIN PRIVATE KEY') === false && 
            strpos($keyContent, 'BEGIN EC PRIVATE KEY') === false &&
            strpos($keyContent, 'BEGIN RSA PRIVATE KEY') === false) {
            throw new \Exception("The file at {$keyPath} does not appear to be a valid private key. Expected '-----BEGIN PRIVATE KEY-----' or similar header.");
        }
        
        return $keyContent;
    }

    /**
     * Load certificate from file
     * 
     * @param string $certPath Path to certificate file
     * @return string Certificate content
     * @throws \Exception
     */
    protected function loadCertificate(string $certPath): string
    {
        $certContent = null;
        
        // Try to resolve path - check if absolute or relative
        if (str_starts_with($certPath, '/') || preg_match('/^[A-Z]:\\\\/', $certPath)) {
            // Absolute path
            if (file_exists($certPath)) {
                $certContent = file_get_contents($certPath);
            }
        } else {
            // Relative path - try storage first, then storage_path
            if (Storage::exists($certPath)) {
                $certContent = Storage::get($certPath);
            } elseif (file_exists(storage_path($certPath))) {
                $certContent = file_get_contents(storage_path($certPath));
            } elseif (file_exists($certPath)) {
                $certContent = file_get_contents($certPath);
            }
        }
        
        if ($certContent === null) {
            // Check if CSR file exists instead
            $csrPath = str_replace('certificate.pem', 'csr.pem', $certPath);
            $csrExists = false;
            if (Storage::exists($csrPath)) {
                $csrExists = true;
            } elseif (file_exists($csrPath)) {
                $csrExists = true;
            } elseif (file_exists(storage_path($csrPath))) {
                $csrExists = true;
            }
            
            $errorMsg = "Certificate file not found: {$certPath}";
            if ($csrExists) {
                $errorMsg .= "\n\n⚠️ IMPORTANT: You have 'csr.pem' but need 'certificate.pem'.";
                $errorMsg .= "\nA CSR (Certificate Signing Request) is NOT a certificate.";
                $errorMsg .= "\nYou need to:\n1. Submit your CSR to ZATCA portal\n2. Download the certificate from ZATCA\n3. Save it as 'certificate.pem'";
            }
            throw new \Exception($errorMsg);
        }
        
        // Validate it's actually a certificate, not a CSR
        if (strpos($certContent, 'BEGIN CERTIFICATE REQUEST') !== false) {
            throw new \Exception("The file at {$certPath} is a CSR (Certificate Signing Request), not a certificate. You need the actual certificate file from ZATCA.");
        }
        
        if (strpos($certContent, 'BEGIN CERTIFICATE') === false) {
            throw new \Exception("The file at {$certPath} does not appear to be a valid certificate. Expected '-----BEGIN CERTIFICATE-----' header.");
        }
        
        return $certContent;
    }
}
