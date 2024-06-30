<?php

namespace Lobby\managers\types;

use Lobby\commands\SpawnCommand;
use Lobby\Loader;
use Lobby\managers\Manager;

class CommandManager extends Manager
{
    /**
     * @return void
     */
    public function onLoad(): void
    {
        Loader::getInstance()->getServer()->getCommandMap()->register("spawn", new SpawnCommand(Loader::getInstance(), "spawn", "Se téléporté au spawn!"));
    }
}
