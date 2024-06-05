<?php

namespace Lobby\managers\types;

use Lobby\managers\Manager;
use Lobby\utils\constants\ItemsIds;
use Lobby\utils\constants\Permissions;
use Lobby\utils\ItemUtil;
use Lobby\worlds\items\EnderButtItem;
use Lobby\worlds\items\NavigationItem;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager as PocketminePermissionManager;
use ReflectionClass;

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
    }
}
