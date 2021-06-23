<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

use Punic\DataBuilder\Environment;
use Punic\DataBuilder\Filesystem;
use Punic\DataBuilder\LocaleIdentifier;
use Punic\DataBuilder\Traits;
use RuntimeException;
use Throwable;

class SourceData
{
    use Traits\Shell;

    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Punic\DataBuilder\Environment
     */
    protected $environment;

    /**
     * @var \Punic\DataBuilder\Build\Options
     */
    private $options;

    /**
     * @var string[]|null
     */
    private $availableLocales;

    public function __construct(Options $options, Filesystem $filesystem, Environment $environment)
    {
        $this->options = $options;
        $this->filesystem = $filesystem;
        $this->environment = $environment;
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * There's a local clone of the repository?
     */
    public function isCldrRepositoryPresent(): bool
    {
        return is_dir($this->getOptions()->getCldrRepositoryDirectory());
    }

    /**
     * Delete the local clone of the repository.
     *
     * @throws \RuntimeException
     */
    public function deleteCldrRepository(): void
    {
        if ($this->isCldrRepositoryPresent()) {
            $this->filesystem->deleteDirectory($this->getOptions()->getCldrRepositoryDirectory());
        }
        $this->availableLocales = null;
    }

    /**
     * Be sure that there's a local clone of the repository.
     *
     * @throws \RuntimeException
     */
    public function ensureCldrRepositoryPresent(bool $passthru = false): void
    {
        if ($this->isCldrRepositoryPresent()) {
            return;
        }
        $dir = $this->getOptions()->getCldrRepositoryDirectory();
        $this->filesystem->createDirectory($dir, true);
        $this->filesystem->deleteDirectory($dir);
        $deleteDirectory = true;
        try {
            $tag = 'release-' . str_replace('.', '-', $this->getOptions()->getCldrVersion());
            $this->shell(
                'git',
                [
                    'clone',
                    '--single-branch',
                    '--depth=1',
                    "--branch={$tag}",
                    '--',
                    'https://github.com/unicode-org/cldr.git',
                    str_replace('/', DIRECTORY_SEPARATOR, $dir),
                ],
                $passthru
            );
            if (!is_dir($dir)) {
                throw new RuntimeException('Local clone of the CLDR repository not created in ' . str_replace('/', DIRECTORY_SEPARATOR, $dir));
            }
            try {
                $this->shell(
                    'git',
                    [
                        '-C ', str_replace('/', DIRECTORY_SEPARATOR, $dir),
                        'lfs',
                        'pull',
                        '--include', $this->getOptions()->getCldrMajorVersion() >= 38 ? 'tools/cldr-code' : 'tools/java',
                    ]
                );
            } catch (RuntimeException $foo) {
            }
            $patchFile = $this->environment->getProjectRootDirectory() . '/patch/cldr/' . $this->getOptions()->getCldrVersion() . '.patch';
            if (is_file($patchFile)) {
                $this->shell(
                    'git',
                    [
                        '-C', str_replace('/', DIRECTORY_SEPARATOR, $dir),
                        'apply',
                        str_replace('/', DIRECTORY_SEPARATOR, $patchFile),
                    ]
                );
            }
            $deleteDirectory = false;
        } finally {
            if ($deleteDirectory) {
                try {
                    $this->deleteCldrRepository();
                } catch (Throwable $foo) {
                }
            }
        }
    }

    /**
     * Get the list of available locale IDs.
     *
     * @throws \RuntimeException
     *
     * @return string[]
     */
    public function getAvailableLocaleIDs(): array
    {
        if ($this->availableLocales !== null) {
            return $this->availableLocales;
        }
        $this->ensureCldrRepositoryPresent();
        $dir = $this->getOptions()->getCldrRepositoryDirectory() . '/common/main';
        if (!is_dir($dir)) {
            throw new RuntimeException('Unable to find the directory ' . str_replace('/', DIRECTORY_SEPARATOR, $dir));
        }
        $contents = $this->filesystem->listDirectoryContents($dir);
        $availableLocales = [];
        $matches = null;
        foreach ($contents as $item) {
            if (preg_match('/^(.+)\.xml$/', $item, $matches)) {
                $localeID = $matches[1];
                if ($localeID === 'root' || preg_match('/^([a-z]{2,3})(?:_([A-Z][a-z]{3}))?(?:_([A-Z]{2}|[0-9]{3}))?$/', $localeID)) {
                    if (!in_array($localeID, $availableLocales, true)) {
                        $availableLocales[] = $localeID;
                        if (strpos($localeID, '_') !== false) {
                            [$languageID] = explode('_', $localeID);
                            if (!in_array($languageID, $availableLocales, true)) {
                                $availableLocales[] = $languageID;
                            }
                        }
                    }
                }
            }
        }
        if ($availableLocales === []) {
            throw new RuntimeException('No locales found in the directory directory ' . str_replace('/', DIRECTORY_SEPARATOR, $dir));
        }
        natcasesort($availableLocales);
        $this->availableLocales = array_values($availableLocales);

        return $this->availableLocales;
    }

    /**
     * Does the CLDR jar file exist?
     */
    public function isCldrJarPresent(): bool
    {
        return is_file($this->getOptions()->getCldrJarFile());
    }

    /**
     * Delete the CLDR jar.
     */
    public function deleteCldrJar(): void
    {
        if ($this->isCldrJarPresent()) {
            $this->filesystem->deleteFile($this->getOptions()->getCldrJarFile());
        }
    }

    /**
     * Be sure that the CLDR jar file exists.
     *
     * @throws \RuntimeException
     */
    public function ensureCldrJarPresent(bool $passthru = false): void
    {
        if ($this->isCldrJarPresent()) {
            return;
        }
        $this->ensureCldrRepositoryPresent();
        $deleteJar = true;
        try {
            if ($this->getOptions()->getCLDRMajorVersion() >= 38) {
                $command = 'mvn';
                $arguments = [
                    '--settings', str_replace('/', DIRECTORY_SEPARATOR, $this->environment->getMvnSettingsFilePath()),
                    'package',
                    '-Dmaven.repo.local=' . str_replace('/', DIRECTORY_SEPARATOR, $this->getOptions()->getMavenRepositoryPath()),
                    '-DskipTests=true',
                    '--file', str_replace('/', DIRECTORY_SEPARATOR, $this->getOptions()->getCldrRepositoryDirectory() . '/tools/cldr-code/pom.xml'),
                ];
            } else {
                $command = 'ant';
                $arguments = [
                    '-f', str_replace('/', DIRECTORY_SEPARATOR, $this->escapeShellArg($this->getOptions()->getCldrRepositoryDirectory() . '/tools/java/build.xml')),
                    'jar',
                ];
            }
            $this->shell($command, $arguments, $passthru);
            if (!$this->isCldrJarPresent()) {
                throw new RuntimeException('Failed to create the JAR file');
            }
            $deleteJar = false;
        } finally {
            if ($deleteJar) {
                try {
                    $this->deleteCldrJar();
                } catch (Throwable $x) {
                }
            }
        }
    }

    /**
     * Does the JSON directory is present?
     */
    public function isCldrJsonContainerPresent(): bool
    {
        return is_dir($this->getOptions()->getCldrJsonDirectory());
    }

    /**
     * Delete the JSON directory.
     *
     * @throws \RuntimeException
     */
    public function deleteCldrJsonContainer(): void
    {
        if ($this->isCldrJsonContainerPresent()) {
            $this->filesystem->deleteDirectory($this->getOptions()->getCldrJsonDirectory());
        }
    }

    /**
     * Is the JSON directory present for a specific locale?
     */
    public function isCldrJsonLocalePresent(string $localeID): bool
    {
        return is_dir($this->getOptions()->getCldrJsonDirectoryForLocale($localeID));
    }

    /**
     * Be sure that the JSON data for a locale is there.
     *
     * @throws \RuntimeException
     */
    public function ensureCldrJsonLocale(string $localeID, bool $passthru = false): void
    {
        if ($this->isCldrJsonLocalePresent($localeID)) {
            return;
        }
        $this->ensureCldrJarPresent();
        $dir = $this->getOptions()->getCldrJsonDirectoryForLocale($localeID);
        $this->filesystem->createDirectory($dir, true);
        $this->filesystem->deleteDirectory($dir);
        $deleteDirectory = true;
        try {
            $genDir = $this->getOptions()->getCldrMajorVersion() >= 38 ? dirname($dir) : $dir;
            $command = 'java';
            $arguments = [
                // http://unicode.org/cldr/trac/ticket/10044
                '-Duser.language=en', '-Duser.country=US',
                // where the CLDR data is located
                '-DCLDR_DIR=' . str_replace('/', DIRECTORY_SEPARATOR, $this->getOptions()->getCldrRepositoryDirectory()),
                // where to save the generated files
                '-DCLDR_GEN_DIR=' . str_replace('/', DIRECTORY_SEPARATOR, $genDir),
                // the CLDR jar file
                '-jar', str_replace('/', DIRECTORY_SEPARATOR, $this->getOptions()->getCldrJarFile()),
                'ldml2json',
                // (main|supplemental|segments|rbnf) Type of CLDR data being generated, main, supplemental, or segments.
                '-t', 'main',
                // (true|false) Whether the output JSON for the main directory should be based on resolved or unresolved data
                '-r', 'true',
                // The minimum draft status of the output data
                '-s', $this->getOptions()->getCldrDraftStatus(),
                // Regular expression to define only specific locales or files to be generated
                '-m', str_replace('-', '_', $localeID),
            ];
            $this->shell($command, $arguments, $passthru);
            if (!$this->isCldrJsonLocalePresent($localeID)) {
                throw new RuntimeException("Failed to create the JSON data for {$localeID}");
            }
            $deleteDirectory = false;
        } finally {
            if ($deleteDirectory) {
                try {
                    $this->filesystem->deleteDirectory($dir);
                } catch (Throwable $foo) {
                }
            }
        }
    }

    /**
     * Does the JSON directory is present for a specific generic data?
     *
     * @param string $genericID (supplemental, segments)
     */
    public function isCldrJsonGenericPresent(string $genericID): bool
    {
        return is_dir($this->getOptions()->getCldrJsonDirectoryForGeneric($genericID));
    }

    /**
     * Be sure that the JSON data for a generic data is there.
     *
     * @param string $genericID (supplemental, segments)
     *
     * @throws \RuntimeException
     */
    public function ensureCldrJsonGeneric(string $genericID, bool $passthru = false): void
    {
        if ($this->isCldrJsonGenericPresent($genericID)) {
            return;
        }
        $this->ensureCldrJarPresent();
        $dir = $this->getOptions()->getCldrJsonDirectoryForGeneric($genericID);
        $this->filesystem->createDirectory($dir, true);
        $this->filesystem->deleteDirectory($dir);
        $deleteDirectory = true;
        try {
            $command = 'java';
            $arguments = [
                // http://unicode.org/cldr/trac/ticket/10044
                '-Duser.language=en', '-Duser.country=US',
                // where the CLDR data is located
                '-DCLDR_DIR=' . str_replace('/', DIRECTORY_SEPARATOR, $this->getOptions()->getCldrRepositoryDirectory()),
                // where to save the generated files
                '-DCLDR_GEN_DIR=' . str_replace('/', DIRECTORY_SEPARATOR, $dir),
                // the CLDR jar file
                '-jar', str_replace('/', DIRECTORY_SEPARATOR, $this->getOptions()->getCldrJarFile()),
                'ldml2json',
                // The minimum draft status of the output data
                '-s', $this->getOptions()->getCldrDraftStatus(),
            ];
            switch ($genericID) {
                case 'rbnf':
                case 'supplemental':
                case 'segments':
                    $arguments = array_merge($arguments, [
                        // (true|false) Whether to write out the 'other' section, which contains any unmatched paths
                        '-o', 'true',
                        // (main|supplemental|segments|rbnf) Type of CLDR data being generated, main, supplemental, or segments
                        '-t', $genericID,
                    ]);
                    break;
                default:
                    throw new RuntimeException("Unrecognized generic data ID: {$genericID}");
            }
            $this->shell($command, $arguments, $passthru);
            if (!$this->isCldrJsonGenericPresent($genericID)) {
                throw new RuntimeException("Failed to create the JSON data for {$genericID}");
            }
            $deleteDirectory = false;
        } finally {
            if ($deleteDirectory) {
                try {
                    $this->filesystem->deleteDirectory($dir);
                } catch (Throwable $foo) {
                }
            }
        }
    }

    /**
     * @throws \RuntimeException
     *
     * @return string[]
     */
    public function getFinalLocaleIDs(): array
    {
        $availableLocaleIDs = $this->getAvailableLocaleIDs();
        $wantedLocaleIDs = $this->getOptions()->getLocales()->getLocaleIDs();
        if ($wantedLocaleIDs === true) {
            $localeIDs = $availableLocaleIDs;
        } else {
            foreach ($wantedLocaleIDs as $wantedLocaleID) {
                $bl = LocaleIdentifier::fromString($wantedLocaleID);
                if (!in_array($bl->getLanguage(), $availableLocaleIDs, true)) {
                    throw new RuntimeException("The locale {$wantedLocaleID} is not defined in the CLDR data");
                }
            }
            $localeIDs = $wantedLocaleIDs;
        }
        $excludedLocaleIDs = $this->getOptions()->getLocales()->getExcludedLocaleIDs();
        if ($excludedLocaleIDs !== []) {
            $localeIDs = array_diff($localeIDs, $excludedLocaleIDs);
        }
        natcasesort($localeIDs);

        return array_values($localeIDs);
    }
}
