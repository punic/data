<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Build\Converter\Supplemental;

use Punic\DataBuilder\Build\Converter\Supplemental;

class Noop extends Supplemental
{
    public function __construct(array $roots, ?string $identifier = null)
    {
        parent::__construct('supplemental', $roots, $identifier);
    }
}
