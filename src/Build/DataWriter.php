<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build;

interface DataWriter
{
    /**
     * Save data to a file in PHP format.
     *
     * @throws \RuntimeException in case of errors
     */
    public function save(array $data, string $file): void;
}
