<?php

namespace Lobby\worlds\items;

use Lobby\utils\constants\GlobalConstants;
use Lobby\utils\EnderButtCache;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class EnderButtItem extends Item
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct(new ItemIdentifier(ItemTypeIds::newId()), "Ender Butt", []);
        $this->setCustomName(TextFormat::RESET. GlobalConstants::PRIMARY_COLOR . "Ender Butt");
    }

    /**
     * @param Player $player
     * @param Vector3 $directionVector
     * @param array $returnedItems
     * @return ItemUseResult
     */
    public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult
    {
        $lastEnderButt = EnderButtCache::getInstance()->getEnderButt($player);
        if(!is_null($lastEnderButt)) {
            if(!$lastEnderButt->isAlive() or !$lastEnderButt->isClosed()){
                $lastEnderButt->flagForDespawn();
            }
            EnderButtCache::getInstance()->removeEnderButt($player);
        }

        $entity = new EnderPearl(Location::fromObject($player->getEyePos(), $player->getWorld(), $player->getLocation()->getYaw(), $player->getLocation()->getPitch()), $player);
        $entity->setMotion($entity->getDirectionVector()->multiply(2));
        $entity->spawnToAll();
        $entity->setScale(0.6);

        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, true);
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, true);

        $player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, 1.5, 0), true);

        $data = new EntityLink($entity->getId(), $player->getId(), EntityLink::TYPE_RIDER, true, true);
        $pk = SetActorLinkPacket::create($data);
        $entity->getWorld()->broadcastPacketToViewers($entity->getPosition(), $pk);

        EnderButtCache::getInstance()->setEnderButt($player, $entity);

        return ItemUseResult::NONE;
    }
}