<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Traits;

use Closure;

trait SilentCaller
{
    /**
     * Invoke a callback, intercepting warnings thrown by PHP.
     *
     * @return array the first element is the result of $callback, the second error is the intercepted error
     */
    protected function silentCall(Closure $callback): array
    {
        $error = 'Unknown error';
        set_error_handler(
            static function ($errno, $errstr) use (&$error) {
                $errstr = trim((string) $errstr);
                if ($errstr !== '') {
                    $error = $errstr;
                }
            },
            -1
        );
        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        return [$result, $error];
    }
}
