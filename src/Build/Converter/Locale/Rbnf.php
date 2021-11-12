<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;
use Punic\DataBuilder\Build\SourceData;
use Punic\DataBuilder\LocaleIdentifier;

class Rbnf extends Locale
{
    public function __construct()
    {
        parent::__construct('rbnf', ['rbnf', 'rbnf'], 'rbnf');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::getSourceFile()
     */
    protected function getSourceFile(SourceData $sourceData, string $localeID): string
    {
        $baseFolder = $sourceData->getOptions()->getCldrJsonDirectoryForGeneric('rbnf') . '/';
        $baseFileName = $localeID === 'root' ? $sourceData->getOptions()->getSourceRootLocaleID() : $localeID;

        return $baseFolder . ($sourceData->getOptions()->getCldrMajorVersion() >= 38 ? "{$localeID}/{$baseFileName}.json" : "{$baseFileName}.json");
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::load()
     */
    protected function load(SourceData $sourceData, string $localeID): array
    {
        $locale = LocaleIdentifier::fromString($localeID);
        $localeIDs = array_merge([$localeID], $locale->getParentLocaleIdentifiers());
        $data = [];
        foreach ($localeIDs as $localeID) {
            $file = $this->getSourceFile($sourceData, $localeID);
            if (file_exists($file)) {
                $parent = parent::load($sourceData, $localeID);
                foreach ($parent['rbnf']['rbnf'] as $group => $rulesetGrouping) {
                    if (!isset($data['rbnf']['rbnf'][$group])) {
                        $data['rbnf']['rbnf'][$group] = [];
                    }
                    $data['rbnf']['rbnf'][$group] += $rulesetGrouping;
                }
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::getRoots()
     */
    protected function getRoots(string $localeID): array
    {
        return $this->roots;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::getUnsetByPath()
     */
    protected function getUnsetByPath(string $localeID): array
    {
        return [
            '/rbnf' => ['identity'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process(SourceData $sourceData, array $data, string $localeID): array
    {
        $data = parent::process($sourceData, $data, $localeID);
        $rulesets = [];
        foreach ($data as $group => $rulesetGrouping) {
            if ($group === 'NumberingSystemRules' && $localeID !== 'root') {
                continue;
            }
            foreach ($rulesetGrouping as $type => $ruleset) {
                $type = substr($type, 1);
                foreach ($ruleset as [$descriptor, $rule]) {
                    if ($rule[0] === "'") {
                        $rule = substr($rule, 1);
                    }
                    $rule = rtrim($rule, ';');
                    $parts = explode('/', $descriptor);
                    $base = $parts[0];
                    if (is_numeric($base)) {
                        $rulesets[$type]['integer'][$base]['rule'] = $rule;
                        if (count($parts) > 1) {
                            $rulesets[$type]['integer'][$base]['radix'] = (int) $parts[1];
                        }
                    } else {
                        $rulesets[$type][$base]['rule'] = $rule;
                    }
                }
            }
        }
        return $rulesets;
    }
}
