<?php

declare(strict_types=1);

namespace Punic\DataBuilder;

use Punic\DataBuilder\Filesystem\VolatileDirectory\Factory as VolatileDirectoryFactory;
use RuntimeException;

class Docker
{
    use Traits\Shell;

    /**
     * \Punic\DataBuilder\Filesystem.
     */
    protected $filesystem;

    /**
     * @var \Punic\DataBuilder\Filesystem\VolatileDirectory\Factory
     */
    protected $volatileDirectoryFactory;

    public function __construct(Filesystem $filesystem, VolatileDirectoryFactory $volatileDirectoryFactory)
    {
        $this->filesystem = $filesystem;
        $this->volatileDirectoryFactory = $volatileDirectoryFactory;
    }

    /**
     * @throws \RuntimeException
     */
    public function checkEnvironment(): void
    {
        $output = [];
        $rc = -1;
        exec('docker --version 2>&1', $output, $rc);
        if ($rc !== 0) {
            throw new RuntimeException('docker is not available');
        }
        $output = [];
        $rc = -1;
        exec('docker ps 2>&1', $output, $rc);
        if ($rc !== 0) {
            throw new RuntimeException('docker is not running');
        }
    }

    /**
     * @throws \RuntimeException
     *
     * @return \Punic\DataBuilder\Docker\Image[]
     */
    public function getImagesByReference(string $reference): array
    {
        $output = $this->shell(
            'docker',
            [
                'images',
                '--all',
                "--filter=reference={$reference}",
                '--format={{.ID}} {{.Repository}} {{.Tag}}',
            ]
        );
        $result = [];
        $matches = null;
        foreach ($output as $line) {
            if (preg_match('/^(?<id>\S+) (?<repository>\S+) (?<tag>\S+)$/', $line, $matches)) {
                $result[] = new Docker\Image($matches['id'], $matches['repository'], $matches['tag']);
            }
        }
        return $result;
    }

    /**
     * @throws \RuntimeException
     */
    public function deleteImage(Docker\Image $image)
    {
        $this->shell(
            'docker',
            [
                'rmi',
                '--force',
                $image->getId(),
            ]
        );
    }

    /**
     * @throws \RuntimeException
     */
    public function ensureImage(string $repository, string $dockerfileContents, bool $deleteOtherImages, bool $passthru = false): Docker\Image
    {
        $dockerfileContentsHash = md5($dockerfileContents);
        $dockerImage = null;
        foreach ($this->getImagesByReference($repository) as $di) {
            if ($dockerfileContentsHash === $di->getTag()) {
                $dockerImage = $di;
            } elseif ($deleteOtherImages) {
                $this->deleteImage($di);
            }
        }
        if ($dockerImage !== null) {
            return $dockerImage;
        }
        $directory = $this->volatileDirectoryFactory->createVolatileDirectory();
        $dockerfilePath = $directory->getPath() . '/Dockerfile';
        $this->filesystem->setFileContents($dockerfilePath, $dockerfileContents);
        $this->shell(
            'docker',
            [
                'build',
                "--tag={$repository}:{$dockerfileContentsHash}",
                str_replace('/', DIRECTORY_SEPARATOR, $directory->getPath()),
            ],
            $passthru
        );
        foreach ($this->getImagesByReference($repository) as $di) {
            if ($dockerfileContentsHash === $di->getTag()) {
                return $di;
            }
        }
    }
}
