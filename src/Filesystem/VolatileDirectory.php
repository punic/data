<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Filesystem;

use Punic\DataBuilder\Filesystem;
use Throwable;

class VolatileDirectory
{
    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    /**
     * @var string|null
     */
    private $path;

    /**
     * @throws \RuntimeException
     */
    public function __construct(Filesystem $filesystem, string $parentDirectory = '')
    {
        $this->filesystem = $filesystem;
        if ($parentDirectory === '') {
            $parentDirectory = $filesystem->getSystemTemporaryDirectory();
        }
        $prefix = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $parentDirectory), '/') . '/volatile-';
        for ($i = 0; ; $i++) {
            $path = $prefix . $i . '-' . uniqid();
            if (file_exists($path)) {
                continue;
            }
            $this->filesystem->createDirectory($path);
            $this->path = $path;
            break;
        }
    }

    /**
     * Clear and delete this volatile directory.
     */
    public function __destruct()
    {
        if ($this->path !== null) {
            try {
                $this->filesystem->deleteDirectory($this->path);
            } catch (Throwable $x) {
            }
            $this->path = null;
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
