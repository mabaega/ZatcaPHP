<?php

class CsrGenerator
{
    private $config;
    private $environment_type;
    private $asn_template;

    public function __construct($config, $environment_type)
    {
        $this->config = $config;
        $this->environment_type = $environment_type;
        $this->asn_template = $this->getAsnTemplate();
    }

    private function getAsnTemplate()
    {
        if ($this->environment_type == 'NonProduction') {
            return 'TSTZATCA-Code-Signing';
        } elseif ($this->environment_type == 'Simulation') {
            return 'PREZATCA-Code-Signing';
        } elseif ($this->environment_type == 'Production') {
            return 'ZATCA-Code-Signing';
        } else {
            throw new Exception("Invalid environment type specified.");
        }
    }

    // Membuat konfigurasi .cnf dinamis berdasarkan input
    private function generateConfigFile()
    {
        $config = $this->config;
        $cnfContent = "
oid_section = OIDs
[OIDs]
certificateTemplateName=1.3.6.1.4.1.1311.20.2

[req]
default_bits = 2048
req_extensions = v3_req
prompt = no
default_md = sha256
req_extensions = req_ext
distinguished_name = dn

[dn]
CN={$config['csr.common.name']}
OU={$config['csr.organization.unit.name']}
O={$config['csr.organization.name']}
C={$config['csr.country.name']}

[v3_req]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment

[req_ext]
certificateTemplateName = ASN1:PRINTABLESTRING:{$this->asn_template}
subjectAltName = dirName:alt_names

[alt_names]
SN={$config['csr.serial.number']}
UID={$config['csr.organization.identifier']}  
title={$config['csr.invoice.type']} 
registeredAddress={$config['csr.location.address']} 
businessCategory={$config['csr.industry.business.category']}
";


        // Menyimpan konten ke file config.cnf
        $filePath = 'certificate/config.cnf';
        file_put_contents($filePath, $cnfContent);

        return $filePath;
    }

    public function generatePrivateKey()
    {
        // Menggunakan SECP256K1 untuk private key
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1', // Menggunakan SECP256K1 sesuai dengan permintaan
        ]);

        return $privateKey;
    }

    public function generateCsr()
    {
        // Menyusun file konfigurasi OpenSSL yang dinamis
        $configFile = $this->generateConfigFile();

        // Membuat private key
        $privateKey = $this->generatePrivateKey();

        // Membuat DN untuk CSR
        $dn = [
            "countryName" => $this->config['csr.country.name'] ?? 'SA',
            "organizationalUnitName" => $this->config['csr.organization.unit.name'] ?? '',
            "organizationName" => $this->config['csr.organization.name'] ?? '',
            "commonName" => $this->config['csr.common.name'] ?? '',
        ];

        // Pastikan untuk menyertakan file konfigurasi yang benar dalam csrConfig
        $csrConfig = [
            "config" => $configFile, // Menyertakan file konfigurasi yang telah dibuat
            "digest_alg" => "sha256",
        ];

        // Buat CSR menggunakan konfigurasi yang sudah diubah
        $csr = openssl_csr_new($dn, $privateKey, $csrConfig);

        if (!$csr) {
            throw new Exception('Error generating CSR: ' . openssl_error_string());
        }

        // Menandatangani CSR
        $csrPem = '';
        if (!openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256'])) {
            throw new Exception('Error signing CSR: ' . openssl_error_string());
        }

        // Menyimpan private key dan CSR ke dalam format PEM
        openssl_pkey_export($privateKey, $privateKeyPem);
        openssl_csr_export($csr, $csrPem);

        // Strip header/footer dari private key
        $privateKeyContent = preg_replace('/-+BEGIN[^-]+-+|-+END[^-]+-+/', '', $privateKeyPem);  
        $privateKeyContent = str_replace(["\r", "\n"], '', $privateKeyContent); 

        // Hapus file sementara setelah selesai
        unlink($configFile);

        // Encode CSR dalam Base64
        $csrBase64 = base64_encode($csrPem);

        return [$privateKeyContent, $csrBase64];
    }

    public function saveToFiles($privateKeyPem, $csrPem)
    {
        if (!file_exists('certificates')) {
            if (!mkdir('certificates', 0777, true)) {
                throw new Exception('Failed to create certificates directory.');
            }
        }

        file_put_contents('certificates/PrivateKey.pem', $privateKeyPem);
        file_put_contents('certificates/taxpayer.csr', $csrPem);

        echo "\nPrivate key and CSR have been saved to certificates/PrivateKey.pem and certificates/taxpayer.csr, respectively.\n";
    }
}