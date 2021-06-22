<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\ConverterManager;

use Punic\DataBuilder\Build\ConverterManager;
use Punic\DataBuilder\Build\DataWriter;
use Punic\DataBuilder\Build\Options;
use Punic\DataBuilder\Build\SourceData;
use Punic\DataBuilder\Container;

class Factory
{
    /**
     * @var \Punic\DataBuilder\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @throws \RuntimeException
     */
    public function createConverterManager(SourceData $sourceData): ConverterManager
    {
        return $this->container->make(
            ConverterManager::class,
            [
                'sourceData' => $sourceData,
                'dataWriter' => $this->createDataWriter($sourceData->getOptions()),
            ]
        );
    }

    protected function createDataWriter(Options $options): DataWriter
    {
        return $this->container->make(DataWriter\Php::class, [
            'flags' => $options->isPrettyOutput() ? DataWriter\Php::FLAG_PRETTYOUTPUT : 0,
        ]);
    }
}
