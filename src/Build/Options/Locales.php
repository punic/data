<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Options;

use InvalidArgumentException;
use Punic\DataBuilder\LocaleIdentifier;

class Locales
{
    /**
     * A placeholder meaning "all available locales".
     *
     * @var string
     */
    public const ALL_LOCALES_PLACEHOLDER = '[ALL]';

    /**
     * The list of the locale identifiers (or true for all available locales).
     *
     * @var string[]|true
     */
    protected $localeIDs = true;

    /**
     * The list of the identifiers of the locales to be excluded.
     *
     * @var string[]
     */
    protected $excludeLocaleIDs = [];

    /**
     * @param string[]|true $localeIDs the list of the locale identifiers (or true for all locales)
     * @param string[] $excludedLocaleIDs the list of the identifiers of the locales to be excluded
     */
    protected function __construct($localeIDs, array $excludedLocaleIDs)
    {
        $this->localeIDs = $localeIDs;
        $this->excludeLocaleIDs = $excludedLocaleIDs;
    }

    public function __toString(): string
    {
        $localeIDs = $this->getLocaleIDs();
        if ($localeIDs === true) {
            $excludeLocaleIDs = $this->getExcludedLocaleIDs();
            if ($excludeLocaleIDs === []) {
                return 'all locales';
            }
            return 'all locales except ' . implode(', ', $excludeLocaleIDs);
        }
        return implode(', ', $localeIDs);
    }

    public function serialize(): string
    {
        $chunks = [];
        $localeIDs = $this->getLocaleIDs();
        if ($localeIDs === true) {
            $chunks = [static::ALL_LOCALES_PLACEHOLDER];
        } else {
            $chunks = $localeIDs;
        }
        foreach ($this->getExcludedLocaleIDs() as $localeID) {
            $chunks[] = "-{$localeID}";
        }

        return implode(',', $chunks);
    }

    /**
     * Get the list of the locale identifiers (or true for all locales).
     *
     * @return string[]|true
     */
    public function getLocaleIDs()
    {
        return $this->localeIDs;
    }

    /**
     * Get the list of the identifiers of the locales to be excluded.
     *
     * @return string[]
     */
    public function getExcludedLocaleIDs()
    {
        return $this->excludeLocaleIDs;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return static
     */
    public static function parseString(string $value): self
    {
        return static::parseArray($value === '' ? [] : explode(',', $value));
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return static
     */
    public static function parseArray(array $values): self
    {
        $allLocaleIDs = false;
        $dictionary = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(__FUNCTION__ . ' received a wrong array item: expected string, found ' . gettype($value));
            }
            if ($value === '') {
                throw new InvalidArgumentException(__FUNCTION__ . ' received a wrong array item (empty string)');
            }
            if ($value === static::ALL_LOCALES_PLACEHOLDER) {
                $allLocaleIDs = true;
                continue;
            }
            switch ($value[0]) {
                case '-':
                    $localeOperation = $value[0];
                    $localeID = substr($value, 1);
                    break;
                default:
                    $localeOperation = '=';
                    $localeID = $value;
                    break;
            }
            $locale = LocaleIdentifier::fromString($localeID);
            if ($locale === null) {
                throw new InvalidArgumentException("Invalid locale identifier specified: {$value}");
            }
            $localeID = (string) $locale;
            if (isset($dictionary[$localeID])) {
                throw new InvalidArgumentException("Locale identifier specified more than once: {$localeID}");
            }
            $dictionary[$localeID] = $localeOperation;
        }
        if ($allLocaleIDs === false && in_array('=', $dictionary, true) === false) {
            $allLocaleIDs = true;
        }
        if ($allLocaleIDs) {
            $localeIDs = true;
            if (in_array('=', $dictionary, true)) {
                throw new InvalidArgumentException("You specified to use all the locales, and to use specific locales.\nIf you want to specify 'all locales except some', please prepend them with a minus sign (-).");
            }
            $excludeLocaleIDs = array_keys(array_filter(
                $dictionary,
                static function (string $operation): bool {
                    return $operation === '-';
                }
            ));
        } else {
            $localeIDs = array_keys(array_filter(
                $dictionary,
                static function (string $operation): bool {
                    return $operation !== '-';
                }
            ));
            $excludeLocaleIDs = array_keys(array_filter(
                $dictionary,
                static function (string $operation): bool {
                    return $operation === '-';
                }
            ));
            if ($excludeLocaleIDs !== []) {
                $commonLocaleIDs = array_intersect($localeIDs, $excludeLocaleIDs);
                if ($commonLocaleIDs !== []) {
                    $localeIDs = array_values(array_diff($localeIDs, $commonLocaleIDs));
                }
                $excludeLocaleIDs = [];
            }
        }

        return new static($localeIDs, $excludeLocaleIDs);
    }
}
