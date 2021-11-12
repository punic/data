<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use Punic\DataBuilder\Build\SourceData;

class DayPeriods extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'dayPeriodRuleSet'], 'dayPeriods');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(SourceData $sourceData, array $data): array
    {
        $data = parent::process($sourceData, $data);
        foreach (array_keys($data) as $l) {
            unset($data[$l]['am']);
            unset($data[$l]['pm']);
            unset($data[$l]['noon']);
            unset($data[$l]['midnight']);

            foreach (array_keys($data[$l]) as $period) {
                if (isset($data[$l]['at'])) {
                    unset($data[$l]);
                } else {
                    $data[$l][$period]['from'] = $data[$l][$period]['_from'];
                    $data[$l][$period]['before'] = $data[$l][$period]['_before'];
                    unset($data[$l][$period]['_from']);
                    unset($data[$l][$period]['_before']);
                }
            }

            uasort($data[$l], static function (array $rule1, array $rule2): int {
                return strcmp($rule1['before'], $rule2['before']);
            });
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::getUnsetByPath()
     */
    protected function getUnsetByPath(): array
    {
        // @todo
        return [
            '/supplemental' => ['version', 'dayPeriodRuleSet-type-selection'],
        ];
    }
}
