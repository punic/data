<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use Punic\DataBuilder\Build\SourceData;
use RuntimeException;

class TelephoneCode extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'telephoneCodeData']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(SourceData $sourceData, array $data): array
    {
        $data = parent::process($sourceData, $data);
        foreach (array_keys($data) as $k) {
            if (!preg_match('/^([A-Z]{2}|[0-9]{3})$/', $k)) {
                throw new RuntimeException("Invalid territory ID: {$k}");
            }
            $d = $data[$k];
            if (!is_array($d) || $d === []) {
                throw new RuntimeException("Expecting non empty array for {$k}, found " . gettype($d));
            }
            $data[$k] = [];
            $n = count($d);
            for ($i = 0; $i < $n; $i++) {
                if (!isset($d[$i])) {
                    throw new RuntimeException("Invalid array for {$k}");
                }
                if (!is_array($d[$i]) || count($d[$i]) !== 1 || !is_string($d[$i]['telephoneCountryCode'] ?? null) || $d[$i]['telephoneCountryCode'] === '') {
                    throw new RuntimeException("Invalid telephoneCountryCode for {$k}");
                }
                $data[$k][] = $d[$i]['telephoneCountryCode'];
            }
            sort($data[$k]);
        }

        return $this->sortData($data);
    }

    protected function sortData(array $data): array
    {
        foreach (array_keys($data) as $k) {
            sort($data[$k]);
        }
        uksort($data, static function ($a, $b) {
            if (is_numeric($a)) {
                if (is_numeric($b)) {
                    return (int) $a - (int) $b;
                }
                return -1;
            }
            if (is_numeric($b)) {
                return 1;
            }

            return strcasecmp($a, $b);
        });

        return $data;
    }
}
