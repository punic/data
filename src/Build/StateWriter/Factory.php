<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\StateWriter;

use Punic\DataBuilder\Build\SourceData;
use Punic\DataBuilder\Build\StateWriter;
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
    public function createStateWriter(SourceData $sourceData): StateWriter
    {
        return $this->container->make(
            StateWriter::class,
            [
                'sourceData' => $sourceData,
            ]
        );
    }
}
