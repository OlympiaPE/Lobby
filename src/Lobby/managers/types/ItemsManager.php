<?php

namespace Lobby\managers\types;

use Lobby\managers\Manager;
use Lobby\utils\constants\ItemsIds;
use Lobby\utils\ItemUtil;
use Lobby\worlds\items\EnderButtItem;
use Lobby\worlds\items\GameItem;
use Lobby\worlds\items\NavigationItem;
use pocketmine\data\bedrock\item\ItemTypeNames;

class ItemsManager extends Manager
{
    /**
     * @return void
     * @throws \ReflectionException
     */
    public function onLoad(): void
    {
        ItemUtil::clone(new NavigationItem(), ItemTypeNames::COMPASS, ItemsIds::NAVIGATION);
        ItemUtil::clone(new EnderButtItem(), ItemTypeNames::ENDER_PEARL, ItemsIds::ENDER_BUTT);
        ItemUtil::clone(new GameItem(), ItemTypeNames::BOOK, ItemsIds::GAME);
    }
}
