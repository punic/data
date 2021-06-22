<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;
use RuntimeException;

class Numbers extends Locale
{
    public function __construct()
    {
        parent::__construct('main', ['numbers']);
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
        foreach ($data as $key => $value) {
            if (preg_match('/^([a-z]+)-numberSystem-([a-z]+)$/i', $key, $m)) {
                $keyPrefix = $m[1];
                switch ($keyPrefix) {
                    case 'symbols':
                        $final['symbols'] = $value;
                        break;
                    case 'currencyFormats':
                    case 'percentFormats':
                        $unitPattern = null;
                        foreach ($value as $k2 => $v2) {
                            if (preg_match('/^unitPattern-(.+)$/i', $k2, $m)) {
                                if ($unitPattern === null) {
                                    $unitPattern = [];
                                }
                                $unitPattern[$m[1]] = $this->toPhpSprintf($v2);
                            } elseif (in_array($k2, ['standard', 'accounting'])) {
                                $formats = explode(';', $v2);
                                if (count($formats) === 1) {
                                    $formats[] = $final['symbols']['minusSign'] . $formats[0];
                                }
                                foreach ($formats as $i => $format) {
                                    $format = preg_replace('/[0-9@#.,E+]+/', '%1$s', str_replace('%', '%%', $format));
                                    $format = str_replace(['%%', '¤'], '%2$s', $format);
                                    $final[$keyPrefix][$k2][$i === 0 ? 'positive' : 'negative'] = $format;
                                }
                            } elseif ($k2 === 'currencySpacing') {
                                foreach ($v2 as $k3 => $v3) {
                                    $final[$keyPrefix]['currencySpacing'][$k3] = [
                                        'currency' => $this->toRegularExpression('currency', $k3, $v3['currencyMatch']),
                                        'surrounding' => $this->toRegularExpression('surrounding', $k3, $v3['surroundingMatch']),
                                        'insertBetween' => $v3['insertBetween'],
                                    ];
                                }
                            }
                        }
                        if ($unitPattern !== null) {
                            $final[$keyPrefix]['unitPattern'] = $unitPattern;
                        }
                        break;
                }
            } else {
                switch ($key) {
                    case 'defaultNumberingSystem':
                    case 'defaultNumberingSystem-alt-latn':
                    case 'otherNumberingSystems':
                    case 'minimalPairs':
                        break;
                    case 'minimumGroupingDigits':
                        if (!$this->asInt($value)) {
                            throw new RuntimeException("Invalid node '{$key}'");
                        }
                        $final[$key] = $value;
                        break;
                    default:
                        throw new RuntimeException("Invalid node '{$key}'");
                }
            }
        }
        return $final;
    }

    private function toRegularExpression(string $type, string $position, string $match)
    {
        switch ($match) {
            case '[:digit:]':
                $regexp = '\pN';
                break;
            case '[:letter:]':
                $regexp = '\pL';
                break;
            case '[:^S:]':
                $regexp = '[^\pS]';
                break;
            case '[[:^S:]&[:^Z:]]':
                $regexp = '[^\pS\pZ]';
                break;
            default:
                throw new RuntimeException("Unsupported match: {$match}");
        }
        if ($type === 'currency' && $position === 'beforeCurrency') {
            $regexp = '^' . $regexp;
        } else {
            $regexp .= '$';
        }
        return '/' . $regexp . '/u';
    }
}
