<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;
use RuntimeException;

class ListPatterns extends Locale
{
    public function __construct()
    {
        parent::__construct('main', ['listPatterns']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process($sourceData, array $data, string $localeID): array
    {
        $data = parent::process($sourceData, $data, $localeID);
        $result = [];
        $m = null;
        foreach (array_keys($data) as $patternType) {
            if (!preg_match('/^listPattern-type-(.+)$/', $patternType, $m)) {
                throw new RuntimeException("Invalid list patterns node '{$patternType}'");
            }
            $patternName = $m[1];
            $result[$patternName] = [];
            foreach ($data[$patternType] as $when => $pattern) {
                $result[$patternName][$when] = $this->toPhpSprintf($pattern);
            }
        }

        return $result;
    }
}
