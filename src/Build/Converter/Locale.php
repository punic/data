<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter;

use Punic\DataBuilder\Build\Converter;
use Punic\DataBuilder\Build\SourceData;
use RuntimeException;

abstract class Locale extends Converter
{
    /**
     * @throws \RuntimeException
     */
    public function convert(SourceData $sourceData, string $localeID): array
    {
        $data = $this->load($sourceData, $localeID);
        return $this->process($sourceData, $data, $localeID);
    }

    protected function getSourceFileName(): string
    {
        return $this->getIdentifier() . '.json';
    }

    /**
     * @throws \RuntimeException
     */
    protected function getSourceFile(SourceData $sourceData, string $localeID): string
    {
        $name = $this->getSourceFileName();
        $file = $sourceData->getOptions()->getCldrJsonDirectoryForLocale($localeID) . '/' . $name;
        if (is_file($file)) {
            return $file;
        }
        /*
        $fallbackFile = $sourceData->getOptions()->getCldrJsonDirectoryForLocale('en') . '/' . $name;
        if (is_file($fallbackFile)) {
            return $fallbackFile;
        }
        */
        throw new RuntimeException("File not found: {$file}");
    }

    protected function load(SourceData $sourceData, string $localeID): array
    {
        $sourceFile = $this->getSourceFile($sourceData, $localeID);

        return $this->loadJson($sourceFile);
    }

    /**
     * @return string[]
     */
    protected function getRoots(string $localeID): array
    {
        return array_merge(
            [$this->type, str_replace('_', '-', $localeID)],
            $this->roots
        );
    }

    /**
     * @return string[]
     */
    protected function getUnsetByPath(string $localeID): array
    {
        return [
            '/' . $this->type . '/' . str_replace('_', '-', $localeID) => ['identity'],
        ];
    }

    /**
     * @throws \RuntimeException
     */
    protected function process(SourceData $sourceData, array $data, string $localeID): array
    {
        return $this->simplify($data, $this->getRoots($localeID), $this->getUnsetByPath($localeID));
    }
}
