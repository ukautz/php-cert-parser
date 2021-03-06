<?php

/**
 * Copyright 2013 François Kooman <fkooman@tuxed.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\X509;

class CertParser
{

    private $_strippedCert;
    private $_parsedCert;

    /**
     * Construct the CertParser object.
     *
     * @param certData the PEM or base64 encoded DER certificate data
     */
    public function __construct($certData)
    {
        if (!is_string($certData)) {
            throw new CertParserException("input should be string");
        }

        // strip header and footer from PEM if present
        $certData = preg_replace('/\-+BEGIN CERTIFICATE\-+/', '', $certData);
        $certData = preg_replace('/\-+END CERTIFICATE\-+/', '', $certData);

        // create one long string of the certificate
        $replaceCharacters = array(" ", "\t", "\n", "\r", "\0" , "\x0B");
        $certData = str_replace($replaceCharacters, '', $certData);

        // store this stripped certificate
        $this->_strippedCert = $certData;

        // parse the certificate using OpenSSL
        if (!function_exists("openssl_x509_parse")) {
            throw new CertParserException("php openssl extension not available");
        }

        $c = openssl_x509_parse($this->toPEM());
        if (FALSE === $c) {
            throw new CertParserException("unable to parse the certificate");
        }

        $this->_parsedCert = $c;
    }

    public function toBase64()
    {
        return $this->_strippedCert;
    }

    public function toDer()
    {
        return base64_decode($this->toBase64());
    }

    public function toPem()
    {
        // prepend header and append footer
        return "-----BEGIN CERTIFICATE-----" . PHP_EOL . wordwrap($this->toBase64(), 64, "\n", TRUE) . PHP_EOL . "-----END CERTIFICATE-----" . PHP_EOL;
    }

    public static function fromFile($fileName)
    {
        $fileData = @file_get_contents($fileName);
        if (FALSE === $fileData) {
            throw new CertParserException("unable to read file");
        }

        return new static($fileData);
    }

    /**
     * Get the UNIX timestamp of when this certificate is valid.
     */
    public function getNotValidBefore()
    {
        if (!array_key_exists("validFrom_time_t", $this->_parsedCert)) {
            throw new CertParserException("could not find 'validFrom_time_t' key");
        }

        return $this->_parsedCert['validFrom_time_t'];
    }

    /**
     * Get the UNIX timestamp of when this certificate is no longer valid.
     */
    public function getNotValidAfter()
    {
        if (!array_key_exists("validTo_time_t", $this->_parsedCert)) {
            throw new CertParserException("could not find 'validTo_time_t' key");
        }

        return $this->_parsedCert['validTo_time_t'];
    }

    public function getFingerprint($algorithm = "sha1")
    {
        if (!in_array($algorithm, hash_algos())) {
            throw new CertParserException(sprintf("unsupported algorithm '%s'", $algorithm));
        }

        return hash($algorithm, $this->toDer());
    }

    /**
     * Get the common name
     */
    public function getName()
    {
        if (!array_key_exists("name", $this->_parsedCert)) {
            throw new CertParserException("could not find 'name' key");
        }

        return $this->_parsedCert['name'];
    }

}
