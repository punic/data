<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\DataWriter;
use Punic\DataBuilder\Build\SourceData;

interface PostProcessor
{
    public function postProcess(string $localeID, string $destinationFile, SourceData $sourceData, DataWriter $dataWriter): void;
}
