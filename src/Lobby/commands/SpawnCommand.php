<?php

namespace Lobby\commands;

use Lobby\entities\Session;
use Lobby\libraries\CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\utils\TextFormat;

class SpawnCommand extends BaseCommand
{

    protected function prepare(): void
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if(!$sender instanceof Session) return;
        $sender->spawn();
    }
}