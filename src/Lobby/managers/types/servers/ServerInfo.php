<?php

namespace Lobby\managers\types\servers;

class ServerInfo
{
    protected int $players = 0;

    public function __construct(
        protected string $name,
        protected string $address,
        protected int $port,
        protected string $publicAddress,
        protected int $publicPort,
        protected string $path
    ) {}

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPublicAddress(): string
    {
        return $this->publicAddress;
    }

    /**
     * @return int
     */
    public function getPublicPort(): int
    {
        return $this->publicPort;
    }

    /**
     * @return bool
     */
    public function hasPath(): bool
    {
        return $this->path !== "";
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return int
     */
    public function getPlayers(): int
    {
        return $this->players;
    }

    /**
     * @param int $players
     */
    public function setPlayers(int $players): void
    {
        $this->players = $players;
    }
}