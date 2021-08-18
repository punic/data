<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;

class Calendar extends Locale
{
    public function __construct()
    {
        parent::__construct('main', ['dates', 'calendars', 'gregorian'], 'calendar');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::getSourceFileName()
     */
    protected function getSourceFileName(): string
    {
        return 'ca-gregorian.json';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process(array $data, string $localeID): array
    {
        $data = parent::process($data, $localeID);
        unset($data['dateTimeFormats']['appendItems']);
        foreach (array_keys($data['dateTimeFormats']) as $width) {
            switch ($width) {
                case 'availableFormats':
                case 'intervalFormats':
                    break;
                default:
                    $data['dateTimeFormats'][$width] = $this->toPhpSprintf($data['dateTimeFormats'][$width]);
                    break;
            }
        }
        foreach (['eraNames' => 'wide', 'eraAbbr' => 'abbreviated', 'eraNarrow' => 'narrow'] as $keyFrom => $keyTo) {
            if (array_key_exists($keyFrom, $data['eras'])) {
                $data['eras'][$keyTo] = $data['eras'][$keyFrom];
                unset($data['eras'][$keyFrom]);
            }
        }
        $data['dateTimeFormats']['intervalFormats']['intervalFormatFallback'] = $this->toPhpSprintf($data['dateTimeFormats']['intervalFormats']['intervalFormatFallback']);

        return $data;
    }
}
