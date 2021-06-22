<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use Punic\DataBuilder\Build\SourceData;

class TimeZone extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'keyword', 'key', 'type'], 'timeZones');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::getSourceFile()
     */
    protected function getSourceFile(SourceData $sourceData): string
    {
        return $sourceData->getOptions()->getCldrRepositoryDirectory() . '/common/bcp47/timezone.xml';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter::getXmlPaths()
     */
    protected function getXmlPaths(): array
    {
        return [
            '/ldmlBCP47/keyword/key/type' => ['alias'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::load()
     */
    protected function load(SourceData $sourceData): array
    {
        $sourceFile = $this->getSourceFile($sourceData);

        return [
            'supplemental' => $this->loadXml($sourceFile),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::process()
     */
    protected function process(array $data): array
    {
        $zones = parent::process($data);
        $data = [];
        foreach (array_keys($zones) as $zone) {
            $aliases = explode(' ', $zone);
            if (count($aliases) > 1) {
                $zoneID = array_shift($aliases);
                foreach ($aliases as $alias) {
                    $data['aliases'][$alias] = $zoneID;
                }
            }
        }

        return $data;
    }
}
