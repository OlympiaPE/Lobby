<?php

namespace Lobby\utils;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

final class EnderButtCache
{
    use SingletonTrait;

    /**
     * @var EnderPearl[]
     */
    protected array $enderButts = [];

    /**
     * @param Player $player
     * @return EnderPearl|null
     */
    public function getEnderButt(Player $player): ?EnderPearl
    {
        return $this->enderButts[$player->getId()] ?? null;
    }

    /**
     * @param Player $player
     * @param EnderPearl $pearl
     * @return void
     */
    public function setEnderButt(Player $player, EnderPearl $pearl): void
    {
        $this->enderButts[$player->getId()] = $pearl;
    }

    /**
     * @param Player $player
     * @return void
     */
    public function removeEnderButt(Player $player): void
    {
        if(isset($this->enderButts[$player->getId()])) {
            unset($this->enderButts[$player->getId()]);
        }
    }
}