<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\SourceData;

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
    public function createSourceData(Options $options): SourceData
    {
        return $this->container->make(SourceData::class, ['options' => $options]);
    }
}
