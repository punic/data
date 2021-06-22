<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Filesystem\VolatileDirectory;

use Punic\DataBuilder\Container;
use Punic\DataBuilder\Filesystem\VolatileDirectory;

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
    public function createVolatileDirectory(): VolatileDirectory
    {
        return $this->container->make(VolatileDirectory::class);
    }
}
