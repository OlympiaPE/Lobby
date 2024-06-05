<?php

namespace Lobby\listeners;

use Lobby\entities\Session;
use Lobby\events\PlayerLeaveVehicleEvent;
use Lobby\libraries\SenseiTarzan\ExtraEvent\Class\EventAttribute;
use Lobby\utils\EnderButtCache;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;

class SessionListener
{
    /**
     * @param PlayerCreationEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerCreation(PlayerCreationEvent $event): void
    {
        $event->setPlayerClass(Session::class);
    }

    /**
     * @param PlayerJoinEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setJoinMessage("");
        if($player instanceof Session) {
            $player->spawn();
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setQuitMessage("");
    }

    /**
     * @param PlayerLeaveVehicleEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerLeaveVehicle(PlayerLeaveVehicleEvent $event): void
    {
        $player = $event->getPlayer();
        $entity = $event->getEntity();

        if($entity instanceof EnderPearl) {
            EnderButtCache::getInstance()->removeEnderButt($player);
            $entity->kill();
        }
    }
}