<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;

class CodeMappings extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'codeMappings']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $mappings = parent::process($data);

        $data = [];
        foreach ($mappings as $key => $mapping) {
            if (strlen($key) === 2) {
                $type = 'territories';
            } else {
                $type = 'currencies';
            }
            foreach ($mapping as $name => $value) {
                if ($name[0] === '_') {
                    if ($name === '_internet') {
                        $value = explode(' ', $value);
                    }
                    if ($type === 'currencies' && $name === '_numeric') {
                        $value = (int) $value;
                    }
                    $data[$type][$key][substr($name, 1)] = $value;
                }
            }
        }

        return $data;
    }
}
