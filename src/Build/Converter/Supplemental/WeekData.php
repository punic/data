<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use RuntimeException;

class WeekData extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'weekData']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $data = parent::process($data);
        foreach (array_keys($data['minDays']) as $key) {
            $value = $data['minDays'][$key];
            if (!preg_match('/^[0-9]+$/', $value)) {
                throw new RuntimeException("Bad number: {$value}");
            }
            $data['minDays'][$key] = (int) $value;
        }
        $dict = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        foreach (array_keys($data['firstDay']) as $key) {
            $val = array_search($data['firstDay'][$key], $dict, true);
            if ($val === false) {
                throw new RuntimeException("Unknown weekday name: {$data['firstDay'][$key]}");
            }
            $data['firstDay'][$key] = $val;
        }
        unset($data['firstDay-alt-variant']);
        unset($data['weekendStart']);
        unset($data['weekendEnd']);

        return $data;
    }
}
