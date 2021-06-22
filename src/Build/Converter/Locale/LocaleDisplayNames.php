<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;
use RuntimeException;

class LocaleDisplayNames extends Locale
{
    public function __construct()
    {
        parent::__construct('main', ['localeDisplayNames']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process(array $data, string $localeID): array
    {
        $data = parent::process($data, $localeID);
        if (!array_key_exists('localeDisplayPattern', $data)) {
            throw new RuntimeException("Missing node 'localeDisplayPattern'");
        }
        foreach (array_keys($data['localeDisplayPattern']) as $k) {
            $data['localeDisplayPattern'][$k] = $this->toPhpSprintf($data['localeDisplayPattern'][$k]);
        }
        if (!array_key_exists('codePatterns', $data)) {
            throw new RuntimeException("Missing node 'codePatterns'");
        }
        foreach (array_keys($data['codePatterns']) as $k) {
            $data['codePatterns'][$k] = $this->toPhpSprintf($data['codePatterns'][$k]);
        }

        return $data;
    }
}
