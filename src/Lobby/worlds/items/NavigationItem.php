<?php

namespace Lobby\worlds\items;

use Lobby\utils\constants\GlobalConstants;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class NavigationItem extends Item
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct(new ItemIdentifier(ItemTypeIds::newId()), "Navigation", []);
        $this->setCustomName(TextFormat::RESET. GlobalConstants::PRIMARY_COLOR . "Navigation");
    }

    /**
     * @param Player $player
     * @param Vector3 $directionVector
     * @param array $returnedItems
     * @return ItemUseResult
     */
    public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult
    {
        return ItemUseResult::NONE;
    }
}