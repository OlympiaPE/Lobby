<?php

namespace Lobby;

use Lobby\handlers\Handlers;
use Lobby\libraries\CortexPE\Commando\exception\HookAlreadyRegistered;
use Lobby\libraries\CortexPE\Commando\PacketHooker;
use Lobby\libraries\muqsit\invmenu\InvMenuHandler;
use Lobby\libraries\slq\Hikabrain\data\DataBase;
use Lobby\libraries\slq\Hikabrain\trait\InitComponent;
use Lobby\managers\Managers;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

class Loader extends PluginBase
{
    use SingletonTrait;

    /**
     * @return void
     */
    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    /**
     * @return void
     * @throws HookAlreadyRegistered
     */
    protected function onEnable(): void
    {
        // Registering libraries
        PacketHooker::register($this);
        InvMenuHandler::register($this);

        Managers::load();
        Handlers::load();
    }

    protected function onDisable(): void
    {
        Managers::save();
    }

    /**
     * @return string
     */
    public function getFile(): string
    {
        return parent::getFile();
    }
}
