<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Console;

use Illuminate\Contracts\Container\Container;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class Command extends SymfonyCommand
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    public function __construct(Container $container, ?string $name = null)
    {
        $this->container = $container;
        parent::__construct($name);
    }
}
