<?php

namespace Lobby\managers\types;

use Lobby\managers\Manager;
use Lobby\utils\constants\Permissions;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager as PocketminePermissionManager;
use ReflectionClass;

class PermissionManager extends Manager
{
    /**
     * @return void
     */
    public function onLoad(): void
    {
        $permissionsReflectionClass = new ReflectionClass(Permissions::class);
        $permissionManager = PocketminePermissionManager::getInstance();

        foreach ($permissionsReflectionClass->getConstants() as $permissionName) {

            $rootOperator = $permissionManager->getPermission(DefaultPermissions::ROOT_OPERATOR);
            $permission = new Permission($permissionName, "Olympia Kitpvp permission");
            DefaultPermissions::registerPermission($permission, [$rootOperator]);
        }
    }
}
