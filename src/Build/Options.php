<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

use Punic\DataBuilder\Cldr\DraftStatus;
use Punic\DataBuilder\Environment;

class Options
{
    /**
     * The placeholder in the output directory that will be replaced with the Punic data format version.
     *
     * @var string
     */
    public const OUTPUTDIR_PLACEHOLDER_FORMATVERSION = '<FORMAT-VERSION>';

    /**
     * The placeholder in the output directory that will be replaced with the CLDR version.
     *
     * @var string
     */
    public const OUTPUTDIR_PLACEHOLDER_CLDRVERSION = '<CLDR-VERSION>';

    /**
     * The CLDR version since we started to use libphonenumber.
     *
     * @var string
     */
    protected const LIBPHONENUMBER_USED_SINCE_CLDR = '34';

    /**
     * @var \Punic\DataBuilder\Environment
     */
    protected $environment;

    /**
     * The CLDR version.
     *
     * @var string
     */
    private $cldrVersion = '';

    /**
     * The libphonenumber version.
     *
     * @var string
     */
    private $libphonenumberVersion = '';

    /**
     * The locales to be processed.
     *
     * @var \Punic\DataBuilder\Build\Options\Locales|null
     */
    private $locales;

    /**
     * The CLDR draft status.
     *
     * @var string
     */
    private $cldrDraftStatus = '';

    /**
     * The path to the directory where the Punic data will be stored.
     *
     * @var string
     */
    private $outputDirectory = '';

    /**
     * The path to directory where we'll save temporary stuff.
     *
     * @var string
     */
    private $temporaryDirectory = '';

    /**
     * The path of the state file (empty string: default, null: none).
     *
     * @var string|null
     */
    private $stateFile = '';

    /**
     * Reset the source CLDR data before the execution?
     *
     * @var bool
     */
    private $resetCldrData = false;

    /**
     * Reset the destination Punic data before the execution?
     *
     * @var bool
     */
    private $resetPunicData = false;

    /**
     * Generated expanded (uncompressed) PHP data files?
     *
     * @var bool
     */
    private $prettyOutput = false;

    /**
     * Just build the CLDR JSON data, don't parse it?
     *
     * @var bool
     */
    private $jsonOnly = false;

    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Get the default CLDR version.
     */
    public function getDefaultCldrVersion(): string
    {
        return '40';
    }

    /**
     * Set the CLDR version.
     *
     * @param string $value empty string to reset to using the default version
     *
     * @return $this
     */
    public function setCldrVersion(string $value): self
    {
        $this->cldrVersion = $value;

        return $this;
    }

    /**
     * Get the CLDR version.
     */
    public function getCldrVersion(): string
    {
        return $this->cldrVersion !== '' ? $this->cldrVersion : $this->getDefaultCldrVersion();
    }

    /**
     * Get the major CLDR version.
     */
    public function getCldrMajorVersion(): int
    {
        $m = null;

        return preg_match('/^(\d+)/', $this->getCldrVersion(), $m) ? (int) $m[1] : 0;
    }

    /**
     * Get the data format version: to be increased when the data structure changes (not needed if data is only added).
     *
     * @return string
     */
    public function getDataFormatVersion()
    {
        if ($this->getCldrMajorVersion() >= 38) {
            return '2';
        }
        return '1';
    }

    /**
     * Set the libphonenumber version.
     *
     * @param string $value empty string to reset to using the default version
     *
     * @return $this
     */
    public function setLibphonenumberVersion(string $value): self
    {
        $this->libphonenumberVersion = $value;

        return $this;
    }

    /**
     * Get the libphonenumber version.
     */
    public function getLibphonenumberVersion(): string
    {
        return $this->libphonenumberVersion !== '' ? $this->libphonenumberVersion : static::getDefaultLibphonenumberVersionForCldrVersion($this->getCldrVersion());
    }

    /**
     * Should we use libphonenumber instead of CLDR for telephone data?
     */
    public function shouldUseLibphonenumber(): bool
    {
        return version_compare($this->getCldrVersion(), static::LIBPHONENUMBER_USED_SINCE_CLDR) >= 0;
    }

    /**
     * Set the locales to be processed.
     *
     * @return $this
     */
    public function setLocales(Options\Locales $value): self
    {
        $this->locales = $value;

        return $this;
    }

    /**
     * Get the locales to be processed.
     */
    public function getLocales(): Options\Locales
    {
        if ($this->locales === null) {
            $this->locales = Options\Locales::parseString(Options\Locales::ALL_LOCALES_PLACEHOLDER);
        }

        return $this->locales;
    }

    /**
     * Get the default CLDR draft status.
     */
    public function getDefaultCldrDraftStatus(): string
    {
        return DraftStatus::CONTRIBUTED;
    }

    /**
     * Set the CLDR draft status.
     *
     * @param string $value empty string to reset to using the default status
     *
     * @return $this
     */
    public function setCldrDraftStatus(string $value): self
    {
        $this->cldrDraftStatus = $value;

        return $this;
    }

    /**
     * Get the CLDR draft status.
     */
    public function getCldrDraftStatus(): string
    {
        return $this->cldrDraftStatus !== '' ? $this->cldrDraftStatus : $this->getDefaultCldrDraftStatus();
    }

    /**
     * Get the default path to the directory where the Punic data will be stored.
     * It contains placeholders.
     *
     * @see \Punic\DataBuilder\Build\Options::OUTPUTDIR_PLACEHOLDER_FORMATVERSION
     * @see \Punic\DataBuilder\Build\Options::OUTPUTDIR_PLACEHOLDER_CLDRVERSION
     */
    public function getDefaultOutputDirectory(): string
    {
        return implode('/', [
            rtrim($this->environment->getProjectRootDirectory(), '/'),
            'docs',
            static::OUTPUTDIR_PLACEHOLDER_FORMATVERSION,
            static::OUTPUTDIR_PLACEHOLDER_CLDRVERSION,
        ]);
    }

    /**
     * Get the path to the directory where the Punic data will be stored.
     *
     * @param bool $keepPlaceholders should the placeholders be replaced with actual values (true) or be kept in the result (false)?
     */
    public function getOutputDirectory(bool $keepPlaceholders = false): string
    {
        $outputDirectory = $this->outputDirectory !== '' ? $this->outputDirectory : $this->getDefaultOutputDirectory();
        if ($keepPlaceholders === false) {
            $outputDirectory = strtr(
                $outputDirectory,
                [
                    static::OUTPUTDIR_PLACEHOLDER_FORMATVERSION => $this->getDataFormatVersion(),
                    static::OUTPUTDIR_PLACEHOLDER_CLDRVERSION => $this->getCldrVersion(),
                ]
            );
        }
        return $outputDirectory;
    }

    /**
     * Get the path to the directory where the locale-specific Punic data will be stored.
     *
     * @param bool $keepPlaceholders should the placeholders be replaced with actual values (true) or be kept in the result (false)?
     */
    public function getOutputDirectoryForLocale(string $localeID, bool $keepPlaceholders = false): string
    {
        return $this->getOutputDirectory($keepPlaceholders) . '/' . str_replace('_', '-', $localeID);
    }

    /**
     * Set the path to the directory where the Punic data will be stored.
     *
     * @param string $value empty string to reset to using the default path
     *
     * @return $this
     */
    public function setOutputDirectory(string $value): self
    {
        $this->outputDirectory = $value;

        return $this;
    }

    /**
     * Get the default path to directory where we'll save temporary stuff.
     */
    public function getDefaultTemporaryDirectory(): string
    {
        return rtrim($this->environment->getProjectRootDirectory(), '/') . '/temp';
    }

    /**
     * Set the path to directory where we'll save temporary stuff.
     *
     * @param string $value empty string to reset to using the default path
     *
     * @return $this
     */
    public function setTemporaryDirectory(string $value): self
    {
        $this->temporaryDirectory = $value;

        return $this;
    }

    /**
     * Get the path to directory where we'll save temporary stuff.
     */
    public function getTemporaryDirectory(): string
    {
        return $this->temporaryDirectory !== '' ? $this->temporaryDirectory : $this->getDefaultTemporaryDirectory();
    }

    /**
     * Get the default path of the state file.
     */
    public function getDefaultStatefilePath(): string
    {
        return rtrim($this->environment->getProjectRootDirectory(), '/') . '/docs/state.json';
    }

    /**
     * Get the path of the state file.
     *
     * @return string|null NULL if no state file
     */
    public function getStatefilePath(): ?string
    {
        if ($this->stateFile === null) {
            return null;
        }
        return $this->stateFile !== '' ? $this->stateFile : $this->getDefaultStatefilePath();
    }

    /**
     * Set the path of the state file.
     *
     * @param string|null $value NULL to disable the creation/updating the state file
     *
     * @return $this
     */
    public function setStatefilePath(?string $value): self
    {
        $this->stateFile = $value;

        return $this;
    }

    /**
     * Reset the source CLDR data before the execution?
     */
    public function isResetCldrData(): bool
    {
        return $this->resetCldrData;
    }

    /**
     * Reset the source CLDR data before the execution?
     *
     * @return $this
     */
    public function setResetCldrData(bool $value): self
    {
        $this->resetCldrData = $value;

        return $this;
    }

    /**
     * Reset the destination Punic data before the execution?
     */
    public function isResetPunicData(): bool
    {
        return $this->resetPunicData;
    }

    /**
     * Reset the destination Punic data before the execution?
     *
     * @return $this
     */
    public function setResetPunicData(bool $value): self
    {
        $this->resetPunicData = $value;

        return $this;
    }

    /**
     * Generated expanded (uncompressed) PHP data files?
     */
    public function isPrettyOutput(): bool
    {
        return $this->prettyOutput;
    }

    /**
     * Generated expanded (uncompressed) PHP data files?
     *
     * @return $this
     */
    public function setPrettyOutput(bool $value): self
    {
        $this->prettyOutput = $value;

        return $this;
    }

    /**
     * Just build the CLDR JSON data, don't parse it?
     */
    public function isJsonOnly(): bool
    {
        return $this->jsonOnly;
    }

    /**
     * Just build the CLDR JSON data, don't parse it?
     *
     * @return $this
     */
    public function setJsonOnly(bool $value): self
    {
        $this->jsonOnly = $value;

        return $this;
    }

    /**
     * Get the repository directory path.
     */
    public function getCldrRepositoryDirectory(): string
    {
        return rtrim($this->getTemporaryDirectory(), '/') . '/cldr/' . $this->getCldrVersion() . '/repository';
    }

    /**
     * Get the CLDR jar file path.
     */
    public function getCldrJarFile(): string
    {
        return $this->getCldrRepositoryDirectory() . ($this->getCldrMajorVersion() >= 38 ? '/tools/cldr-code/target/cldr-code.jar' : '/tools/java/cldr.jar');
    }

    /**
     * Get the path to the mvn repository.
     */
    public function getMavenRepositoryPath(): string
    {
        return rtrim($this->getTemporaryDirectory(), '/') . '/mvn';
    }

    /**
     * Get path to the the directory that contains the CLDR JSON files.
     */
    public function getCldrJsonDirectory(): string
    {
        return rtrim($this->getTemporaryDirectory(), '/') . '/cldr/' . $this->getCldrVersion() . '/json-' . $this->getCldrDraftStatus();
    }

    /**
     * Get path to the the directory that contains the JSON files for a specific locale.
     */
    public function getCldrJsonDirectoryForLocale(string $localeID): string
    {
        return $this->getCldrJsonDirectory() . '/locales/' . $localeID;
    }

    /**
     * Get the JSON directory path for a generic data.
     *
     * @param string $genericID (supplemental, segments)
     */
    public function getCldrJsonDirectoryForGeneric(string $genericID): string
    {
        return $this->getCldrJsonDirectory() . '/' . $genericID;
    }

    /**
     * Get the "root" source locale identifier.
     */
    public function getSourceRootLocaleID(): string
    {
        if ($this->getCldrMajorVersion() <= 39) {
            return 'root';
        }
        return 'und';
    }

    /**
     * Get the libphonenumber version used for a specific CLDR version.
     *
     * @return string empty string if the libphonenumber is not used for the CLDR version
     */
    protected static function getDefaultLibphonenumberVersionForCldrVersion(string $cldrVersion): string
    {
        if (version_compare($cldrVersion, static::LIBPHONENUMBER_USED_SINCE_CLDR) < 0) {
            return '';
        }
        if (version_compare($cldrVersion, '35.1') < 0) {
            return 'v8.10.1';
        }
        if (version_compare($cldrVersion, '36') < 0) {
            return 'v8.10.12';
        }
        return 'v8.12.36';
    }
}
