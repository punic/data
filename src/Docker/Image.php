<?php

declare(strict_types=1);

namespace Punic\DataBuilder\Docker;

class Image
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $repository;

    /**
     * @var string
     */
    private $tag;

    public function __construct(string $id, string $repository, string $tag)
    {
        $this->id = $id;
        $this->repository = $repository;
        $this->tag = $tag;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getReference(): string
    {
        return $this->getRepository() . ':' . $this->getTag();
    }
}
