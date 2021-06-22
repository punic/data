<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Cldr;

class DraftStatus
{
    /**
     * Draft status: unconfirmed.
     *
     * @var string
     */
    public const UNCONFIRMED = 'unconfirmed';

    /**
     * Draft status: provisional.
     *
     * @var string
     */
    public const PROVISIONAL = 'provisional';

    /**
     * Draft status: contributed.
     *
     * @var string
     */
    public const CONTRIBUTED = 'contributed';

    /**
     * Draft status: approved.
     *
     * @var string
     */
    public const APPROVED = 'approved';

    /**
     * Get all the available statuses.
     *
     * @return string[]
     */
    public static function getAllStatuses()
    {
        return [
            static::UNCONFIRMED,
            static::PROVISIONAL,
            static::CONTRIBUTED,
            static::APPROVED,
        ];
    }
}
