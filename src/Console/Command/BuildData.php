<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Console\Command;

use InvalidArgumentException;
use Punic\DataBuilder\Build;
use Punic\DataBuilder\Cldr\DraftStatus;
use Punic\DataBuilder\Console\Command;
use Punic\DataBuilder\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BuildData extends Command
{
    /**
     * @var string
     */
    public const NAME = 'data:build';

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $options = $this->container->make(Build\Options::class);
        /** @var Build\Options $options */
        $allLocalesPlaceholders = $options->getLocales()::ALL_LOCALES_PLACEHOLDER;
        $dataFormatVersionPlaceholder = $options::OUTPUTDIR_PLACEHOLDER_FORMATVERSION;
        $cldrVersionPlaceholder = $options::OUTPUTDIR_PLACEHOLDER_CLDRVERSION;

        $this
            ->setName(static::NAME)
            ->setDescription('Build the Punic data from CLDR and libphonenumber')
            ->addOption('cldr', 'c', InputOption::VALUE_REQUIRED, 'CLDR version (Examples: 31.d02  30.0.3  30  29.beta.1  25.M1  23.1.d01)', $options->getDefaultCldrVersion())
            ->addOption('libphonenumber', 'p', InputOption::VALUE_REQUIRED, 'libphonenumber version (used when parsing CLDR 34+)')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Comma-separated list of locales to be processes', [$allLocalesPlaceholders])
            ->addOption('draft-status', 'd', InputOption::VALUE_REQUIRED, 'The minimum level of the draft status of the CLDR data to be accepted (valud values: ' . implode(', ', DraftStatus::getAllStatuses()) . ')', $options->getDefaultCldrDraftStatus())
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The output directory', str_replace('/', DIRECTORY_SEPARATOR, $options->getDefaultOutputDirectory()))
            ->addOption('temp', 't', InputOption::VALUE_REQUIRED, 'The temporary directory to be used', str_replace('/', DIRECTORY_SEPARATOR, $options->getDefaultTemporaryDirectory()))
            ->addOption('state-file', 's', InputOption::VALUE_OPTIONAL, 'The path of the state file (set to an empty value to skip creating/updating the state file)', str_replace('/', DIRECTORY_SEPARATOR, $options->getDefaultStatefilePath()))
            ->addOption('docker', 'k', InputOption::VALUE_NONE, 'Use Docker to build the data')
            ->addOption('reset-cldr-data', 'x', InputOption::VALUE_NONE, 'Reset the source CLDR data before the execution')
            ->addOption('reset-punic-data', 'r', InputOption::VALUE_NONE, 'Reset the destination Punic data before the execution')
            ->addOption('pretty-output', 'u', InputOption::VALUE_NONE, 'Generated expanded (uncompressed) PHP')
            ->addOption('json-only', 'j', InputOption::VALUE_NONE, "Just build the CLDR JSON data, don't parse it")
            ->setHelp(
                <<<EOT
# DEFINING THE LOCALES TO BE PROCESSED

The --locale (-l) option defines the idenfifiers of the locales to be processed.

You can use {$allLocalesPlaceholders} (case-sensitive) to include all available locales.

To use only specific locales, you can write:
--locale=it,de
which means 'Italian and German'.

You can prepend a minus sign to substract specific locales: so for instance
--locale={$allLocalesPlaceholders},-it,-de
means 'all the locales except Italian and German'.
You can also write it in a shorte form:
--locale=-it,-de

By default, all locales are processed.

# SPECIFYING THE OUTPUT DIRECTORY

The --outpuut option accepts the following placeholders:

- {$dataFormatVersionPlaceholder} represents the version of the Punic data format
- {$cldrVersionPlaceholder} represents the CLDR version

EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = $this->getOptions($input, $output);
        if ($options === null) {
            return $this::INVALID;
        }
        return $input->getOption('docker') ? $this->launchWithDocker($input, $output, $options) : $this->launch($input, $output, $options);
    }

    protected function getOptions(InputInterface $input, OutputInterface $output): ?Build\Options
    {
        $cldrVersion = $this->getCldrVersion($input, $output);
        if ($cldrVersion === '') {
            return null;
        }
        $libPhonenumberVersion = $this->getLibphonenumberVersion($input, $output);
        if ($libPhonenumberVersion === '') {
            return null;
        }
        $locales = $this->getLocales($input, $output);
        if ($locales === null) {
            return null;
        }
        $cldrDraftStatus = $this->getCldrDraftStatus($input, $output);
        if ($cldrDraftStatus === '') {
            return null;
        }
        $outputDirectory = $this->getOutputDirectory($input, $output);
        if ($outputDirectory === '') {
            return null;
        }
        $temporaryDirectory = $this->getTemporaryDirectory($input, $output);
        if ($temporaryDirectory === '') {
            return null;
        }
        $stateFilePath = $this->getStateFilePath($input, $output);
        if ($stateFilePath === '') {
            return null;
        }
        $options = $this->container->make(Build\Options::class);
        // @var Build\Options $options
        $options
            ->setCldrVersion($cldrVersion)
           ->setLocales($locales)
            ->setCldrDraftStatus($cldrDraftStatus)
            ->setOutputDirectory($outputDirectory)
            ->setTemporaryDirectory($temporaryDirectory)
            ->setStatefilePath($stateFilePath)
            ->setResetCldrData($input->getOption('reset-cldr-data'))
            ->setResetPunicData($input->getOption('reset-punic-data'))
            ->setPrettyOutput($input->getOption('pretty-output'))
            ->setJsonOnly($input->getOption('json-only'))
        ;
        if ($libPhonenumberVersion !== null) {
            $options->setLibphonenumberVersion($libPhonenumberVersion);
        }
        $filesystem = $this->container->make(Filesystem::class);
        // @var Filesystem $filesystem
        try {
            $finalOutputDirectory = $filesystem->makePathAbsolute($options->getOutputDirectory());
        } catch (InvalidArgumentException $x) {
            $output->writeln("<error>The --output option is not valid:\n{$x->getMessage()}</error>");
            return null;
        }
        if (!is_dir($finalOutputDirectory)) {
            try {
                $filesystem->createDirectory($finalOutputDirectory, true);
            } catch (RuntimeException $x) {
                $output->writeln("<error>{$x->getMessage()}</error>");
                return null;
            }
        }
        if (!is_writable($finalOutputDirectory)) {
            $output->writeln(sprintf('<error>The output directory %s is not writable</error>', str_replace('/', DIRECTORY_SEPARATOR, $finalOutputDirectory)));
            return null;
        }
        $options->setOutputDirectory($finalOutputDirectory);

        return $options;
    }

    protected function getCldrVersion(InputInterface $input, OutputInterface $output): string
    {
        $value = trim((string) $input->getOption('cldr'));
        if ($value === '') {
            $output->writeln("<error>The --cldr option can't be empty</error>");
            return '';
        }
        if (!preg_match('/^[1-9]\d*(\.\d+)*(\.[dM]\d+|\.beta\.\d+)?$/', $value)) {
            $output->writeln("<error>{$value} is not a valid CLDR version</error>");
            return '';
        }
        return $value;
    }

    protected function getLibphonenumberVersion(InputInterface $input, OutputInterface $output): ?string
    {
        $value = $input->getOption('libphonenumber');
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            $output->writeln("<error>The --libphonenumber option can't be empty</error>");
            return '';
        }
        if (!preg_match('/^v?[1-9]\d*(\.\d+)*$/', $value)) {
            $output->writeln("<error>{$value} is not a valid libphonenumber version</error>");
            return '';
        }
        if ($value[0] !== 'v') {
            $value = "v{$value}";
        }
        return $value;
    }

    protected function getLocales(InputInterface $input, OutputInterface $output): ?Build\Options\Locales
    {
        try {
            return Build\Options\Locales::parseString(implode(',', $input->getOption('locale')));
        } catch (InvalidArgumentException $x) {
            $output->writeln("<error>Error in --locate option:\n{$x->getMessage()}</error>");
            return null;
        }
    }

    protected function getCldrDraftStatus(InputInterface $input, OutputInterface $output): string
    {
        $cldrDraftStatuses = DraftStatus::getAllStatuses();
        $cldrDraftStatus = $input->getOption('draft-status');
        if (!in_array($cldrDraftStatus, $cldrDraftStatuses, true)) {
            $output->writeln("<error>'{$cldrDraftStatus}' is not a valid value for the --draft-status option.\nValid values are:\n- " . implode("\n- ", $cldrDraftStatuses) . '</error>');
            return '';
        }
        return $cldrDraftStatus;
    }

    protected function getOutputDirectory(InputInterface $input, OutputInterface $output): string
    {
        $value = $input->getOption('output');
        $sampleValue = strtr($value, [
            Build\Options::OUTPUTDIR_PLACEHOLDER_FORMATVERSION => '1',
            Build\Options::OUTPUTDIR_PLACEHOLDER_CLDRVERSION => '1.2.3',
        ]);
        $filesystem = $this->container->make(Filesystem::class);
        // @var Filesystem $filesystem
        try {
            $filesystem->makePathAbsolute($sampleValue);
        } catch (InvalidArgumentException $x) {
            $output->writeln("<error>The --output option is not valid:\n{$x->getMessage()}</error>");
            return '';
        }

        return $value;
    }

    protected function getTemporaryDirectory(InputInterface $input, OutputInterface $output): string
    {
        $filesystem = $this->container->make(Filesystem::class);
        // @var Filesystem $filesystem
        try {
            $directory = $filesystem->makePathAbsolute($input->getOption('temp'));
        } catch (InvalidArgumentException $x) {
            $output->writeln("<error>The --state-file option is not valid:\n{$x->getMessage()}</error>");
            return '';
        }
        if (!is_dir($directory)) {
            try {
                $filesystem->createDirectory($directory, true);
            } catch (RuntimeException $x) {
                $output->writeln("<error>{$x->getMessage()}</error>");
                return '';
            }
        }
        if (!is_writable($directory)) {
            $output->writeln(sprintf('<error>The temporary directory %s is not writable</error>', str_replace('/', DIRECTORY_SEPARATOR, $directory)));
            return '';
        }

        return $directory;
    }

    protected function getStateFilePath(InputInterface $input, OutputInterface $output): ?string
    {
        $value = $input->getOption('state-file');
        if ($value === null || $value === '') {
            return null;
        }
        $filesystem = $this->container->make(Filesystem::class);
        // @var Filesystem $filesystem
        try {
            $value = $filesystem->makePathAbsolute($value);
        } catch (InvalidArgumentException $x) {
            $output->writeln("<error>The --state-file option is not valid:\n{$x->getMessage()}</error>");
            return '';
        }
        if (is_dir($value)) {
            $output->writeln(sprintf("<error>The --state-file option is not valid:\n%s is a directory</error>", str_replace('/', DIRECTORY_SEPARATOR, $value)));
            return '';
        }
        if (is_file($value)) {
            if (!is_writable($value)) {
                $output->writeln(sprintf('<error>The state file %s is not writable</error>', str_replace('/', DIRECTORY_SEPARATOR, $value)));
            }
        } else {
            $parentDirectory = dirname($value);
            if (!is_dir($parentDirectory)) {
                $output->writeln(sprintf("<error>The --state-file option is not valid:\nUnable to find the directory %s</error>", str_replace('/', DIRECTORY_SEPARATOR, $parentDirectory)));
                return '';
            }
            if (!is_writable($parentDirectory)) {
                $output->writeln(sprintf('<error>The state file directory %s is not writable</error>', str_replace('/', DIRECTORY_SEPARATOR, $parentDirectory)));
            }
        }
        return $value;
    }

    protected function launch(InputInterface $input, OutputInterface $output, Build\Options $options): int
    {
        $builder = $this->container->make(Build\Builder::class);
        // @var Build\Builder $builder
        try {
            $builder->run($options, $output);
        } catch (RuntimeException $x) {
            $output->writeln("<error>{$x->getMessage()}</error>");
            return $this::FAILURE;
        }
        return $this::SUCCESS;
    }

    protected function launchWithDocker(InputInterface $input, OutputInterface $output, Build\Options $options): int
    {
        $extraArguments = [];
        switch ($output->getVerbosity()) {
            case $output::VERBOSITY_DEBUG:
                $extraArguments[] = '-vvv';
                break;
            case $output::VERBOSITY_VERY_VERBOSE:
                $extraArguments[] = '-vvv';
                break;
            case $output::VERBOSITY_VERBOSE:
                $extraArguments[] = '-vvv';
                break;
            case $output::VERBOSITY_QUIET:
                $extraArguments[] = '-q';
                break;
        }
        if ($input->hasParameterOption(['--ansi'], true)) {
            $extraArguments[] = '--ansi';
        } elseif ($input->hasParameterOption(['--no-ansi'], true)) {
            $extraArguments[] = '--no-ansi';
        }
        $dockerBuilder = $this->container->make(Build\DockerBuilder::class);
        try {
            $dockerBuilder->run($options, $extraArguments);
        } catch (RuntimeException $x) {
            $output->writeln("<error>{$x->getMessage()}</error>");
            return $this::FAILURE;
        }

        return $this::SUCCESS;
    }
}
