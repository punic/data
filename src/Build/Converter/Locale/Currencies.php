<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Collator;
use Punic\DataBuilder\Build\Converter\Locale;
use Punic\DataBuilder\Build\DataWriter;
use Punic\DataBuilder\Build\SourceData;
use RuntimeException;

class Currencies extends Locale implements PostProcessor
{
    public function __construct()
    {
        parent::__construct('main', ['numbers', 'currencies']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale\PostProcessor::postProcess()
     */
    public function postProcess(string $localeID, string $destinationFile, SourceData $sourceData, DataWriter $dataWriter): void
    {
        if ($localeID === 'en') {
            return;
        }
        if (!$dataWriter instanceof DataWriter\Php) {
            throw new RuntimeException();
        }
        $referenceData = $this->convert($sourceData, 'en');
        $destinationData = include $destinationFile;
        $someChanged = false;
        foreach ($referenceData as $currency => $currencyInfo) {
            if (!array_key_exists($currency, $destinationData)) {
                $someChanged = true;
                $destinationData[$currency] = $currencyInfo;
            }
        }
        if ($someChanged) {
            $dataWriter->save($destinationData, $destinationFile);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process(array $data, string $localeID): array
    {
        $data = parent::process($data, $localeID);
        $final = [];
        $m = null;
        foreach ($data as $currencyCode => $currencyInfo) {
            if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
                throw new RuntimeException("Invalid currency code: {$currencyCode}");
            }
            if (array_key_exists('symbol', $currencyInfo) && (strcmp($currencyInfo['symbol'], $currencyCode) === 0)) {
                unset($currencyInfo['symbol']);
            }
            foreach ($currencyInfo as $currencyInfoKey => $currencyInfoValue) {
                switch ($currencyInfoKey) {
                    case 'displayName':
                        unset($currencyInfo[$currencyInfoKey]);
                        $currencyInfo['name'] = $currencyInfoValue;
                        break;
                    case 'symbol-alt-variant':
                        if ($currencyInfoValue !== $currencyCode) {
                            $currencyInfo['symbolAlt'] = $currencyInfoValue;
                        }
                        unset($currencyInfo[$currencyInfoKey]);
                        break;
                    case 'symbol-alt-narrow':
                        if ($currencyInfoValue !== $currencyCode) {
                            $currencyInfo['symbolNarrow'] = $currencyInfoValue;
                        }
                        unset($currencyInfo[$currencyInfoKey]);
                        break;
                    default:
                        if (preg_match('/^displayName-count-(.+)$/', $currencyInfoKey, $m)) {
                            if (!array_key_exists('pluralName', $currencyInfo)) {
                                $currencyInfo['pluralName'] = [];
                            }
                            $currencyInfo['pluralName'][$m[1]] = $currencyInfoValue;
                            unset($currencyInfo[$currencyInfoKey]);
                        }
                        break;
                }
            }
            if (array_key_exists('pluralName', $currencyInfo)) {
                if (!array_key_exists('other', $currencyInfo['pluralName'])) {
                    throw new RuntimeException("Missing 'other' plural rule for currency {$currencyCode}");
                }
                if (!array_key_exists('name', $currencyInfo)) {
                    if (array_key_exists('one', $currencyInfo['pluralName'])) {
                        $currencyInfo['name'] = $currencyInfo['pluralName']['one'];
                    } else {
                        $currencyInfo['name'] = $currencyInfo['pluralName']['other'];
                    }
                }
            }
            if (!array_key_exists('name', $currencyInfo)) {
                $currencyInfo['name'] = $currencyCode;
            }
            if (array_key_exists('pluralName', $currencyInfo)) {
                if ((count($currencyInfo['pluralName']) === 1) && (strcmp($currencyInfo['pluralName']['other'], $currencyInfo['name']) === 0)) {
                    unset($currencyInfo['pluralName']);
                }
            }
            $final[$currencyCode] = $currencyInfo;
        }
        $data = $final;

        $collator = new Collator($localeID);
        uasort($data, static function (array $a, array $b) use ($collator): int {
            $ab = [$a['name'], $b['name']];
            $collator->sort($ab);
            $i = array_search($a['name'], $ab, true);
            if ($i === 1) {
                return 1;
            }
            $i = array_search($b['name'], $ab, true);
            if ($i === 1) {
                return -1;
            }

            return 0;
        });

        return $data;
    }
}
