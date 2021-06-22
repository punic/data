<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Docker\PathMapper;

class Result
{
    /**
     * @var \Punic\DataBuilder\Docker\Volume[]
     */
    private $volumes;

    /**
     * @var array<string, string>
     */
    private $mappedPaths;

    /**
     * @param \Punic\DataBuilder\Docker\Volume[] $volumes
     * @param array<string, string> $mappedPaths
     */
    public function __construct(array $volumes, array $mappedPaths)
    {
        $this->volumes = $volumes;
        $this->mappedPaths = $mappedPaths;
    }

    /**
     * @return \Punic\DataBuilder\Docker\Volume[]
     */
    public function getVolumes(): array
    {
        return $this->volumes;
    }

    public function getMappedPath(string $key): ?string
    {
        return $this->mappedPaths[$key] ?? null;
    }
}
