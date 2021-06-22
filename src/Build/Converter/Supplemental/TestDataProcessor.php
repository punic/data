<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\SourceData;

interface TestDataProcessor
{
    public function shouldConvertTestData(): bool;

    public function getTestFilename(): string;

    public function convertTestData(SourceData $sourceData): array;
}
