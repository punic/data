<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Traits;

use RuntimeException;

trait Shell
{
    /**
     * Escape an string so that it's safe to be used as a shell argument.
     */
    protected function escapeShellArg(string $arg): string
    {
        if (preg_match('/^[[:alnum:]\-_\/' . preg_quote(DIRECTORY_SEPARATOR, '/') . '=.,:;\[\]()?]+$/u', $arg)) {
            return $arg;
        }

        return escapeshellarg($arg);
    }

    /**
     * Execute a command and return its output (if $passthru is false).
     *
     * @param string[] $arguments
     * @param int[] $validExitCodes
     *
     * @throws \RuntimeException if $validExitCodes is not an empty array and it does not contain the command exit code
     *
     * @return string[]
     */
    protected function shell(string $command, array $arguments, bool $passthru = false, array $validExitCodes = [0]): array
    {
        $fullCommandLine = implode(
            ' ',
            array_map(
                function (string $chunk): string {
                    return $this->escapeShellArg($chunk);
                },
                array_merge([$command], $arguments)
            )
        );

        return $this->shellRaw($fullCommandLine, $passthru, $validExitCodes);
    }

    /**
     * Execute a command (without any escaping) and return its output (if $passthru is false).
     *
     * @param int[] $validExitCodes
     *
     * @throws \RuntimeException if $validExitCodes is not an empty array and it does not contain the command exit code
     *
     * @return string[]
     */
    protected function shellRaw(string $fullCommandLine, bool $passthru = false, array $validExitCodes = [0]): array
    {
        $output = [];
        $rc = -1;
        if ($passthru) {
            passthru($fullCommandLine, $rc);
        } else {
            exec("{$fullCommandLine} 2>&1", $output, $rc);
        }
        if ($validExitCodes !== [] && !in_array($rc, $validExitCodes, true)) {
            if ($passthru) {
                throw new RuntimeException("The command failed with the exit code {$rc}");
            }
            $message = trim(implode("\n", $output));
            throw new RuntimeException($message === '' ? "The command failed with the exit code {$rc}" : $message);
        }

        return $output;
    }
}
