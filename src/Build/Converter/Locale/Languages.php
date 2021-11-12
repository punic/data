<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Collator;
use Punic\DataBuilder\Build\Converter\Locale;
use Punic\DataBuilder\Build\SourceData;

class Languages extends Locale
{
    public function __construct()
    {
        parent::__construct('main', ['localeDisplayNames', 'languages']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process(SourceData $sourceData, array $data, string $localeID): array
    {
        $languages = parent::process($sourceData, $data, $localeID);

        $collator = new Collator($localeID);
        $collator->asort($languages, Collator::SORT_STRING);

        return $languages;
    }
}
