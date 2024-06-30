<?php

namespace Lobby\libraries\olympia;

use Lobby\libraries\olympia\tasks\QueryAsyncRequestTask;
use pocketmine\Server;

class Query
{
    /**
     * @param string $host
     * @param int $port
     * @param \Closure $callback
     * @return void
     */
    public static function create(string $host, int $port, \Closure $callback): void
    {
        QueryAsyncRequestTask::$callable["$host:$port"] = $callback;
        $task = new QueryAsyncRequestTask($host, $port);
        Server::getInstance()->getAsyncPool()->submitTask($task);
    }
}