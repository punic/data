<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use Punic\DataBuilder\Build\SourceData;

class TimeData extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'timeData']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(SourceData $sourceData, array $data): array
    {
        $data = parent::process($sourceData, $data);
        foreach (array_keys($data) as $key) {
            $data[$key]['preferred'] = $data[$key]['_preferred'];
            unset($data[$key]['_preferred']);

            $data[$key]['allowed'] = explode(' ', $data[$key]['_allowed']);
            unset($data[$key]['_allowed']);
        }

        return $data;
    }
}
