<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Docker\PathMapper;

use Illuminate\Contracts\Container\Container;
use Punic\DataBuilder\Docker\PathMapper;

class Factory
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function createPathMapper(string $parentDirectoryInDocker): PathMapper
    {
        return $this->container->make(PathMapper::class, ['parentDirectoryInDocker' => $parentDirectoryInDocker]);
    }
}
