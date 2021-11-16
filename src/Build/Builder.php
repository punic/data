<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

use Closure;
use Collator;
use DOMDocument;
use Punic\DataBuilder\Console\Command\CreateMvnSettings;
use Punic\DataBuilder\Environment;
use Punic\DataBuilder\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Builder
{
    /**
     * @var \Punic\DataBuilder\Environment
     */
    protected $environment;

    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Punic\DataBuilder\Build\SourceData\Factory
     */
    protected $sourceDataFactory;

    /**
     * @var \Punic\DataBuilder\Build\ConverterManager\Factory
     */
    protected $converterManagerFactory;

    /**
     * @var \Punic\DataBuilder\Build\StateWriter\Factory
     */
    protected $stateWriterFactory;

    public function __construct(Environment $environment, Filesystem $filesystem, SourceData\Factory $sourceDataFactory, ConverterManager\Factory $converterManagerFactory, StateWriter\Factory $stateWriterFactory)
    {
        $this->environment = $environment;
        $this->filesystem = $filesystem;
        $this->sourceDataFactory = $sourceDataFactory;
        $this->converterManagerFactory = $converterManagerFactory;
        $this->stateWriterFactory = $stateWriterFactory;
    }

    /**
     * @throws \RuntimeException
     */
    public function run(Options $options, ?OutputInterface $output = null): void
    {
        if ($output === null) {
            $output = new NullOutput();
        }
        $sourceData = $this->sourceDataFactory->createSourceData($options);
        $converterManager = $this->converterManagerFactory->createConverterManager($sourceData);
        $this->checkEnvironment($sourceData);
        $this->printOptions($sourceData, $output);
        $this->initializeCldrRepository($sourceData, $output);
        $localeIDs = $sourceData->getFinalLocaleIDs();
        if ($localeIDs === []) {
            throw new RuntimeException('No locale will be generated');
        }
        $this->configureCldrRepository($sourceData, $output);
        $this->prepareGenericCldrFiles($sourceData, $output);
        if (!$options->isJsonOnly()) {
            $this->initializePunicData($sourceData->getOptions(), $output);
        }
        $localeFiles = $this->convertLocales($localeIDs, $converterManager, $output);
        [$supplementalFiles, $testFiles] = $this->convertSupplementalFiles($converterManager, $output);
        if (!$sourceData->getOptions()->isJsonOnly()) {
            if ($sourceData->getOptions()->getStatefilePath() !== null) {
                $this->writeStateFile($sourceData, $localeIDs, ['localeFiles' => $localeFiles, 'supplementalFiles' => $supplementalFiles, 'testFiles' => $testFiles], $output);
            }
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function checkEnvironment(SourceData $sourceData): void
    {
        $errors = [];
        if (!class_exists(Collator::class)) {
            $errors[] = 'Missing PHP extension: intl';
        }
        if (!class_exists(DOMDocument::class)) {
            $errors[] = 'Missing PHP extension: dom';
        }
        $output = [];
        $rc = -1;
        exec('git --version 2>&1', $output, $rc);
        if ($rc !== 0) {
            $errors[] = 'Missing command: git';
        }
        $output = [];
        $rc = -1;
        exec('java -version 2>&1', $output, $rc);
        if ($rc !== 0) {
            $errors[] = 'Missing command: java';
        }
        if ($sourceData->getOptions()->getCldrMajorVersion() >= 38) {
            $output = [];
            $rc = -1;
            exec('mvn -version 2>&1', $output, $rc);
            if ($rc !== 0) {
                $errors[] = 'Missing command: mvn';
            }
            if (!is_file($this->environment->getMvnSettingsFilePath())) {
                $errors[] = sprintf('Unable to find the Maven settings file. You can create is by using the %s command.', CreateMvnSettings::NAME);
            }
        } else {
            $output = [];
            $rc = -1;
            exec('ant -version 2>&1', $output, $rc);
            if ($rc !== 0) {
                $errors[] = 'Missing command: ant';
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }
    }

    protected function printOptions(SourceData $sourceData, OutputInterface $output): void
    {
        if ($output->isQuiet()) {
            return;
        }
        $options = $sourceData->getOptions();
        $lines = [];
        $lines[] = ['Punic data format version', $options->getDataFormatVersion()];
        $lines[] = ['Processing CLDR version', $options->getCldrVersion()];
        if ($options->shouldUseLibphonenumber()) {
            $lines[] = ['Libphonenumber version', $options->getLibphonenumberVersion()];
        }
        $lines[] = ['CLDR draft status', $options->getCldrDraftStatus()];
        $lines[] = ['Processing locales', (string) $options->getLocales()];
        $lines[] = ['Output directory', str_replace('/', DIRECTORY_SEPARATOR, $options->getOutputDirectory())];
        $lines[] = ['Temporary directory', str_replace('/', DIRECTORY_SEPARATOR, $options->getTemporaryDirectory())];
        $lines[] = ['State file', $options->getStatefilePath() === null ? '<not updated>' : str_replace('/', DIRECTORY_SEPARATOR, $options->getStatefilePath())];
        $maxHeaderLength = 0;
        foreach ($lines as $line) {
            $maxHeaderLength = max($maxHeaderLength, mb_strlen($line[0]));
        }
        foreach ($lines as [$header, $value]) {
            $output->writeln(sprintf("%-{$maxHeaderLength}s: %s", $header, $value));
        }
    }

    protected function callVerbose(OutputInterface $output, string $message, Closure $callback)
    {
        if ($output->isVerbose()) {
            $output->writeln("### {$message}");
            $callback(true);
        } else {
            $output->write("{$message}... ");
            $callback(false);
            $output->writeln('<info>done.</info>');
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function initializeCldrRepository(SourceData $sourceData, OutputInterface $output): void
    {
        if ($sourceData->getOptions()->isResetCldrData() && $sourceData->isCldrRepositoryPresent()) {
            $output->write('Deleting the CLDR repository... ');
            $sourceData->deleteCldrRepository();
            $output->writeln('<info>done.</info>');
        }
        if (!$sourceData->isCldrRepositoryPresent()) {
            $this->callVerbose($output, 'Cloning the CLDR repository', static function (bool $verbose) use ($sourceData) {
                $sourceData->ensureCldrRepositoryPresent($verbose);
            });
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function configureCldrRepository(SourceData $sourceData, OutputInterface $output): void
    {
        if (!$sourceData->isCldrJarPresent()) {
            $this->callVerbose($output, 'Creating the CLDR jar file', static function (bool $verbose) use ($sourceData): void {
                $sourceData->ensureCldrJarPresent($verbose);
            });
        }
        if ($sourceData->getOptions()->isResetCldrData() && $sourceData->isCldrJsonContainerPresent()) {
            $output->write('Deleting the CLDR JSON directory... ');
            $sourceData->deleteCldrJsonContainer();
            $output->writeln('<info>done.</info>');
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function prepareGenericCldrFiles(SourceData $sourceData, OutputInterface $output): void
    {
        $builder = function (string $section) use ($sourceData, $output): void {
            if ($sourceData->isCldrJsonGenericPresent($section)) {
                return;
            }
            $this->callVerbose($output, "Creating the {$section} JSON files", static function (bool $verbose) use ($sourceData, $section): void {
                $sourceData->ensureCldrJsonGeneric($section, $verbose);
            });
        };
        $builder('supplemental');
        $builder('segments');
        $builder('rbnf');
        if (!$sourceData->isCldrJsonLocalePresent('en')) {
            $this->callVerbose($output, 'Building source JSON data for English', static function (bool $verbose) use ($sourceData): void {
                $sourceData->ensureCldrJsonLocale('en', $verbose);
            });
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function initializePunicData(Options $options, OutputInterface $output): void
    {
        $outputDirectory = $options->getOutputDirectory();
        if (is_dir($outputDirectory) && $options->isResetPunicData()) {
            $output->write('Clearing the current Punic data... ');
            $this->filesystem->deleteDirectory($outputDirectory);
            $output->writeln('<info>done.</info>');
        }
    }

    /**
     * @param string[] $localeIDs
     *
     * @return string[]
     */
    protected function convertLocales(array $localeIDs, ConverterManager $converterManager, OutputInterface $output): array
    {
        $progress = new ProgressBar($output, count($localeIDs));
        $progress->setMessage('');
        if (!$output->isQuiet()) {
            $progress->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% -- %message%');
        }
        $progress->start();
        $uniqueLocaleFiles = [];
        foreach ($localeIDs as $localeID) {
            $progress->advance();
            $localeFiles = $this->convertLocale($localeID, $converterManager, $progress);
            $uniqueLocaleFiles = array_unique(
                array_merge(
                    $uniqueLocaleFiles,
                    array_map('basename', $localeFiles)
                )
            );
        }
        sort($uniqueLocaleFiles);

        return $uniqueLocaleFiles;
    }

    /**
     * @return string[]
     */
    protected function convertLocale(string $localeID, ConverterManager $converterManager, ProgressBar $progress): array
    {
        $message = $progress->getMessage();
        $baseMessage = $localeID;
        $progress->setMessage($baseMessage);
        if (!$converterManager->getSourceData()->isCldrJsonLocalePresent($localeID)) {
            $progress->setMessage("{$baseMessage}: building source JSON data");
            $progress->display();
            $converterManager->getSourceData()->ensureCldrJsonLocale($localeID);
            $progress->setMessage($baseMessage);
            $progress->display();
        }
        if ($converterManager->getSourceData()->getOptions()->isJsonOnly()) {
            return [];
        }
        $progress->setMessage("{$baseMessage}: converting");
        $progress->display();
        $localeFiles = $converterManager->convertLocale($localeID);
        $message = $progress->setMessage($message);
        $progress->display();

        return $localeFiles;
    }

    protected function convertSupplementalFiles(ConverterManager $converterManager, OutputInterface $output): array
    {
        $output->write('Converting supplemental files... ');
        [$supplementalFiles, $testFiles] = $converterManager->convertSupplementalFiles();
        $output->writeln('<info>done.</info>');

        $uniqueSupplementalFiles = array_unique(array_map('basename', $supplementalFiles));
        sort($uniqueSupplementalFiles);
        $uniqueTestFiles = array_unique(array_map('basename', $testFiles));
        sort($uniqueTestFiles);

        return [$uniqueSupplementalFiles, $uniqueTestFiles];
    }

    /**
     * @param string[] $localeIDs
     */
    protected function writeStateFile(SourceData $sourceData, array $localeIDs, array $files, OutputInterface $output): void
    {
        $output->write('Writing state file... ');
        $stateWriter = $this->stateWriterFactory->createStateWriter($sourceData);
        $stateWriter->save($sourceData, $localeIDs, $files);
        $output->writeln('<info>done.</info>');
    }
}
