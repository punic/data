<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;
use RuntimeException;

class Units extends Locale
{
    public function __construct()
    {
        parent::__construct('main', ['units']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Locale::process()
     */
    protected function process(array $data, string $localeID): array
    {
        $data = parent::process($data, $localeID);
        $originalData = $data;
        $m = null;
        foreach (array_keys($data) as $width) {
            switch ($width) {
                case 'long':
                case 'short':
                case 'narrow':
                    foreach (array_keys($data[$width]) as $unitKey) {
                        switch ($unitKey) {
                            case 'per':
                                $this->checkExactKeys($data[$width][$unitKey], ['compoundUnitPattern']);
                                $data[$width]['_compoundPattern'] = $this->toPhpSprintf($data[$width][$unitKey]['compoundUnitPattern']);
                                unset($data[$width][$unitKey]);
                                break;
                            case 'times':
                                $this->checkExactKeys($data[$width][$unitKey], ['compoundUnitPattern']);
                                $data[$width]['_compoundPatternX'] = $this->toPhpSprintf($data[$width][$unitKey]['compoundUnitPattern']);
                                unset($data[$width][$unitKey]);
                                break;
                            case 'coordinateUnit':
                                if ($data[$width][$unitKey]['displayName']) {
                                    $displayName = $data[$width][$unitKey]['displayName'];
                                    unset($data[$width][$unitKey]['displayName']);
                                } else {
                                    $displayName = null;
                                }
                                $this->checkExactKeys($data[$width][$unitKey], ['east', 'north', 'south', 'west']);
                                $data[$width]['_coordinateUnit'] = [];
                                foreach (array_keys($data[$width][$unitKey]) as $direction) {
                                    $data[$width]['_coordinateUnit'][$direction] = $this->toPhpSprintf($data[$width][$unitKey][$direction]);
                                }
                                if ($displayName !== null) {
                                    $data[$width]['_coordinateUnit']['_displayName'] = $displayName;
                                }
                                unset($data[$width][$unitKey]);
                                break;
                            default:
                                if (preg_match('/^\d\d+p\d+$/', $unitKey)) {
                                    // @todo
                                    continue 2;
                                }
                                if (preg_match('/^power\d+$/', $unitKey)) {
                                    // @todo
                                    continue 2;
                                }
                                if (!preg_match('/^(\\w+)?-(.+)$/', $unitKey, $m)) {
                                    throw new RuntimeException("Invalid node (2) '{$width}/{$unitKey}'");
                                }
                                $unitKind = $m[1];
                                $unitName = $m[2];
                                if ($unitKind === '10p' && is_numeric($unitName)) {
                                    // @todo
                                    continue 2;
                                }
                                if (!array_key_exists($unitKind, $data[$width])) {
                                    $data[$width][$unitKind] = [];
                                }
                                if (!array_key_exists($unitName, $data[$width][$unitKind])) {
                                    $data[$width][$unitKind][$unitName] = [];
                                }
                                if (!isset($data[$width][$unitKey]['unitPattern-count-other'])) {
                                    $lookInOtherWidths = [];
                                    switch ($width) {
                                        case 'long':
                                            $lookInOtherWidths = ['short', 'narrow'];
                                            break;
                                        case 'short':
                                            $lookInOtherWidths = ['narrow', 'long'];
                                            break;
                                        case 'narrow':
                                            $lookInOtherWidths = ['short', 'long'];
                                            break;
                                    }
                                    foreach ($lookInOtherWidths as $lookInOtherWidth) {
                                        if (isset($originalData[$lookInOtherWidth][$unitKey]['unitPattern-count-other'])) {
                                            $data[$width][$unitKey] += $originalData[$lookInOtherWidth][$unitKey];
                                            break;
                                        }
                                    }
                                    if (!isset($data[$width][$unitKey]['unitPattern-count-other'])) {
                                        throw new RuntimeException("Missing 'other' rule in '{$width}/{$unitKey}'");
                                    }
                                }
                                foreach (array_keys($data[$width][$unitKey]) as $pluralRuleSrc) {
                                    switch ($pluralRuleSrc) {
                                        case 'displayName':
                                            $data[$width][$unitKind][$unitName]['_name'] = $data[$width][$unitKey][$pluralRuleSrc];
                                            break;
                                        case 'gender':
                                            $data[$width][$unitKind][$unitName]['_gender'] = $data[$width][$unitKey][$pluralRuleSrc];
                                            break;
                                        case 'perUnitPattern':
                                            $data[$width][$unitKind][$unitName]['_per'] = $this->toPhpSprintf($data[$width][$unitKey][$pluralRuleSrc]);
                                            break;
                                        default:
                                            if (preg_match('/-count-(\w+)/', $pluralRuleSrc, $m)) {
                                                $pluralRule = $m[1];
                                            } else {
                                                $pluralRule = 'other';
                                            }
                                            if (preg_match('/-gender-(\w+)/', $pluralRuleSrc, $m)) {
                                                $pluralRule .= '-' . $m[1];
                                            }
                                            if (preg_match('/-case-(\w+)/', $pluralRuleSrc, $m)) {
                                                $pluralRule .= '-' . $m[1];
                                            }
                                            $data[$width][$unitKind][$unitName][$pluralRule] = $this->toPhpSprintf($data[$width][$unitKey][$pluralRuleSrc]);
                                            break;
                                    }
                                }
                                unset($data[$width][$unitKey]);
                                break;
                        }
                    }
                    break;
                default:
                    if (preg_match('/^durationUnit-type-(.+)/', $width, $m)) {
                        unset($data[$width]['durationUnitPattern-alt-variant']);
                        $this->checkExactKeys($data[$width], ['durationUnitPattern']);
                        $t = $m[1];
                        if (!array_key_exists('_durationPattern', $data)) {
                            $data['_durationPattern'] = [];
                        }
                        $data['_durationPattern'][$t] = $data[$width]['durationUnitPattern'];
                        unset($data[$width]);
                    } else {
                        throw new RuntimeException("Invalid node (6) '{$width}'");
                    }
                    break;
            }
        }

        return $data;
    }
}
