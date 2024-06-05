<?php

namespace Lobby\utils;

use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\world\Position;
use ReflectionClass;

class EntitiesUtil
{
    public static function updateStaticPacketCache(string $identifier, string $behaviourId = ""): void {
        $instance = StaticPacketCache::getInstance();
        $staticPacketCache = new ReflectionClass($instance);
        $property = $staticPacketCache->getProperty("availableActorIdentifiers");
        $property->setAccessible(true);
        /** @var AvailableActorIdentifiersPacket $packet */
        $packet = $property->getValue($instance);
        /** @var CompoundTag $root */
        $root = $packet->identifiers->getRoot();
        $idList = $root->getListTag("idlist") ?? new ListTag();
        $idList->push(CompoundTag::create()
            ->setString("id", $identifier)
            ->setString("bid", $behaviourId));
        $packet->identifiers = new CacheableNbt($root);
    }

    /**
     * @param Position $position
     * @param int $base1
     * @param int $base2
     * @return bool
     */
    public static function inInPosByCenter(Position $position, int $base1, int $base2): bool
    {
        return ($position->x <= $base1 and $position->x >= $base2) && ($position->z <= $base1 and $position->z >= $base2);
    }

    /**
     * @param Vector3 $position
     * @param Vector3 $target
     * @return Vector2
     */
    public static function calculateAgreedDirection(Vector3 $position, Vector3 $target): Vector2
    {
        $angle = atan2($position->z - $target->z, $position->x - $target->x);
        $yaw = (($angle * 180) / M_PI) - 90;
        $angle = atan2((new Vector2($target->x, $target->z))->distance(new Vector2($position->x, $position->z)), $position->y - $target->y);
        $pitch = (($angle * 180) / M_PI) - 90;

        return new Vector2($yaw, $pitch);
    }
}