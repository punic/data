<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use RuntimeException;

class MeasurementData extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'measurementData']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $data = parent::process($data);
        if (!(array_key_exists('measurementSystem', $data) && is_array($data['measurementSystem']))) {
            throw new RuntimeException('Missing/invalid key: measurementSystem');
        }
        if (!(array_key_exists('paperSize', $data) && is_array($data['paperSize']))) {
            throw new RuntimeException('Missing/invalid key: paperSize');
        }

        return $data;
    }
}
