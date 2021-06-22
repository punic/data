<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Docker;

class Volume
{
    /**
     * @var string
     */
    private $localPath;

    /**
     * @var string
     */
    private $mappedPath;

    public function __construct(string $localPath, string $mappedPath)
    {
        $this->localPath = $localPath;
        $this->mappedPath = $mappedPath;
    }

    public function getLocalPath(): string
    {
        return $this->localPath;
    }

    public function getMappedPath(): string
    {
        return $this->mappedPath;
    }
}
