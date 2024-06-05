<?php

namespace Lobby\managers\types;

use Lobby\libraries\olympia\Query;
use Lobby\Loader;
use Lobby\managers\Manager;
use Lobby\managers\types\servers\ServerInfo;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use Symfony\Component\Filesystem\Path;

class ServersManager extends Manager
{
    protected Config $config;

    /** @var ServerInfo[] */
    protected array $servers = [];

    /**
     * @return void
     */
    public function onLoad(): void
    {
        $this->config = new Config(Path::join(Loader::getInstance()->getDataFolder(), "servers.json"), Config::JSON);
        $this->config->save();
        foreach ($this->config->getAll() as $item) {
            $this->servers[strtolower($item["name"])] = new ServerInfo($item["name"], $item["address"], $item["port"], $item["publicAddress"], $item["publicPort"], $item["path"] ?? "");
        }

        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach ($this->servers as $serverInfo) {
                Query::create($serverInfo->getPublicAddress(), $serverInfo->getPublicPort(), fn(ServerInfo $info, mixed $data) => $info->setPlayers($data["Players"] ?? 0));
            }
        }), 20);
    }

    /**
     * @param string $name
     * @return ServerInfo|null
     */
    public function getServerByName(string $name): ?ServerInfo
    {
        return $this->servers[strtolower($name)] ?? null;
    }

    /**
     * @param string $host
     * @param int $port
     * @return ServerInfo|null
     */
    public function getServerByPublicInfo(string $host, int $port): ?ServerInfo
    {
        foreach ($this->servers as $serverInfo) {
            if($serverInfo->getPublicAddress() === $host && $serverInfo->getPublicPort() === $port) {
                return $serverInfo;
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public function getServers(): array
    {
        return $this->servers;
    }
}
