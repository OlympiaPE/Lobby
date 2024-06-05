<?php

namespace Lobby\entities;

use Lobby\utils\constants\ItemsIds;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;

class Session extends Player
{
    /**
     * @return void
     */
    public function setKit(): void
    {
        $this->clear();
        $this->getInventory()->setContents([
            3 => StringToItemParser::getInstance()->parse(ItemsIds::NAVIGATION),
            5 => StringToItemParser::getInstance()->parse(ItemsIds::ENDER_BUTT)
        ]);
    }

    /**
     * @return void
     */
    public function spawn(): void
    {
        $this->setKit();
        $this->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->setHealth(20);
        $this->getCursorInventory()->clearAll();
        $this->getArmorInventory()->clearAll();
        $this->getCraftingGrid()->clearAll();
        $this->getEnderInventory()->clearAll();
        $this->getInventory()->clearAll();
        $this->getOffHandInventory()->clearAll();
    }
}