<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter;

use Punic\DataBuilder\Build\Converter;
use Punic\DataBuilder\Build\SourceData;

abstract class Supplemental extends Converter
{
    public function convert(SourceData $sourceData): array
    {
        $data = $this->load($sourceData);
        return $this->process($sourceData, $data);
    }

    protected function getSourceFile(SourceData $sourceData): string
    {
        $supplementalDir = $sourceData->getOptions()->getCldrJsonDirectoryForGeneric('supplemental');
        $identifier = $this->getIdentifier();
        if ($sourceData->getOptions()->getCldrMajorVersion() >= 38) {
            switch ($identifier) {
                case 'codeMappings':
                case 'currencyData':
                case 'measurementData':
                case 'parentLocales':
                case 'territoryContainment':
                case 'territoryInfo':
                case 'timeData':
                case 'weekData':
                    return "{$supplementalDir}/supplementalData/{$identifier}.json";
                case 'dayPeriods':
                    return "{$supplementalDir}/dayPeriods/{$identifier}.json";
                case 'likelySubtags':
                    return "{$supplementalDir}/likelySubtags/{$identifier}.json";
                case 'primaryZones':
                case 'metaZones':
                    return "{$supplementalDir}/metaZones/{$identifier}.json";
                case 'ordinals':
                    return "{$supplementalDir}/ordinals/{$identifier}.json";
                case 'plurals':
                    return "{$supplementalDir}/plurals/{$identifier}.json";
            }
        }

        return "{$supplementalDir}/{$identifier}.json";
    }

    protected function load(SourceData $sourceData): array
    {
        $sourceFile = $this->getSourceFile($sourceData);

        return $this->loadJson($sourceFile);
    }

    /**
     * @return string[]
     */
    protected function getRoots(): array
    {
        return $this->roots;
    }

    protected function getUnsetByPath(): array
    {
        return [
            '/supplemental' => ['version', 'generation'],
        ];
    }

    protected function process(SourceData $sourceData, array $data): array
    {
        return $this->simplify($data, $this->getRoots(), $this->getUnsetByPath());
    }
}
