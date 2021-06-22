<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental\TelephoneCode;

use Punic\DataBuilder\Build\Converter\Supplemental\TelephoneCode;
use Punic\DataBuilder\Build\SourceData;
use RuntimeException;

class Libphonenumber extends TelephoneCode
{
    /**
     * @var array
     */
    protected $territoryMap = [
        'TA' => 'SH', // Saint Helena, Ascension and Tristan da Cunha
    ];

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::load()
     */
    protected function load(SourceData $sourceData): array
    {
        $localFile = "{$sourceData->getOptions()->getTemporaryDirectory()}/PhoneNumberMetadata-{$sourceData->getOptions()->getLibphonenumberVersion()}.xml";
        $url = "https://github.com/googlei18n/libphonenumber/raw/{$sourceData->getOptions()->getLibphonenumberVersion()}/resources/PhoneNumberMetadata.xml";
        $saveFile = true;
        if (is_file($localFile)) {
            [$xmlData] = $this->silentCall(static function () use ($localFile) {
                return file_get_contents($localFile);
            });
            if ($xmlData) {
                $saveFile = false;
            } else {
                $this->silentCall(static function () use ($localFile) {
                    unlink($localFile);
                });
            }
        } else {
            $xmlData = false;
        }
        if (!$xmlData) {
            [$xmlData, $error] = $this->silentCall(static function () use ($url) {
                return file_get_contents($url);
            });
            if (!$xmlData) {
                throw new RuntimeException("Failed to download libphonenumber data from {$url}:\n{$error}");
            }
        }
        $old = libxml_use_internal_errors(true);
        libxml_clear_errors();
        [$xml] = $this->silentCall(static function () use ($xmlData) {
            return simplexml_load_string($xmlData);
        });
        if ($xml === false) {
            $msg = "Failed to parse XML from {$url}:";
            foreach (libxml_get_errors() as $error) {
                $msg .= "\n - line {$error->line}: {$error->message}";
            }
            libxml_clear_errors();
            libxml_use_internal_errors($old);
            throw new RuntimeException($msg);
        }
        libxml_use_internal_errors($old);
        if ($saveFile) {
            $this->silentCall(static function () use ($localFile, $xmlData) {
                file_put_contents($localFile, $xmlData);
            });
        }

        return ['xml' => $xml];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental\TelephoneCode::process()
     */
    protected function process(array $data): array
    {
        $xml = $data['xml'];
        /** @var \SimpleXMLElement $xml */
        $telephoneCodeData = [];
        $territories = $xml->xpath('/phoneNumberMetadata/territories/territory[@id][@countryCode]');
        foreach ($territories as $territory) {
            $territoryID = (string) $territory['id'];
            if (isset($this->territoryMap[$territoryID])) {
                $territoryID = $this->territoryMap[$territoryID];
            }
            if (!isset($telephoneCodeData[$territoryID])) {
                $telephoneCodeData[$territoryID] = [];
            }
            $phonePrefix = (string) $territory['countryCode'];
            if (!in_array($phonePrefix, $telephoneCodeData[$territoryID], true)) {
                $telephoneCodeData[$territoryID][] = $phonePrefix;
            }
        }

        return $this->sortData($telephoneCodeData);
    }
}
