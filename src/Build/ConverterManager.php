<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

use LogicException;
use Punic\DataBuilder\Filesystem;
use Throwable;

class ConverterManager
{
    /**
     * @var \Punic\DataBuilder\Build\SourceData
     */
    protected $sourceData;

    /**
     * @var \Punic\DataBuilder\Build\DataWriter
     */
    protected $dataWriter;

    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Punic\DataBuilder\Build\Converter\Locale[]
     */
    private $localeConverters = [];

    /**
     * @var \Punic\DataBuilder\Build\Converter\Supplemental[]
     */
    private $supplementalConverters = [];

    public function __construct(SourceData $sourceData, DataWriter $dataWriter, Filesystem $filesystem, bool $registerDefaultConverters = true)
    {
        $this->sourceData = $sourceData;
        $this->dataWriter = $dataWriter;
        $this->filesystem = $filesystem;
        if ($registerDefaultConverters) {
            $this->registerDefaultConverters();
        }
    }

    public function getSourceData(): SourceData
    {
        return $this->sourceData;
    }

    /**
     * @return string[]
     */
    public function convertLocale(string $localeID): array
    {
        if ($this->getSourceData()->getOptions()->isJsonOnly()) {
            return [];
        }
        $destinationFiles = [];
        $outputDirectory = $this->getSourceData()->getOptions()->getOutputDirectoryForLocale($localeID);
        if (!is_dir($outputDirectory)) {
            $this->filesystem->createDirectory($outputDirectory, true);
        }
        $deleteOutputDirectory = true;
        try {
            foreach ($this->getLocaleConverters() as $converter) {
                $destinationFile = $outputDirectory . '/' . $converter->getIdentifier() . '.php';
                if (!is_file($destinationFile)) {
                    $data = $converter->convert($this->sourceData, $localeID);
                    $this->dataWriter->save($data, $destinationFile);
                }
                if ($converter instanceof Converter\Locale\PostProcessor) {
                    $converter->postProcess($localeID, $destinationFile, $this->sourceData, $this->dataWriter);
                }
                $destinationFiles[] = $destinationFile;
            }
            $deleteOutputDirectory = false;
        } finally {
            if ($deleteOutputDirectory) {
                try {
                    $this->filesystem->deleteDirectory($outputDirectory);
                } catch (Throwable $x) {
                }
            }
        }

        return $destinationFiles;
    }

    /**
     * @return <string[], string[]>
     */
    public function convertSupplementalFiles(): array
    {
        if ($this->getSourceData()->getOptions()->isJsonOnly()) {
            return [[], []];
        }
        $supplementalFiles = [];
        $testFiles = [];
        $outputDirectory = $this->getSourceData()->getOptions()->getOutputDirectory();
        foreach ($this->getSupplementalConverters() as $converter) {
            $destinationFile = "{$outputDirectory}/{$converter->getIdentifier()}.php";
            $supplementalFiles[] = $destinationFile;
            if (!is_file($destinationFile)) {
                $data = $converter->convert($this->sourceData);
                $this->dataWriter->save($data, $destinationFile);
            }
            if ($converter instanceof Converter\Supplemental\TestDataProcessor && $converter->shouldConvertTestData()) {
                $destinationFile = "{$outputDirectory}/{$converter->getTestFilename()}";
                $testFiles[] = $destinationFile;
                if (!is_file($destinationFile)) {
                    $data = $converter->convertTestData($this->sourceData);
                    $this->dataWriter->save($data, $destinationFile);
                }
            }
        }

        return [$supplementalFiles, $testFiles];
    }

    /**
     * @return \Punic\DataBuilder\Build\Converter\Locale[]
     */
    protected function getLocaleConverters(): array
    {
        return $this->localeConverters;
    }

    /**
     * @return \Punic\DataBuilder\Build\Converter\Supplemental[]
     */
    protected function getSupplementalConverters(): array
    {
        return $this->supplementalConverters;
    }

    protected function resetConverters(): void
    {
        $this->localeConverters = [];
        $this->supplementalConverters = [];
    }

    protected function registerDefaultConverters(): void
    {
        // Locale
        $this->registerConverters([
            new Converter\Locale\Calendar(),
            new Converter\Locale\TimeZoneNames(),
            new Converter\Locale\ListPatterns(),
            new Converter\Locale\Units(),
            new Converter\Locale\Noop(['dates', 'fields'], 'dateFields'),
            new Converter\Locale\Languages(),
            new Converter\Locale\Noop(['localeDisplayNames', 'territories']),
            new Converter\Locale\LocaleDisplayNames(),
            new Converter\Locale\Numbers(),
            new Converter\Locale\Noop(['layout', 'orientation'], 'layout'),
            new Converter\Locale\Noop(['localeDisplayNames', 'measurementSystemNames']),
            new Converter\Locale\Currencies(),
            new Converter\Locale\Rbnf(),
            new Converter\Locale\Scripts(),
        ]);
        if ($this->sourceData->getOptions()->getCldrMajorVersion() >= 32) {
            $this->registerConverter(new Converter\Locale\Subdivisions());
        }
        // Supplemental
        $this->registerConverters([
            new Converter\Supplemental\TerritoryInfo(),
            new Converter\Supplemental\TimeData(),
            new Converter\Supplemental\DayPeriods(),
            new Converter\Supplemental\WeekData(),
            new Converter\Supplemental\Noop(['supplemental', 'parentLocales', 'parentLocale'], 'parentLocales'),
            new Converter\Supplemental\Noop(['supplemental', 'likelySubtags']),
            new Converter\Supplemental\TerritoryContainment(),
            new Converter\Supplemental\MetaZones(),
            new Converter\Supplemental\Noop(['supplemental', 'primaryZones']),
            new Converter\Supplemental\Plurals('cardinal', 'plurals'),
            new Converter\Supplemental\Plurals('ordinal', 'ordinals'),
            new Converter\Supplemental\MeasurementData(),
            new Converter\Supplemental\CurrencyData(),
        ]);
        if ($this->sourceData->getOptions()->getCldrMajorVersion() >= 32) {
            $this->registerConverters([
                new Converter\Supplemental\Subdivisions(),
                new Converter\Supplemental\TimeZone(),
                new Converter\Supplemental\CodeMappings(),
            ]);
        }
        if ($this->sourceData->getOptions()->shouldUseLibphonenumber()) {
            $this->registerConverter(new Converter\Supplemental\TelephoneCode\Libphonenumber());
        } else {
            $this->registerConverter(new Converter\Supplemental\TelephoneCode());
        }
    }

    /**
     * @param \Punic\DataBuilder\Build\Converter[] $converters
     */
    protected function registerConverters(array $converters): void
    {
        foreach ($converters as $converter) {
            $this->registerConverter($converter);
        }
    }

    /**
     * @throws \LogicException
     */
    protected function registerConverter(Converter $converter): void
    {
        if ($converter instanceof Converter\Locale) {
            $this->localeConverters[] = $converter;
        } elseif ($converter instanceof Converter\Supplemental) {
            $this->supplementalConverters[] = $converter;
        } else {
            throw new LogicException('Unrecognized converter: ' . get_class($converter));
        }
    }
}
