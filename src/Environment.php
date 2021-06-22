<?php

declare(strict_types=1);

namespace Punic\DataBuilder;

class Environment
{
    /**
     * @var string
     */
    private $projectRootDirectory;

    public function __construct()
    {
        $this->projectRootDirectory = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
    }

    /**
     * Get the full path to the project root directory.
     */
    public function getProjectRootDirectory(): string
    {
        return $this->projectRootDirectory;
    }

    /**
     * Get the full path of the default data directory.
     */
    public function getDefaultDataDirectory(): string
    {
        return $this->getProjectRootDirectory() . '/docs';
    }

    /**
     * Get the full path to the MVN settings file.
     */
    public function getMvnSettingsFilePath(): string
    {
        return $this->getProjectRootDirectory() . '/mvn-settings.xml';
    }
}
