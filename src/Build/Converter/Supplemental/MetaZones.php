<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use RuntimeException;

class MetaZones extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'metaZones']);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $data = parent::process($data);
        $this->checkExactKeys($data['metazoneInfo'], ['timezone']);
        $data['metazoneInfo'] = $data['metazoneInfo']['timezone'];
        foreach ($data['metazoneInfo'] as $id0 => $info0) {
            foreach ($info0 as $id1 => $info1) {
                if (is_int($id1)) {
                    $info1 = $this->fixMetazoneInfo($info1);
                } else {
                    foreach ($info1 as $id2 => $info2) {
                        if (is_int($id2)) {
                            $info2 = $this->fixMetazoneInfo($info2);
                        } else {
                            foreach ($info2 as $id3 => $info3) {
                                if (is_int($id3)) {
                                    $info3 = $this->fixMetazoneInfo($info3);
                                } else {
                                    throw new RuntimeException('Invalid metazoneInfo node');
                                }
                                $info2[$id3] = $info3;
                            }
                        }
                        $info1[$id2] = $info2;
                    }
                }
                $info0[$id1] = $info1;
            }
            $data['metazoneInfo'][$id0] = $info0;
        }
        $metazones = [];
        if ((!array_key_exists('metazones', $data)) && is_array($data['metazones']) && (count($data['metazones']) > 0)) {
            throw new RuntimeException('metazones node not found/invalid');
        }
        foreach ($data['metazones'] as $mz) {
            $this->checkExactKeys($mz, ['mapZone']);
            $mz = $mz['mapZone'];
            foreach (array_keys($mz) as $i) {
                switch ($i) {
                    case '_other':
                    case '_territory':
                    case '_type':
                        $mz[substr($i, 1)] = $mz[$i];
                        unset($mz[$i]);
                        break;
                    default:
                        throw new RuntimeException("Invalid mapZone node key: {$i}");
                }
            }
            $metazones[] = $mz;
        }
        $data['metazones'] = $metazones;

        return $data;
    }

    /**
     * @param array|mixed $a
     *
     * @throws \RuntimeException
     */
    private function fixMetazoneInfo($a): array
    {
        $this->checkExactKeys($a, ['usesMetazone']);
        $a = $a['usesMetazone'];
        foreach (array_keys($a) as $key) {
            switch ($key) {
                case '_mzone':
                case '_from':
                case '_to':
                    $a[substr($key, 1)] = $a[$key];
                    unset($a[$key]);
                    break;
                default:
                    throw new RuntimeException("Invalid metazoneInfo node: {$key}");
            }
        }

        return $a;
    }
}
