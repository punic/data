<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;
use Punic\DataBuilder\Build\SourceData;

class Subdivisions extends Supplemental
{
    public function __construct()
    {
        parent::__construct('supplemental', ['supplemental', 'subdivisionContainment', 'subgroup'], 'subdivisionContainment');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter\Supplemental::getSourceFile()
     */
    protected function getSourceFile(SourceData $sourceData): string
    {
        return $sourceData->getOptions()->getCldrRepositoryDirectory() . '/common/supplemental/subdivisions.xml';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Punic\DataBuilder\Build\Converter::getXmlPaths()
     */
    protected function getXmlPaths(): array
    {
        return [
            '/supplementalData/subdivisionContainment/subgroup' => ['type'],
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
        $data = parent::process($data);
        foreach (array_keys($data) as $key) {
            $data[$key]['contains'] = explode(' ', $data[$key]['_contains']);
            unset($data[$key]['_contains']);
        }

        return $data;
    }
}
