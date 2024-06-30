<?php

namespace Lobby\libraries\olympia\tasks;

use Lobby\libraries\jasonw4331\libpmquery\PMQuery;
use Lobby\libraries\jasonw4331\libpmquery\PmQueryException;
use Lobby\managers\Managers;
use Lobby\managers\types\servers\ServerInfo;
use pocketmine\scheduler\AsyncTask;

class QueryAsyncRequestTask extends AsyncTask
{
    public static array $callable = [];

    public function __construct(
        protected string $host,
        protected string $port,
    ) {}

    public function onRun(): void
    {
        try {
            $queryData = PMQuery::query($this->host, $this->port);
            $this->setResult($queryData);
        } catch (PmQueryException $e) {
            $this->setResult(null);
        }
    }

    /**
     * @return void
     */
    public function onCompletion(): void
    {
        $serverInfo = Managers::SERVERS()->getServerByPublicInfo($this->host, $this->port);
        if($serverInfo instanceof ServerInfo && isset(self::$callable["$this->host:$this->port"])) {
            self::$callable["$this->host:$this->port"]($serverInfo, $this->getResult());
        }
    }
}