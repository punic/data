<?php

declare(strict_types=1);

namespace Punic\DataBuilder;

use Illuminate\Container\Container as IlluminateContainerImplementation;
use Illuminate\Contracts\Container\Container as IlluminateContainerInterface;

class Container extends IlluminateContainerImplementation
{
    public function __construct()
    {
        $this->singleton(self::class);
        $this->instance(self::class, $this);
        $this->alias(self::class, IlluminateContainerImplementation::class);
        $this->alias(self::class, IlluminateContainerInterface::class);
        $this->singleton(Environment::class);
    }
}
