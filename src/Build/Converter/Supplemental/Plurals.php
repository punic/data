<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use Punic\DataBuilder\Build\SourceData;
use RuntimeException;

class Plurals extends Supplemental implements TestDataProcessor
{
    public function __construct(string $type, ?string $identifier = null)
    {
        parent::__construct('supplemental', ['supplemental', 'plurals-type-' . $type], $identifier);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental\TestDataProcessor::getTestFilename()
     */
    public function getTestFilename(): string
    {
        return '__test.plurals.php';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental\TestDataProcessor::shouldConvertTestData()
     */
    public function shouldConvertTestData(): bool
    {
        return $this->getIdentifier() === 'plurals';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental\TestDataProcessor::convertTestData()
     */
    public function convertTestData(SourceData $sourceData): array
    {
        $data = $this->load($sourceData);
        $dt = $this->realProcess($sourceData, $data);

        return $dt[1];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(SourceData $sourceData, array $data): array
    {
        $dt = $this->realProcess($sourceData, $data);

        return $dt[0];
    }

    /**
     * @return array
     */
    private function realProcess(SourceData $sourceData, array $data)
    {
        $data = parent::process($sourceData, $data);
        $testData = [];
        $m = null;
        foreach ($data as $l => $lData) {
            $testData[$l] = [];
            $keys = array_keys($lData);
            foreach ($keys as $key) {
                if (!preg_match('/^pluralRule-count-(.+)$/', $key, $m)) {
                    throw new RuntimeException("Invalid node '{$key}'");
                }
                $rule = $m[1];
                $testData[$l][$rule] = [];
                $vOriginal = $lData[$key];
                $examples = explode('@', $vOriginal);
                $v = trim(array_shift($examples));
                foreach ($examples as $example) {
                    list($exampleNumberType, $exampleValues) = explode(' ', $example, 2);
                    switch ($exampleNumberType) {
                        case 'integer':
                        case 'decimal':
                            $exampleValues = preg_replace('/, …$/', '', $exampleValues);
                            $exampleValuesParsed = [];
                            foreach (explode(', ', trim($exampleValues)) as $ev) {
                                if (preg_match('/^[+\-]?\d+$/', $ev)) {
                                    $exampleValuesParsed[] = $ev;
                                    $exampleValuesParsed[] = (int) $ev;
                                } elseif (preg_match('/^[+\-]?\d+\.\d+$/', $ev)) {
                                    $exampleValuesParsed[] = $ev;
                                } elseif (preg_match('/^([+\-]?\d(\.\d+)?)c([+\-]?\d+)$/', $ev, $m)) {
                                    // $f = $m[1] * pow(10, $m[3]);
                                    // @todo
                                } elseif (preg_match('/^([+\-]?\d+)~([+\-]?\d+)$/', $ev, $m)) {
                                    $exampleValuesParsed[] = $m[1];
                                    $exampleValuesParsed[] = (int) $m[1];
                                    $exampleValuesParsed[] = $m[2];
                                    $exampleValuesParsed[] = (int) $m[2];
                                } elseif (preg_match('/^([+\-]?\d+(\.\d+)?)~([+\-]?\d+(\.\d+)?)$/', $ev, $m)) {
                                    $exampleValuesParsed[] = $m[1];
                                    $exampleValuesParsed[] = $m[3];
                                } elseif (preg_match('/^(\d+(?:\.\d+)?)e(\d+)$/', $ev, $m)) {
                                    $exampleValuesParsed[] = $ev;
                                } elseif ($ev !== '…') {
                                    throw new RuntimeException("Invalid node '{$key}': {$vOriginal}");
                                }
                            }
                            $testData[$l][$rule] = $exampleValuesParsed;
                            break;
                        default:
                            throw new RuntimeException("Invalid node '{$key}': {$vOriginal}");
                    }
                }
                if ($rule === 'other') {
                    if ($v !== '') {
                        throw new RuntimeException("Invalid node '{$key}': {$vOriginal}");
                    }
                } else {
                    $v = preg_replace('/ ?!= ?/', ' != ', $v);
                    $v = preg_replace('/([^!]) ?= ?/', '\1 == ', $v);
                    $v = preg_replace('/ ?% ?/', ' % ', $v);
                    $map = [' == ' => 'true', ' != ' => 'false'];
                    $startPattern = '^| and | or ';
                    $leftPattern = '[nivwftce](?: % \d+)?';
                    $operatorPattern = ' == | != ';
                    $rightPattern = '\d+(?:(?:\.\.|,)\d+)+';
                    while (preg_match("/(?:{$startPattern})(({$leftPattern})({$operatorPattern})({$rightPattern}))/", $v, $m)) {
                        $found = $m[1];
                        $left = $m[2]; // eg 'n % 10'
                        $operator = $m[3]; // eg '=='
                        $right = $m[4];
                        $ranges = explode(',', $right);
                        foreach (array_keys($ranges) as $j) {
                            if (preg_match('/^(\\d+)\\.\\.(\\d+)$/', $ranges[$j], $m)) {
                                $ranges[$j] = "array({$m[1]}, {$m[2]})";
                            }
                        }
                        $replace = "static::inRange({$left}, {$map[$operator]}, " . implode(', ', $ranges) . ')';
                        $v = str_replace($found, $replace, $v);
                    }
                    if (strpos($v, '..') !== false) {
                        throw new RuntimeException("Invalid node '{$key}': {$vOriginal} (not converted part: \"{$v}\"");
                    }
                    foreach ([
                        'n' => '%1$s', // absolute value of the source number (integer and decimals).
                        'i' => '%2$s', // integer digits of n
                        'v' => '%3$s', // number of visible fraction digits in n, with trailing zeros.
                        'w' => '%4$s', // number of visible fraction digits in n, without trailing zeros.
                        'f' => '%5$s', // visible fractional digits in n, with trailing zeros.
                        't' => '%6$s', // visible fractional digits in n, without trailing zeros.
                        'c' => '%7$s', // compact decimal exponent value: exponent of the power of 10 used in compact decimal formatting.
                        'e' => '%7$s', // currently, synonym for ‘c’. however, may be redefined in the future.
                    ] as $from => $to) {
                        $v = preg_replace('/^' . $from . ' /', "{$to} ", $v);
                        $v = preg_replace("/^{$from} /", "{$to} ", $v);
                        $v = str_replace(" {$from} ", " {$to} ", $v);
                        $v = str_replace("({$from}, ", "({$to}, ", $v);
                        $v = str_replace("({$from} ", "({$to} ", $v);
                        $v = str_replace(" {$from},", " {$to},", $v);
                    }
                    $v = str_replace(' % ', ' %% ', $v);
                    $lData[$rule] = $v;
                }
                unset($lData[$key]);
            }
            $data[$l] = $lData;
        }

        return [$data, $testData];
    }
}
