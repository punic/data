<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Console;

use Illuminate\Contracts\Container\Container;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    public function __construct(Container $container, bool $registerCommands = true)
    {
        parent::__construct('PunicData');
        $this->container = $container;
        if ($registerCommands) {
            $this->registerCommands();
        }
    }

    public function registerCommands(): void
    {
        $this->addCommands([
            $this->container->make(Command\CreateMvnSettings::class),
            $this->container->make(Command\BuildData::class),
            $this->container->make(Command\SymlinkData::class),
        ]);
    }
}
