<?php

declare(strict_types=1);

namespace Punic\DataBuilder;

use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use THrowable;

class DataSymlinker
{
    /**
     * Operation: create symlinks.
     *
     * @var int
     */
    protected const OPERATION_CREATE = 1;

    /**
     * Operation: remove symlinks.
     *
     * @var int
     */
    protected const OPERATION_EXPAND = 2;

    /**
     * @var \Punic\DataBuilder\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    protected $filesMD5 = [];

    /**
     * @var array
     */
    protected $symlinks = [];

    /**
     * @var string
     */
    private $dataPath;

    /**
     * @throws \RuntimeException if there's some missing system features
     */
    public function __construct(Filesystem $filesystem, ?OutputInterface $output = null)
    {
        $this->filesystem = $filesystem;
        $this->output = $output ?? new NullOutput();
    }

    /**
     * @throws \InvalidArgumentException if $dataPath is not the path to a writable directory
     * @throws \RuntimeException
     */
    public function compact(string $dataPath): void
    {
        $this->filesystem->checkSymlinker();
        $this->reset($dataPath);
        $this->addDirectory($this->getDataPath(), static::OPERATION_CREATE);
        $this->createSymlinks();
    }

    /**
     * @throws \InvalidArgumentException if $dataPath is not the path to a writable directory
     * @throws \RuntimeException
     */
    public function expand(string $dataPath): void
    {
        $this->reset($dataPath);
        $this->addDirectory($this->getDataPath(), static::OPERATION_EXPAND);
        $this->expandSymlinks();
    }

    protected function getDataPath(): string
    {
        return $this->dataPath;
    }

    /**
     * @throws \InvalidArgumentException if $dataPath is not the path to a writable directory
     */
    protected function reset(string $dataPath): void
    {
        $dataPath = $this->filesystem->makePathAbsolute($dataPath);
        if (!is_dir($dataPath)) {
            throw new \InvalidArgumentException(sprintf('THe directory %s does not exist', str_replace('/', DIRECTORY_SEPARATOR, $dataPath)));
        }
        if (!is_writable($dataPath)) {
            throw new \InvalidArgumentException(sprintf('THe directory %s is not writable', str_replace('/', DIRECTORY_SEPARATOR, $dataPath)));
        }
        $this->filesMD5 = [];
        $this->symlinks = [];
        $this->dataPath = $dataPath;
    }

    /**
     * @throws \RuntimeException
     */
    protected function createSymlinks(): void
    {
        $md5s = array_unique(array_values($this->filesMD5));
        $total = count($md5s);
        if ($total === 0) {
            if (!$this->output->isQuiet()) {
                $this->output->writeln('No files found.');
            }
            return;
        }
        $progress = new ProgressBar($this->output, $total);
        if (!$this->output->isQuiet()) {
            $progress->setFormat('%current%/%max% (%percent:3s%%) %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        }
        $progress->setMessage('Looking for same-content files');
        $progress->start();
        foreach ($md5s as $md5) {
            $progress->advance();
            $similarFiles = array_keys(
                array_filter(
                    $this->filesMD5,
                    static function ($value) use ($md5): bool {
                        return $value === $md5;
                    }
                )
            );
            if (count($similarFiles) < 2) {
                continue;
            }
            $contentMap = [];
            foreach ($similarFiles as $similarFile) {
                $contents = $this->filesystem->getFileContents($similarFile);
                if (!isset($contentMap[$contents])) {
                    $contentMap[$contents] = [];
                }
                $contentMap[$contents][] = $similarFile;
            }
            foreach ($contentMap as $sameFiles) {
                $numLinks = count($sameFiles) - 1;
                if ($numLinks < 1) {
                    continue;
                }
                $sameFiles = $this->sortPaths($sameFiles);
                $target = array_shift($sameFiles);
                $links = array_values($sameFiles);
                $oldMessage = $progress->getMessage();
                $progress->setMessage("Creating {$numLinks} symbolic links pointing to " . $this->getDisplayPath($target));
                $progress->display();
                foreach ($links as $link) {
                    $this->createSymlink($link, $target);
                    unset($this->filesMD5[$link]);
                    $this->symlinks[$link] = $target;
                }
                $progress->setMessage($oldMessage);
                $progress->display();
            }
        }
        $progress->finish();
    }

    /**
     * @throws \RuntimeException
     */
    protected function expandSymlinks(): void
    {
        $total = count($this->symlinks);
        if ($total === 0) {
            if (!$this->output->isQuiet()) {
                $this->output->writeln('No symbolic link found.');
            }
            return;
        }
        $progress = new ProgressBar($this->output, $total);
        if (!$this->output->isQuiet()) {
            $progress->setFormat('%current%/%max% (%percent:3s%%) %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        }
        $progress->setMessage('Initializing');
        $progress->start();
        $symlinkPaths = array_keys($this->symlinks);
        while (($symlinkPath = array_shift($symlinkPaths)) !== null) {
            $progress->advance();
            $progress->setMessage('Expanding ' . $this->getDisplayPath($symlinkPath));
            $progress->display();
            $actualFile = $this->getActualTarget($this->symlinks[$symlinkPath]);
            $contents = $this->filesystem->getFileContents($actualFile);
            $this->filesystem->deleteFile($symlinkPath);
            $this->filesystem->setFileContents($symlinkPath, $contents);
            unset($this->symlinks[$symlinkPath]);
            $this->filesMD5[$symlinkPath] = true;
        }
        $progress->finish();
    }

    /**
     * @throws \RuntimeException
     */
    protected function addDirectory(string $fullDirectoryPath, int $operation): void
    {
        if (!$this->output->isQuiet()) {
            $this->output->write('Reading directory ' . $this->getDisplayPath($fullDirectoryPath) . '... ');
        }
        $subdirectories = [];
        foreach ($this->filesystem->listDirectoryContents($fullDirectoryPath) as $item) {
            $fullItemPath = rtrim($fullDirectoryPath, '/') . '/' . $item;
            if (is_dir($fullItemPath)) {
                $subdirectories[] = $fullItemPath;
            } else {
                $this->addFile($fullItemPath, $operation);
            }
        }
        if (!$this->output->isQuiet()) {
            $this->output->writeln('done.');
        }
        foreach ($subdirectories as $subdirectory) {
            $this->addDirectory($subdirectory, $operation);
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function addFile(string $fullPath, int $operation): void
    {
        if (!preg_match('/.\.php$/i', $fullPath)) {
            return;
        }
        $target = null;
        if (is_link($fullPath)) {
            $target = $this->filesystem->getLinkTarget($fullPath);
            $target = $this->filesystem->resolvePath($target, dirname($fullPath));
        } else {
            $contents = $this->filesystem->getFileContents($fullPath);
            if (strpos($contents, "\n") === false && preg_match('_^\.\.?/_', $contents)) {
                $target = $this->filesystem->resolvePath($contents, dirname($fullPath));
            } else {
                if ($operation === static::OPERATION_CREATE) {
                    $md5 = md5($contents);
                    if ($md5 === false) {
                        throw new RuntimeException('Failed to calculate MD5 of the file ' . str_replace('/', DIRECTORY_SEPARATOR, $fullPath));
                    }
                } else {
                    $md5 = true;
                }
                $this->filesMD5[$fullPath] = $md5;
            }
        }
        if ($target !== null) {
            if (!is_file($target)) {
                throw new RuntimeException(str_replace(DIRECTORY_SEPARATOR, '/', $fullPath) . ' links to non existing ' . str_replace(DIRECTORY_SEPARATOR, '/', $target));
            }
            $this->symlinks[$fullPath] = $target;
        }
    }

    protected function getDisplayPath(string $path): string
    {
        $result = $path;
        $dataPath = $this->getDataPath();
        if ($path !== $dataPath) {
            $prefix = $dataPath . '/';
            if (strpos($path, $prefix) === 0) {
                $result = substr($path, strlen($prefix));
            }
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $result);
    }

    /**
     * @throws \RuntimeException
     */
    protected function resolvePath(string $relativePath, string $referenceDirectory): string
    {
        if ($relativePath !== '') {
            if ($relativePath[0] === '/') {
                $result = $relativePath;
            }
            if (DIRECTORY_SEPARATOR === '\\' && preg_match('_^[A-Z]:/_i', $relativePath)) {
                return $relativePath;
            }
        }
        $relativePathParts = explode('/', $relativePath);
        $result = explode('/', $referenceDirectory);
        while (($part = array_shift($relativePathParts)) !== null) {
            switch ($part) {
                case '.':
                    break;
                case '..':
                    if (array_pop($result) === null) {
                        throw new RuntimeException('Too many parent paths (..).');
                    }
                    break;
                default:
                    $result[] = $part;
                    break;
            }
        }

        return implode('/', $result);
    }

    /**
     * @param string[] $paths
     *
     * @return string[]
     */
    protected function sortPaths(array $paths): array
    {
        $keyed = [];
        foreach ($paths as $path) {
            $keyed[$path] = str_replace('/', ' ', $path);
        }
        asort($keyed);

        return array_keys($keyed);
    }

    /**
     * @throws \RuntimeException
     */
    protected function getActualTarget(string $target): string
    {
        if (isset($this->filesMD5[$target])) {
            return $target;
        }
        if (isset($this->symlinks[$target])) {
            return $this->getActualTarget($this->symlinks[$target]);
        }
        throw new RuntimeException("The link target {$target} is outside of the data directory.");
    }

    protected function getTargetRelativePath(string $link, string $target): string
    {
        $linkParts = explode('/', $link);
        $targetParts = explode('/', $target);
        while (($linkPart = array_shift($linkParts)) !== null) {
            if ($targetParts[0] === $linkPart) {
                array_shift($targetParts);
                $targetParts = array_values($targetParts);
                continue;
            }
            $numLinkParts = count($linkParts);
            if ($numLinkParts === 0) {
                $targetParts[0] = './' . $targetParts[0];
            } else {
                $padLength = count($targetParts) + $numLinkParts;
                $targetParts = array_pad($targetParts, -$padLength, '..');
            }
            break;
        }

        return implode('/', $targetParts);
    }

    /**
     * @throws \RuntimeException
     */
    protected function createSymlink(string $link, string $target): void
    {
        $linkName = basename($link);
        $linkDir = str_replace(DIRECTORY_SEPARATOR, '/', dirname($link));
        $targetRelative = $this->getTargetRelativePath($link, $target);
        $initialDirectory = $this->filesystem->getCurrentDirectory();
        $this->filesystem->setCurrentDirectory($linkDir);
        try {
            if (file_exists($link)) {
                $this->filesystem->deleteFile($link);
            }
            $this->filesystem->createSymlink($targetRelative, $linkName);
        } finally {
            try {
                $this->filesystem->setCurrentDirectory($initialDirectory);
            } catch (Throwable $x) {
            }
        }
        if (!is_link($link)) {
            throw new RuntimeException("Failed to create link {$link} to {$target}");
        }
        try {
            $actualTarget = $this->filesystem->getLinkTarget($link);
        } catch (Throwable $x) {
            try {
                $this->filesystem->deleteFile($link);
            } catch (Throwable $x) {
            }
            throw $x;
        }
        if ($actualTarget !== $target && $actualTarget !== $targetRelative) {
            try {
                $this->filesystem->deleteFile($link);
            } catch (Throwable $x) {
            }
            throw new RuntimeException("Failed to create link {$link} (created as {$actualTarget} instead of {$target} or {$targetRelative}).");
        }
    }
}
