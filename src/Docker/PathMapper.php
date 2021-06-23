<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Docker;

use InvalidArgumentException;
use Punic\DataBuilder\Filesystem;
use RuntimeException;
use Throwable;

class PathMapper
{
    protected const TYPE_DIRECTORY = 'd';

    protected const TYPE_FILE = 'f';

    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    private $parentDirectoryInDockerWithSlash;

    private $paths = [];

    public function __construct(Filesystem $filesystem, string $parentDirectoryInDocker)
    {
        $this->filesystem = $filesystem;
        $parentDirectoryInDocker = trim(str_replace(DIRECTORY_SEPARATOR, '/', $parentDirectoryInDocker), '/');
        $this->parentDirectoryInDockerWithSlash = $parentDirectoryInDocker === '' ? '/' : "/{$parentDirectoryInDocker}/";
        $this->reset();
    }

    /**
     * @return $this
     */
    public function reset(): self
    {
        $this->paths = [];

        return $this;
    }

    /**
     * @throws \InvalidArgumentException if $key is already present
     *
     * @return $this
     */
    public function addLocalDirectory(string $key, string $normalizedPath): self
    {
        if (isset($this->paths[$key])) {
            throw new InvalidArgumentException("Duplicated key: {$key}");
        }
        $this->paths[$key] = [static::TYPE_DIRECTORY, $this->resolveLink($normalizedPath)];
        return $this;
    }

    /**
     * @throws \InvalidArgumentException if $key is already present
     *
     * @return $this
     */
    public function addLocalFile(string $key, string $normalizedPath): self
    {
        if (isset($this->paths[$key])) {
            throw new InvalidArgumentException("Duplicated key: {$key}");
        }
        $this->paths[$key] = [static::TYPE_FILE, $this->resolveLink($normalizedPath)];
        return $this;
    }

    public function process(): PathMapper\Result
    {
        $directories = $this->getMinimumCommonDirectories();
        $volumes = $this->buildVolumeList($directories);
        $mappedPaths = $this->buildMappedPaths($volumes);

        return new PathMapper\Result($volumes, $mappedPaths);
    }

    protected function getParentDirectoryInDockerWithSlash(): string
    {
        return $this->parentDirectoryInDockerWithSlash;
    }

    protected function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * @return string[]
     */
    protected function getDistinctDirectories(): array
    {
        $result = [];
        foreach ($this->getPaths() as $path) {
            switch ($path[0]) {
                case static::TYPE_DIRECTORY:
                    $directory = $path[1];
                    break;
                case static::TYPE_FILE:
                    $p = strrpos($path[1], '/');
                    if ($p === false) {
                        throw new RuntimeException();
                    }
                    $directory = $p === 0 ? '/' : substr($path[1], 0, $p);
                    if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:$/', $directory)) {
                        $directory .= '/';
                    }
                    break;
                default:
                    throw new RuntimeException();
            }
            if (!in_array($directory, $result, true)) {
                $result[] = $directory;
            }
        }

        return $result;
    }

    /**
     * @param string[]|null $distinctDirectories
     *
     * @return string[]
     */
    protected function getMinimumCommonDirectories(?array $distinctDirectories = null): array
    {
        if ($distinctDirectories === null) {
            $distinctDirectories = $this->getDistinctDirectories();
        }
        $result = [];
        foreach ($distinctDirectories as $directory) {
            if ($result === []) {
                $result[] = $directory;
                continue;
            }
            $directoryWithSlash = rtrim($directory, '/') . '/';
            for ($index = count($result) - 1; $index >= 0; $index--) {
                $previousWithSlash = rtrim($result[$index], '/') . '/';
                if (strpos($directoryWithSlash, $previousWithSlash) === 0) {
                    continue 2;
                }
                if (strpos($previousWithSlash, $directoryWithSlash) === 0) {
                    $result[$index] = $directory;
                    continue 2;
                }
            }
            $result[] = $directory;
        }

        return $result;
    }

    /**
     * @param string[] $directories
     */
    protected function buildVolumeList(array $directories): array
    {
        $result = [];
        $parentDirectoryInDockerWithSlash = $this->getParentDirectoryInDockerWithSlash();
        foreach ($directories as $directory) {
            $baseName = basename($directory);
            if (!is_string($baseName) || $baseName === '' || strpbrk($baseName, '/' . DIRECTORY_SEPARATOR) !== false) {
                $baseName = 'vol';
            }
            for ($index = 1; ; $index++) {
                $mappedName = $parentDirectoryInDockerWithSlash . $baseName . ($index === 1 ? '' : $index);
                if (!isset($result[$mappedName])) {
                    $result[$mappedName] = $directory;
                    break;
                }
            }
        }

        return $result;
    }

    protected function buildMappedPaths(array $volumes): array
    {
        $result = [];
        foreach ($this->getPaths() as $key => $path) {
            $mappedPath = '';
            foreach ($volumes as $mappedVolumePath => $localVolumePath) {
                $localVolumePathWithSlash = rtrim($localVolumePath, '/') . '/';
                switch ($path[0]) {
                    case static::TYPE_DIRECTORY:
                        $pathWithSlash = rtrim($path[1], '/') . '/';
                        if (strpos($pathWithSlash, $localVolumePathWithSlash) === 0) {
                            $subdirectory = rtrim(substr($pathWithSlash, strlen($localVolumePathWithSlash)), '/');
                            $mappedPath = $subdirectory === '' ? $mappedVolumePath : "{$mappedVolumePath}/{$subdirectory}";
                            break 2;
                        }
                        break;
                    case static::TYPE_FILE:
                        if (strpos($path[1], $localVolumePathWithSlash) === 0) {
                            $mappedPath = "{$mappedVolumePath}/" . substr($path[1], strlen($localVolumePathWithSlash));
                            break 2;
                        }
                        break;
                    default:
                        throw new RuntimeException();
                }
            }
            if ($mappedPath === '') {
                throw new RuntimeException();
            }
            $result[$key] = $mappedPath;
        }
        return $result;
    }

    protected function resolveLink(string $path): string
    {
        try {
            $target = $this->filesystem->getLinkTarget($path);
            if (file_exists($target)) {
                return $target;
            }
        } catch (Throwable $x) {
        }
        return $path;
    }
}
