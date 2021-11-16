<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;
use Punic\DataBuilder\Build\SourceData;
use Punic\DataBuilder\LocaleIdentifier;
use RuntimeException;

class Subdivisions extends Locale
{
    public function __construct()
    {
        parent::__construct('subdivisions', ['localeDisplayNames', 'subdivisions', 'subdivision'], 'subdivisions');
    }

    /**
     * {@inheritDoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::getSourceFile()
     */
    protected function getSourceFile(SourceData $sourceData, string $localeID): string
    {
        return $sourceData->getOptions()->getCldrRepositoryDirectory() . '/common/subdivisions/' . $localeID . '.xml';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter::getXmlPaths()
     */
    protected function getXmlPaths(): array
    {
        return [
            '/ldml/localeDisplayNames/subdivisions/subdivision' => ['type'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::load()
     */
    protected function load(SourceData $sourceData, string $localeID): array
    {
        $key = str_replace('_', '-', $localeID);
        $data = [$this->type => [$key => []]];
        $locale = LocaleIdentifier::fromString($localeID);
        $localeIDs = array_merge([$localeID], $locale->getParentLocaleIdentifiers(), ['en']);
        foreach (array_reverse($localeIDs) as $localeID) {
            $sourceFile = $this->getSourceFile($sourceData, $localeID);
            if (is_file($sourceFile)) {
                $data[$this->type][$key] = $this->loadXml($sourceFile, $data[$this->type][$key]);
            }
        }

        // As of CLDR 36, England, Scotland and Wales are not stored in the subdivisions/*.xml.
        $localeDisplayNamesFile = $sourceData->getOptions()->getOutputDirectoryForLocale($localeID) . '/localeDisplayNames.php';
        if (!is_file($localeDisplayNamesFile)) {
            throw new RuntimeException(sprintf("Failed to find the file %s\nPlease run the LocaleDisplayNames converter before this one", str_replace('/', DIRECTORY_SEPARATOR, $localeDisplayNamesFile)));
        }
        $localeDisplayNames = require $localeDisplayNamesFile;

        $data[$this->type][$key]['localeDisplayNames']['subdivisions']['subdivision']
            = ($localeDisplayNames['subdivisions'] ?? [])
            + $data[$this->type][$key]['localeDisplayNames']['subdivisions']['subdivision'];

        uksort($data[$this->type][$key]['localeDisplayNames']['subdivisions']['subdivision'], 'strcasecmp');

        return $data;
    }
}
