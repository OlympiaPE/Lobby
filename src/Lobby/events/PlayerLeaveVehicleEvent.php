<?php

namespace Lobby\events;

use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class PlayerLeaveVehicleEvent extends PlayerEvent
{
    public function __construct(
        Player $player,
        protected Entity $entity
    ){
        $this->player = $player;
    }

    /**
     * @return Entity
     */
    public function getEntity(): Entity
    {
        return $this->entity;
    }
}