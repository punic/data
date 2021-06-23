<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Collator;
use Punic\DataBuilder\Build\Converter\Locale;
use RuntimeException;

class Scripts extends Locale
{
    public function __construct()
    {
        parent::__construct('main', ['localeDisplayNames', 'scripts']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process(array $data, string $localeID): array
    {
        $data = parent::process($data, $localeID);
        [$names, $alts] = $this->extractScripts($data);
        $this->fillMissingScriptNames($names, $alts);
        $this->sortScriptNames($localeID, $names);
        $reversedAlts = $this->reverseScriptAlts($alts);

        return $this->mergeScriptNames($names, $reversedAlts);
    }

    protected function extractScripts(array $data): array
    {
        $names = [];
        $alts = [
            'secondary' => [],
            'variant' => [],
            'short' => [],
            'stand-alone' => [],
        ];
        $m = null;
        foreach ($data as $fullScriptCode => $scriptName) {
            if (!preg_match('/^(?<code>[A-Z][a-z]{3})(-alt-(?<alt>.+))?$/', $fullScriptCode, $m)) {
                throw new RuntimeException("Invalid script code: {$fullScriptCode}");
            }
            $scriptCode = $m['code'];
            $alt = $m['alt'] ?? '';
            if ($alt === '') {
                if (isset($names[$scriptCode])) {
                    throw new RuntimeException("Duplicated script code: {$fullScriptCode}");
                }
                $names[$scriptCode] = $scriptName;
            } elseif (!isset($alts[$alt])) {
                throw new RuntimeException("Unrecognized '{$alt}' in script code {$fullScriptCode}");
            } else {
                $alts[$alt][$scriptCode] = array_merge($alts[$alt][$scriptCode] ?? [], [$scriptName]);
            }
        }
        foreach (array_keys($alts) as $alt) {
            if ($alts[$alt] === []) {
                unset($alts[$alt]);
            }
        }

        return [$names, $alts];
    }

    protected function fillMissingScriptNames(array &$names, array $alts): void
    {
        foreach (array_keys($alts) as $alt) {
            foreach (array_keys($alts[$alt]) as $scriptCode) {
                if (isset($names[$scriptCode])) {
                    continue;
                }
                $names[$scriptCode] = array_shift($alts[$alt][$scriptCode]);
                if ($alts[$alt][$scriptCode] === []) {
                    unset($alts[$alt][$scriptCode]);
                }
            }
        }
    }

    protected function sortScriptNames(string $localeID, array &$names): void
    {
        $collator = new Collator($localeID);
        $collator->asort($names);
    }

    protected function reverseScriptAlts(array $alts): array
    {
        $scriptCodes = [];
        foreach ($alts as $scripts) {
            $scriptCodes = array_unique(array_merge($scriptCodes, array_keys($scripts)));
        }
        $result = [];
        foreach ($scriptCodes as $scriptCode) {
            $scriptAlts = [];
            foreach ($alts as $alt => $scripts) {
                if (!isset($scripts[$scriptCode])) {
                    continue;
                }
                $scriptAlts[$alt] = $scripts[$scriptCode];
            }
            $result[$scriptCode] = $scriptAlts;
        }

        return $result;
    }

    protected function mergeScriptNames(array $names, array $reversedAlts): array
    {
        $result = [];
        foreach ($names as $scriptCode => $name) {
            if (isset($reversedAlts[$scriptCode])) {
                $result[$scriptCode] = ['' => $name] + $reversedAlts[$scriptCode];
            } else {
                $result[$scriptCode] = $name;
            }
        }

        return $result;
    }
}
