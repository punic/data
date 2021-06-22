<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Locale;

use Punic\DataBuilder\Build\Converter\Locale;

class Noop extends Locale
{
    public function __construct(array $roots, ?string $identitifier = null)
    {
        parent::__construct('main', $roots, $identitifier);
    }
}
