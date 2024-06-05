<?php

namespace Lobby\listeners;

use Lobby\entities\Session;
use Lobby\events\PlayerLeaveVehicleEvent;
use Lobby\libraries\SenseiTarzan\ExtraEvent\Class\EventAttribute;
use pocketmine\entity\Entity;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\player\Player;

class PacketListener
{
    /**
     * @param PlayerCreationEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onHandleReceivePacket(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        $player = ($origin = $event->getOrigin())->getPlayer();

        if($player instanceof Player) {
            if($packet instanceof InteractPacket) {
                if($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE) {
                    $entity = $player->getWorld()->getEntity($packet->targetActorRuntimeId);
                    if($entity instanceof Entity) {
                        $ev = new PlayerLeaveVehicleEvent($player, $entity);
                        $ev->call();
                    }
                }
            }
        }
    }
}