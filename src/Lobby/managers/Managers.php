<?php

namespace Lobby\managers;

use Lobby\Loader;
use Lobby\managers\types\CommandManager;
use Lobby\managers\types\ItemsManager;
use Lobby\managers\types\ListenerManager;
use Lobby\managers\types\PermissionManager;
use Lobby\managers\types\ServersManager;
use pocketmine\utils\RegistryTrait;
use ReflectionClass;

/**
 * @method static PermissionManager PERMISSION()
 * @method static CommandManager COMMAND()
 * @method static ListenerManager LISTENER()
 * @method static ItemsManager ITEMS()
 * @method static ServersManager SERVERS()
 */
class Managers
{
    use RegistryTrait;

    private function __construct() {}

    /**
     * @param string $name
     * @param object $manager
     * @return void
     */
    protected static function register(string $name, Manager $manager) : void{
        $manager->onLoad();
        self::_registryRegister($name, $manager);
    }

    /**
     * @return array
     */
    public static function getAll() : array{
        return self::_registryGetAll();
    }

    /**
     * @return void
     */
    public static function load(): void
    {
        self::checkInit();
    }

    /**
     * @return void
     */
    protected static function setup(): void
    {
        $reflectionClass = new ReflectionClass(self::class);
        $namespace = $reflectionClass->getNamespaceName();
        $docComment = $reflectionClass->getDocComment();

        $matches = [];
        preg_match_all('/@method\s+static\s+(\S+)\s+([^\s()]+)\(\)/', $docComment, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {

            $className = $match[1];
            $managerClass = "\\$namespace\\types\\$className";
            $manager = new $managerClass();

            if ($manager instanceof Manager) {
                self::register(strtolower($match[2]), $manager);
            }else{
                Loader::getInstance()->getLogger()->error("[Manager] The $className class does not inherit from Manager !");
            }
        }
    }

    /**
     * @return void
     */
    public static function save(): void
    {
        foreach (self::getAll() as $manager) {
            if ($manager instanceof Manager && $manager->isRequireSaveOnDisable()) {
                $manager->save();
            }
        }
    }
}
