<?php

declare(strict_types=1);

namespace Punic\DataBuilder;

class LocaleIdentifier
{
    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $script;

    /**
     * @var string
     */
    private $territory;

    /**
     * @var string[]
     */
    private $variants;

    /**
     * @param string[] $variants
     */
    protected function __construct(string $language, string $script = '', string $territory = '', array $variants = [])
    {
        $this->language = $language;
        $this->script = $script;
        $this->territory = $territory;
        $this->variants = $variants;
    }

    public function __toString(): string
    {
        return static::merge($this->getLanguage(), $this->getScript(), $this->getTerritory(), $this->getVariants());
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getTerritory(): string
    {
        return $this->territory;
    }

    /**
     * @return string[]
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * @param string|mixed $localeIdentifier
     *
     * @return static|null
     */
    public static function fromString($localeIdentifier): ?self
    {
        if (!is_string($localeIdentifier) || $localeIdentifier === '') {
            return null;
        }
        if (strcasecmp($localeIdentifier, 'root') === 0) {
            // http://unicode.org/reports/tr35/#Unicode_language_identifier
            return new static('root');
        }
        $rxLanguage = '(?:[a-z]{2,3})|(?:[a-z]{5,8}:)';
        $rxScript = '[a-z]{4}';
        $rxTerritory = '(?:[a-z]{2})|(?:[0-9]{3})';
        $rxVariant = '(?:[a-z0-9]{5,8})|(?:[0-9][a-z0-9]{3})';
        $rxSep = '[-_]';
        $matches = null;
        if (preg_match("/^(?<language>{$rxLanguage})(?:{$rxSep}(?<script>{$rxScript}))?(?:{$rxSep}(?<territory>{$rxTerritory}))?(?<variants>(?:{$rxSep}(?:{$rxVariant}))*)$/i", $localeIdentifier, $matches)) {
            return new static(
                strtolower($matches['language']),
                isset($matches['script']) ? ucfirst(strtolower($matches['script'])) : '',
                isset($matches['territory']) ? strtoupper($matches['territory']) : '',
                isset($matches['variants']) && $matches['variants'] !== '' ? explode('_', strtoupper(str_replace('-', '_', substr($matches['variants'], 1)))) : []
            );
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getParentLocaleIdentifiers(): array
    {
        $parents = [];
        $language = $this->getLanguage();
        $script = $this->getScript();
        $territory = $this->getTerritory();
        if ($this->getVariants() !== []) {
            $parents[] = static::merge($language, $script, $territory);
        }
        if ($script !== '' && $territory !== '') {
            $parents[] = static::merge($language, $script, '');
            $parents[] = static::merge($language, '', $territory);
        }
        if ($script !== '' || $territory !== '') {
            $parents[] = static::merge($language);
        }
        $parents[] = 'root';

        return $parents;
    }

    /**
     * @param string[] $variants
     */
    protected static function merge(string $language, string $script = '', string $territory = '', array $variants = []): string
    {
        $parts = [$language];
        if ($script !== '') {
            $parts[] = $script;
        }
        if ($territory !== '') {
            $parts[] = $territory;
        }
        if ($variants !== []) {
            $parts = array_merge($parts, $variants);
        }

        return implode('_', $parts);
    }
}
