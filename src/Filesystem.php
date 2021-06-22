<?php

declare(strict_types=1);

namespace Punic\DataBuilder;

use Closure;
use InvalidArgumentException;
use RuntimeException;

class Filesystem
{
    use Traits\Shell;

    use Traits\SilentCaller;

    /**
     * @var \Closure|null
     */
    private $symlinker;

    /**
     * Normalize a path, removing unnecessary trailing path separators (and replacing '\' with '/' on Windows, ).
     *
     * @throws \InvalidArgumentException if $path is empty
     * @throws \InvalidArgumentException if $checkInvalidChars is true and $path contains invalid characters
     */
    public function normalizePath(string $path, bool $checkInvalidChars = true): string
    {
        if ($path === '') {
            throw new InvalidArgumentException('The path is empty');
        }
        $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        if (DIRECTORY_SEPARATOR === '\\' && preg_match('_^[A-Za-z]:/$_', $normalizedPath)) {
            $trimmedPath = rtrim($normalizedPath, '/');
            $normalizedPath = preg_match('_^[A-Za-z]:$_', $trimmedPath) ? "{$trimmedPath}/" : $trimmedPath;
        } else {
            $trimmedPath = rtrim($normalizedPath, '/');
            $normalizedPath = $trimmedPath === '' ? "{$trimmedPath}/" : $trimmedPath;
        }
        if ($checkInvalidChars) {
            if (DIRECTORY_SEPARATOR === '\\') {
                $invalidChars = implode('', array_map('chr', range(0, 31))) . '*?"<>|';
            } else {
                $invalidChars = '';
            }
            if ($invalidChars !== '') {
                $invalidSubstring = strpbrk($normalizedPath, $invalidChars);
                if ($invalidSubstring !== false) {
                    $invalidChar = $invalidSubstring[0];
                    if (asc($invalidChar) >= asc(' ')) {
                        $invalidChar = "'{$invalidChar}'";
                    } else {
                        $invalidChar = '0x' . dechex(asc($invalidChar));
                    }
                    throw new InvalidArgumentException("The path '{$path}' contains the invalid characted {$invalidChar}");
                }
            }
        }

        return $normalizedPath;
    }

    /**
     * Check if a path is absolute.
     */
    public function isPathAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        if (strpos($path, '//') === 0) {
            return true;
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            return (bool) preg_match('_^[A-Za-z]:/_', $path);
        }
        return $path[0] === '/';
    }

    /**
     * Normalize a path and make it absolute.
     *
     * @throws \InvalidArgumentException if $path is empty
     * @throws \InvalidArgumentException if $checkInvalidChars is true and $path contains invalid characters
     * @throws \RuntimeException if we can't retrieve the current directory
     */
    public function makePathAbsolute(string $path, bool $checkInvalidChars = true): string
    {
        $normalizedPath = $this->normalizePath($path, $checkInvalidChars);
        if ($this->isPathAbsolute($normalizedPath)) {
            return $normalizedPath;
        }
        if (DIRECTORY_SEPARATOR === '\\' && preg_match('_^[A-Za-z]:$_', $normalizedPath)) {
            $absolutePath = rtrim($this->getCurrentDirectoryOfWindowsDrive($normalizedPath[0]), '/') . '/' . substr($normalizedPath, 2);
        } else {
            $absolutePath = rtrim($this->getCurrentDirectory(), '/') . '/' . $normalizedPath;
        }
        $chunks = preg_split('_/+_', $absolutePath, -1, PREG_SPLIT_NO_EMPTY);
        if ($chunks === []) {
            return '/';
        }
        if (preg_match('/^[A-Za-z]:$/', $chunks[0])) {
            $prefix = array_shift($chunks);
        } else {
            $matches = null;
            preg_match('_^(/*)/_', $absolutePath, $matches);
            $prefix = $matches[1];
        }
        $suffix = array_reduce(
            $chunks,
            static function (?string $carry, string $item): string {
                if ($item === '.') {
                    return $carry;
                }
                if ($carry === null) {
                    $carry = '/';
                }
                if ($item === '..') {
                    $lastSlashPosition = strrpos($carry, '/');
                    if ($lastSlashPosition === false) {
                        throw new InvalidArgumentException('Invalid path traversal');
                    }
                    return substr($carry, 0, $lastSlashPosition);
                }
                return $carry === '/' ? "/{$item}" : "{$carry}/{$item}";
            }
        );
        $absolutePath = $prefix . '/' . ltrim($suffix, '/');

        return $absolutePath;
    }

    /**
     * @throws \RuntimeException
     */
    public function resolvePath(string $relativePath, string $referenceDirectory): string
    {
        if ($relativePath === '') {
            return $referenceDirectory;
        }
        if ($this->isPathAbsolute($relativePath)) {
            return $relativePath;
        }
        $relativePathParts = explode('/', $this->normalizePath($relativePath));
        $chunks = explode('/', $this->makePathAbsolute($referenceDirectory));
        while (($part = array_shift($relativePathParts)) !== null) {
            switch ($part) {
                case '.':
                    break;
                case '..':
                    if (array_pop($chunks) === null) {
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
     * Get the current directory.
     *
     * @throws \RuntimeException
     */
    public function getCurrentDirectory(): string
    {
        [$currentDirectory, $error] = $this->silentCall(static function () {
            return getcwd();
        });
        if ($currentDirectory === false) {
            throw new RuntimeException("Failed to determine current directory:\n{$error}");
        }

        return $this->normalizePath($currentDirectory);
    }

    /**
     * Get the current directory.
     *
     * @throws \RuntimeException
     */
    public function setCurrentDirectory(string $path): void
    {
        [$success, $error] = $this->silentCall(static function () use ($path) {
            return chdir($path);
        });
        if (!$success) {
            throw new RuntimeException('Failed to change the current directory to ' . str_replace('/', DIRECTORY_SEPARATOR, $path) . ":\n{$error}");
        }
    }

    /**
     * Create a directory, even if it's not empty.
     *
     * @throws \RuntimeException
     */
    public function createDirectory(string $path, bool $recursive = false): void
    {
        [$ok, $error] = $this->silentCall(static function () use ($path, $recursive) {
            return mkdir($path, 0777, $recursive);
        });
        if (!$ok) {
            throw new RuntimeException('Failed to create the directory ' . str_replace('/', DIRECTORY_SEPARATOR, $path) . "\n:{$error}");
        }
    }

    /**
     * Delete a directory, even if it's not empty.
     *
     * @throws \RuntimeException
     */
    public function deleteDirectory(string $path): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->shell('RMDIR', ['/S', '/Q', str_replace('/', DIRECTORY_SEPARATOR, $path)]);
        } else {
            $this->shell('rm', ['-rf', $path]);
        }
    }

    /**
     * Delete a file or a symbolic link.
     *
     * @throws \RuntimeException
     */
    public function deleteFile(string $path): void
    {
        [$ok, $error] = $this->silentCall(static function () use ($path) {
            return unlink($path);
        });
        if ($ok === false) {
            throw new RuntimeException('Failed to delete the file ' . str_replace('/', DIRECTORY_SEPARATOR, $path) . ":\n{$error}");
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function setFileContents(string $path, string &$contents): void
    {
        [$ok, $error] = $this->silentCall(static function () use ($path, &$contents) {
            return file_put_contents($path, $contents);
        });
        if ($ok === false) {
            throw new RuntimeException('Failed to set the contents of the file ' . str_replace('/', DIRECTORY_SEPARATOR, $path) . ":\n{$error}");
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function getFileContents(string $path): string
    {
        [$contents, $error] = $this->silentCall(static function () use ($path) {
            return file_get_contents($path);
        });
        if ($contents === false) {
            throw new RuntimeException('Failed to get the contents of the file ' . str_replace('/', DIRECTORY_SEPARATOR, $path) . ":\n{$error}");
        }

        return $contents;
    }

    /**
     * @throws \RuntimeException
     */
    public function listDirectoryContents(string $path): array
    {
        [$list, $error] = $this->silentCall(static function () use ($path) {
            return scandir($path);
        });
        if (!is_array($list)) {
            throw new RuntimeException('Failed to list the content of the directory ' . str_replace('/', DIRECTORY_SEPARATOR, $path) . ":\n{$error}");
        }
        $result = [];
        foreach ($list as $item) {
            if ($item !== '.' && $item !== '..') {
                $result[] = $item;
            }
        }

        return $result;
    }

    public function getLinkTarget(string $path): string
    {
        [$target, $error] = $this->silentCall(static function () use ($path) {
            return readlink($path);
        });
        if ($target === false) {
            throw new RuntimeException(sprintf("Failed to read the target of the symbolic link %s:\n%s", str_replace('/', DIRECTORY_SEPARATOR, $path), $error));
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $target);
    }

    /**
     * @throws \RuntimeException
     */
    public function checkSymlinker(): void
    {
        $this->getSymlinker();
    }

    /**
     * @throws \RuntimeException
     */
    public function createSymlink(string $target, string $link): void
    {
        $symlinker = $this->getSymlinker();
        $symlinker($target, $link);
    }

    /**
     * @throws \RuntimeException
     */
    public function getSystemTemporaryDirectory(): string
    {
        foreach ([
            sys_get_temp_dir(),
            getenv('TMP'),
            getenv('TEMP'),
        ] as $eligible) {
            if (!is_string($eligible) || $eligible === '') {
                continue;
            }
            [$ok] = $this->silentCall(static function () use ($eligible) {
                return is_dir($eligible) && is_writable($eligible);
            });
            if (!$ok) {
                continue;
            }
            return $this->makePathAbsolute($eligible);
        }

        throw new RuntimeException('Failed to detect a system temporary directory');
    }

    /**
     * Get the current directory of a specific Windows drive.
     *
     * @throws \RuntimeException
     */
    protected function getCurrentDirectoryOfWindowsDrive(string $driveLetter): string
    {
        try {
            $output = $this->shellRaw("CD {$driveLetter}:");
            $errorSuffix = empty($output[0]) ? '' : null;
        } catch (RuntimeException $x) {
            $errorSuffix = ":\n" . $x->getMessage();
        }
        if ($errorSuffix !== null) {
            throw new RuntimeException("Failed to determine current directory of the Windows drive {$driveLetter}{$errorSuffix}");
        }

        return $this->normalizePath($output[0]);
    }

    /**
     * @throws \RuntimeException
     */
    protected function getSymlinker(): Closure
    {
        if ($this->symlinker === null) {
            $this->symlinker = DIRECTORY_SEPARATOR === '\\' ? $this->buildWindowsSymlinker() : $this->buildPosixSymlinker();
        }

        return $this->symlinker;
    }

    protected function buildPosixSymlinker(): Closure
    {
        return function (string $target, string $link): void {
            [$success, $error] = $this->silentCall(static function () use ($target, $link) {
                return symlink(str_replace('/', DIRECTORY_SEPARATOR, $target), $link);
            });
            if ($success === false) {
                throw new RuntimeException('Failed to create link ' . str_replace('/', DIRECTORY_SEPARATOR, $link) . ' to ' . str_replace('/', DIRECTORY_SEPARATOR, $target) . ":\n{$error}");
            }
        };
    }

    /**
     * @throws \RuntimeException
     */
    protected function buildWindowsSymlinker(): Closure
    {
        foreach ([
            'mklink' => [
                'testArguments' => ['/?'],
                'testRCs' => [0, 1],
                'testOk' => static function (array $output): bool {
                    foreach ($output as $line) {
                        if (preg_match('_^\s*MKLINK\\b_', $line) && strpos($line, '[/D]') !== false && strpos($line, '[/H]') !== false && strpos($line, '[/J]') !== false) {
                            return true;
                        }
                    }
                    return false;
                },
                'runArguments' => ['%2$s', '%1$s'],
            ],
        ] as $program => $args) {
            try {
                $output = $this->shell($program, $args['testArguments'], false, $args['testRCs'] ?? [0]);
            } catch (RuntimeException $x) {
                continue;
            }
            if ($args['testOk']($output) !== true) {
                continue;
            }
            $runArguments = $args['runArguments'];
            return function (string $target, string $link) use ($program, $runArguments): void {
                $arguments = [];
                foreach ($runArguments as $runArgument) {
                    $arguments[] = sprintf($runArgument, str_replace('/', DIRECTORY_SEPARATOR, $target), str_replace('/', DIRECTORY_SEPARATOR, $link));
                }
                $this->shell($program, $arguments);
            };
        }

        throw new RuntimeException('On Windows the symlink() PHP function does not support relative paths, so we have to use MKLINK (but it has not been found).');
    }
}
