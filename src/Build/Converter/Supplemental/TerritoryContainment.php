<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;

class TerritoryContainment extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'territoryContainment']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $data = parent::process($data);
        foreach (array_keys($data) as $key) {
            if (array_key_exists('_grouping', $data[$key])) {
                unset($data[$key]['_grouping']);
            }
            if (array_key_exists('_contains', $data[$key])) {
                $data[$key]['contains'] = $data[$key]['_contains'];
                unset($data[$key]['_contains']);
            }
            if (is_string($key) && strpos($key, '-status-') !== false) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
