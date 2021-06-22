<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use RuntimeException;

class CurrencyData extends Supplemental
{
    public function __construct()
    {
        parent::__construct('main', ['supplemental', 'currencyData']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $data = parent::process($data);
        $keys = ['fractions', 'region'];
        if ((count($data) !== count($keys)) || array_diff($keys, array_keys($data)) !== []) {
            throw new RuntimeException('Unexpected keys in currencyData.json');
        }
        $final = [];
        if (!array_key_exists('DEFAULT', $data['fractions'])) {
            throw new RuntimeException('Missing DEFAULT in currencyData.json');
        }
        $final['fractionsDefault'] = $this->parseFraction($data['fractions']['DEFAULT'], true);
        unset($data['fractions']['DEFAULT']);
        $final['fractions'] = [];
        foreach ($data['fractions'] as $currencyCode => $currencyInfo) {
            $currencyInfo = $this->parseFraction($currencyInfo, $final['fractionsDefault']);
            if (!empty($currencyInfo)) {
                $final['fractions'][$currencyCode] = $currencyInfo;
            }
        }
        $final['regions'] = [];
        foreach ($data['region'] as $territoryCode => $territoryInfos) {
            if (is_int($territoryCode)) {
                $territoryCode = substr('00' . $territoryCode, -3);
            }
            $final['regions'][$territoryCode] = [];
            foreach ($territoryInfos as $territoryInfo) {
                foreach ($territoryInfo as $currencyCode => $currencyInfo) {
                    $final['regions'][$territoryCode][] = array_merge(['currency' => $currencyCode], $this->parseRegion($currencyInfo));
                }
            }
            usort($final['regions'][$territoryCode], static function (array $a, array $b): int {
                if (array_key_exists('notTender', $a) && $a['notTender']) {
                    if (!array_key_exists('notTender', $b)) {
                        return 1;
                    }
                } elseif (array_key_exists('notTender', $b) && $b['notTender']) {
                    return -1;
                }
                if (array_key_exists('to', $a)) {
                    if (array_key_exists('to', $b)) {
                        if ($a['to'] !== $b['to']) {
                            return strcmp($b['to'], $a['to']);
                        }
                    } else {
                        return 1;
                    }
                } elseif (array_key_exists('to', $b)) {
                    return -1;
                }

                return 0;
            });
        }
        $data = $final;

        return $data;
    }

    /**
     * @param array|true $defaultValues
     *
     * @throws \RuntimeException
     */
    private function parseFraction(array $info, $defaultValues): array
    {
        $result = [];
        foreach (['_digits' => 'digits', '_rounding' => 'rounding', '_cashDigits' => 'cashDigits', '_cashRounding' => 'cashRounding'] as $keyFrom => $keyTo) {
            if (array_key_exists($keyTo, $info)) {
                throw new RuntimeException("{$keyTo} already exist in array");
            }
            if (array_key_exists($keyFrom, $info)) {
                $v = $info[$keyFrom];
                unset($info[$keyFrom]);
                switch (gettype($v)) {
                    case 'integer':
                        break;
                    case 'string':
                        if (!preg_match('/^[0-9]+$/', $v)) {
                            throw new RuntimeException("{$keyFrom} is invalid");
                        }
                        $v = (int) $v;
                        break;
                    default:
                        throw new RuntimeException("{$keyFrom} is invalid");
                }
                switch ($keyTo) {
                    case 'rounding':
                    case 'cashRounding':
                        if ($v === 0) {
                            $v = 1;
                        }
                        break;
                }
                $result[$keyTo] = $v;
            }
        }
        if (!empty($info)) {
            throw new RuntimeException('Unexpected data in currency franction');
        }
        if (array_key_exists('cashDigits', $result) && array_key_exists('digits', $result) && ($result['cashDigits'] === $result['digits'])) {
            unset($result['cashDigits']);
        }
        if (array_key_exists('cashRounding', $result) && array_key_exists('rounding', $result) && ($result['cashRounding'] === $result['rounding'])) {
            unset($result['cashRounding']);
        }
        if ($defaultValues === true) {
            if (!array_key_exists('digits', $result)) {
                throw new RuntimeException('Missing default rounding');
            }
            if (!array_key_exists('digits', $result)) {
                throw new RuntimeException('Missing default rounding');
            }
        } else {
            if (array_key_exists('digits', $result) && ($result['digits'] === $defaultValues['digits'])) {
                unset($result['digits']);
            }
            if (array_key_exists('rounding', $result) && ($result['rounding'] === $defaultValues['rounding'])) {
                unset($result['rounding']);
            }
        }

        return $result;
    }

    /**
     * @throws \RuntimeException
     */
    private function parseRegion(array $currencyInfo): array
    {
        $result = [];
        if (array_key_exists('_tender', $currencyInfo)) {
            if ($currencyInfo['_tender'] !== 'false') {
                throw new RuntimeException('Invalid _tender value');
            }
            unset($currencyInfo['_tender']);
            $result['notTender'] = true;
        }
        foreach (['_from' => 'from', '_to' => 'to'] as $keyFrom => $keyTo) {
            if (array_key_exists($keyFrom, $currencyInfo)) {
                $v = $currencyInfo[$keyFrom];
                unset($currencyInfo[$keyFrom]);
                if (!(is_string($v) && preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $v))) {
                    throw new RuntimeException("Invalid {$keyFrom} value");
                }
                $result[$keyTo] = $v;
            }
        }
        if ($currencyInfo !== []) {
            throw new RuntimeException('Unknown currency info keys found: ' . implode(', ', array_keys($currencyInfo)));
        }
        if ($result === []) {
            throw new RuntimeException('Empty currency info');
        }

        return $result;
    }
}
