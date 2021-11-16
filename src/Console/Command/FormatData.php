<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Console\Command;

use FilesystemIterator;
use Generator;
use InvalidArgumentException;
use Punic\DataBuilder\Console\Command;
use Punic\DataBuilder\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FormatData extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName('data:format')
            ->setDescription('Format folders containing Punic PHP data files in JSON format')
            ->addArgument('destination', InputArgument::REQUIRED, 'The path to a directory where the converted data will be saved')
            ->addArgument('source', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The path of the folder(s) containing the Punic PHP data')
            ->setHelp(
                <<<'EOT'
The Punic data (usually located in the docs folder) is in compressed PHP format.
In addition, the data may be "symlinked".
This makes comparing different folders very hard.

You can use this command to read the Punic data contained in one or more folders, and convert it to an uncompressed JSON format.
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = $this->container->make(Filesystem::class);
        $fs->checkSymlinker();
        $destinationFolder = $this->getFolder($fs, $input->getArgument('destination'));
        $sourceFolders = $this->getFolders($fs, $input->getArgument('source'));
        foreach ($sourceFolders as $index => $sourceFolder) {
            $this->formatFolder($fs, $sourceFolder, $destinationFolder . '/output-' . ($index + 1));
        }
        return $this::FAILURE;
    }

    protected function formatFolder(Filesystem $fs, string $sourceFolder, string $destinationFolder): void
    {
        if (is_dir($destinationFolder)) {
            $fs->deleteDirectory($destinationFolder);
        }
        $fs->createDirectory($destinationFolder);
        foreach ($this->listDataFiles($sourceFolder) as $file) {
            $this->formatFile($fs, $sourceFolder, $destinationFolder, $file);
        }
    }

    protected function formatFile(Filesystem $fs, string $sourceFolder, string $destinationFolder, string $file): void
    {
        $sourceFile = "{$sourceFolder}/{$file}";
        if (is_link($sourceFile)) {
            $actualSourceFile = $fs->resolvePath($fs->getLinkTarget($sourceFile), dirname($sourceFile));
        } else {
            $sourceData = $fs->getFileContents($sourceFile);
            if (strpos($sourceData, "\n") === false && preg_match('_^\.\.?/_', $sourceData)) {
                $actualSourceFile = $fs->resolvePath($sourceData, dirname($sourceFile));
            } else {
                $actualSourceFile = $sourceFile;
            }
        }
        $sourceData = $this->readSourceData($actualSourceFile);
        $sourceData = $this->sortSourceData($sourceData);
        $sourceJson = json_encode($sourceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_LINE_TERMINATORS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (defined('JSON_THROW_ON_ERROR') ? JSON_THROW_ON_ERROR : 0));
        $lastSlashPosition = strrpos($file, '/');
        if ($lastSlashPosition !== false) {
            $fullDestinationFolder = $destinationFolder . '/' . substr($file, 0, $lastSlashPosition);
            if (!is_dir($fullDestinationFolder)) {
                $fs->createDirectory($fullDestinationFolder, true);
            }
        }
        $fs->setFileContents("{$destinationFolder}/" . preg_replace('/\.php$/i', '.json', $file), $sourceJson);
    }

    protected function readSourceData(string $path): array
    {
        return require $path;
    }

    protected function sortSourceData(array $data): array
    {
        $numKeys = count($data);
        if ($numKeys === 0) {
            return [];
        }
        $keys = array_keys($data);
        if ($keys !== range(0, $numKeys - 1)) {
            ksort($data, SORT_NATURAL);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortSourceData($value);
            }
        }
        return $data;
    }

    /**
     * @return \Generator|string[]
     */
    protected function listDataFiles(string $sourceFolder): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $sourceFolder,
                FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $item) {
            $relativePath = substr($item, strlen($sourceFolder) + 1);
            if (preg_match('/.\.php$/i', $relativePath) && is_file($item)) {
                yield $relativePath;
            }
        }
    }

    protected function getFolder(Filesystem $fs, string $path): string
    {
        $absolute = realpath($path);
        if ($absolute === false || !is_dir($absolute)) {
            throw new InvalidArgumentException("The folder '{$path}' does not exist");
        }

        return $fs->normalizePath($absolute, false);
    }

    protected function getFolders(Filesystem $fs, array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            $path = $this->getFolder($fs, $path);
            if (!in_array($path, $paths, true)) {
                $result[] = $path;
            }
        }
        if ($result === []) {
            throw new InvalidArgumentException('No folder paths specified');
        }

        return $result;
    }
}
