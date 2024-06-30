<?php

namespace Lobby\entities;

use Lobby\libraries\slq\Hikabrain\game\Game;
use Lobby\libraries\slq\Hikabrain\game\HikabrainTeamFight;
use Lobby\libraries\slq\Hikabrain\game\RushTF;
use Lobby\libraries\slq\Hikabrain\game\utils\Team;
use Lobby\libraries\slq\Hikabrain\inventory\TradeInventory;
use Lobby\utils\constants\ItemsIds;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\animation\CriticalHitAnimation;
use pocketmine\entity\Attribute;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerMissSwingEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\MeleeWeaponEnchantment;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\sound\ItemBreakSound;

class Session extends Player
{
    public ?TradeInventory $currentTradeWindow = null;
    private bool $respawnLocked = false;
    public array $lastAttack = [];
    public HikabrainTeamFight|RushTF|null $ownedGame = null;
    public bool $inLife = true;

    /**
     * @return void
     */
    public function setKit(): void
    {
        $this->clear();
        $this->getInventory()->setContents([
            0 => StringToItemParser::getInstance()->parse(ItemsIds::GAME),
            3 => StringToItemParser::getInstance()->parse(ItemsIds::NAVIGATION),
            5 => StringToItemParser::getInstance()->parse(ItemsIds::ENDER_BUTT),
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
    public function knockBack(float $x, float $z, float $force = self::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = self::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : void{
        $bXZ = 0.535;
        $bY = 0.4;

        $f = sqrt($x * $x + $z * $z);
        if ($f <= 0) return;

        if(mt_rand() / mt_getrandmax() > $this->knockbackResistanceAttr->getValue()){
            $f = 1 / $f;
            $motionX = $this->motion->x / 2;
            $motionY = $this->motion->y / 2;
            $motionZ = $this->motion->z / 2;
            $motionX += $x * $f * $bXZ;
            $motionY += $bY;
            $motionZ += $z * $f * $bXZ;

            $this->setMotion(new Vector3($motionX, $motionY > $verticalLimit ? $verticalLimit : $motionY , $motionZ));
        }
    }

    /**
     * @return void
     */
    public function onDeath() : void{
        //Crafting grid must always be evacuated even if keep-inventory is true. This dumps the contents into the
        //main inventory and drops the rest on the ground.
        $this->removeCurrentWindow();

        $ev = new PlayerDeathEvent($this, $this->getDrops(), $this->getXpDropAmount(), null);
        $ev->call();

        if(!$ev->getKeepInventory()){
            foreach($ev->getDrops() as $item){
                $this->getWorld()->dropItem($this->location, $item);
            }

            $clearInventory = fn(Inventory $inventory) => $inventory->setContents(array_filter($inventory->getContents(), fn(Item $item) => $item->keepOnDeath()));
            $this->inventory->setHeldItemIndex(0);
            $clearInventory($this->inventory);
            $clearInventory($this->armorInventory);
            $clearInventory($this->offHandInventory);
            $clearInventory($this->cursorInventory);
        }

        $this->startDeathAnimation();
        $this->respawn();

    }

    public function respawn() : void{
        if($this->server->isHardcore()){
            if($this->kick(KnownTranslationFactory::pocketmine_disconnect_ban(KnownTranslationFactory::pocketmine_disconnect_ban_hardcore()))){ //this allows plugins to prevent the ban by cancelling PlayerKickEvent
                $this->server->getNameBans()->addBan($this->getName(), "Died in hardcore mode");
            }
            return;
        }

        $this->actuallyRespawn();
    }

    protected function actuallyRespawn() : void{
        if($this->respawnLocked){
            return;
        }
        $this->respawnLocked = true;

        $this->logger->debug("Waiting for safe respawn position to be located");
        $spawn = $this->getSpawn();
        $spawn->getWorld()->requestSafeSpawn($spawn)->onCompletion(
            function(Position $safeSpawn) : void{
                if(!$this->isConnected()){
                    return;
                }
                $this->logger->debug("Respawn position located, completing respawn");
                $ev = new PlayerRespawnEvent($this, $safeSpawn);
                $ev->call();

                $realSpawn = Position::fromObject($ev->getRespawnPosition()->add(0.5, 0, 0.5), $ev->getRespawnPosition()->getWorld());
                $this->teleport($realSpawn);

                $this->setSprinting(false);
                $this->setSneaking(false);
                $this->setFlying(false);

                $this->extinguish();
                $this->setAirSupplyTicks($this->getMaxAirSupplyTicks());
                $this->deadTicks = 0;
                $this->noDamageTicks = 20;

                $this->effectManager->clear();
                $this->setHealth($this->getMaxHealth());

                foreach($this->attributeMap->getAll() as $attr){
                    if($attr->getId() === Attribute::EXPERIENCE || $attr->getId() === Attribute::EXPERIENCE_LEVEL){ //we have already reset both of those if needed when the player died
                        continue;
                    }
                    $attr->resetToDefault();
                }

                $this->spawnToAll();
                $this->scheduleUpdate();

                $this->getNetworkSession()->onServerRespawn();
                $this->respawnLocked = false;
            },
            function() : void{
                if($this->isConnected()){
                    $this->getNetworkSession()->disconnectWithError(KnownTranslationFactory::pocketmine_disconnect_error_respawn());
                }
            }
        );
    }

    /**
     * @return void
     */
    public function missSwing(): void
    {
        $ev = new PlayerMissSwingEvent($this);
        $ev->call();
        if(!$ev->isCancelled()){
            $this->broadcastAnimation(new ArmSwingAnimation($this), $this->getViewers());
        }
    }

    public function onUpdate(int $currentTick): bool
    {
        if($currentTick % 2 === 0) {
            if(Game::isInGame($this)) {
                $game = Game::getGame($this);
                if($game instanceof RushTF && $game->isCurrent()) {
                    $team = $game->getPlayerTeam($this);
                    if($team instanceof Team) {
                        if($team->getPoints() === 1) {
                            $this->setMaxHealth(24);
                        } elseif($team->getPoints() >= 2) {
                            $this->setMaxHealth(28);
                        } else $this->setMaxHealth(20);
                    }
                }
            }
        }
        return parent::onUpdate($currentTick);
    }


    /**
     * Attacks the given entity with the currently-held item.
     * TODO: move this up the class hierarchy
     *
     * @return bool if the entity was dealt damage
     */
    public function attackEntity(Entity $entity) : bool{
        if(!$entity->isAlive()){
            return false;
        }
        if($entity instanceof ItemEntity || $entity instanceof Arrow){
            $this->logger->debug("Attempted to attack non-attackable entity " . get_class($entity));
            return false;
        }

        $heldItem = $this->inventory->getItemInHand();
        $oldItem = clone $heldItem;

        $ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $heldItem->getAttackPoints());
        if(!$this->canInteract($entity->getLocation(), 8)){
            $this->logger->debug("Cancelled attack of entity " . $entity->getId() . " due to not currently being interactable");
            $ev->cancel();
        }

        $meleeEnchantmentDamage = 0;
        /** @var EnchantmentInstance[] $meleeEnchantments */
        $meleeEnchantments = [];
        foreach($heldItem->getEnchantments() as $enchantment){
            $type = $enchantment->getType();
            if($type instanceof MeleeWeaponEnchantment && $type->isApplicableTo($entity)){
                $meleeEnchantmentDamage += $type->getDamageBonus($enchantment->getLevel());
                $meleeEnchantments[] = $enchantment;
            }
        }
        $ev->setModifier($meleeEnchantmentDamage, EntityDamageEvent::MODIFIER_WEAPON_ENCHANTMENTS);

        if(!$this->isSprinting() && !$this->isFlying() && $this->fallDistance > 0 && !$this->effectManager->has(VanillaEffects::BLINDNESS()) && !$this->isUnderwater()){
            $ev->setModifier($ev->getFinalDamage() / 4, EntityDamageEvent::MODIFIER_CRITICAL);
        }

        $entity->attack($ev);
        $this->broadcastAnimation(new ArmSwingAnimation($this), $this->getViewers());

        $soundPos = $entity->getPosition()->add(0, $entity->size->getHeight() / 2, 0);
        if($ev->isCancelled()){
            //$this->getWorld()->addSound($soundPos, new EntityAttackNoDamageSound());
            return false;
        }
        //$this->getWorld()->addSound($soundPos, new EntityAttackSound());

        if($ev->getModifier(EntityDamageEvent::MODIFIER_CRITICAL) > 0 && $entity instanceof Living){
            $entity->broadcastAnimation(new CriticalHitAnimation($entity));
        }

        foreach($meleeEnchantments as $enchantment){
            $type = $enchantment->getType();
            assert($type instanceof MeleeWeaponEnchantment);
            $type->onPostAttack($this, $entity, $enchantment->getLevel());
        }

        if($this->isAlive()){
            //reactive damage like thorns might cause us to be killed by attacking another mob, which
            //would mean we'd already have dropped the inventory by the time we reached here
            $returnedItems = [];
            $heldItem->onAttackEntity($entity, $returnedItems);
            $this->returnItemsFromAction($oldItem, $heldItem, $returnedItems);

            $this->hungerManager->exhaust(0.1, PlayerExhaustEvent::CAUSE_ATTACK);
        }

        return true;
    }

    /**
     * @param Item[] $extraReturnedItems
     */
    private function returnItemsFromAction(Item $oldHeldItem, Item $newHeldItem, array $extraReturnedItems) : void{
        $heldItemChanged = false;

        if(!$newHeldItem->equalsExact($oldHeldItem) && $oldHeldItem->equalsExact($this->inventory->getItemInHand())){
            //determine if the item was changed in some meaningful way, or just damaged/changed count
            //if it was really changed we always need to set it, whether we have finite resources or not
            $newReplica = clone $oldHeldItem;
            $newReplica->setCount($newHeldItem->getCount());
            if($newReplica instanceof Durable && $newHeldItem instanceof Durable){
                $newReplica->setDamage($newHeldItem->getDamage());
            }
            $damagedOrDeducted = $newReplica->equalsExact($newHeldItem);

            if(!$damagedOrDeducted || $this->hasFiniteResources()){
                if($newHeldItem instanceof Durable && $newHeldItem->isBroken()){
                    $this->broadcastSound(new ItemBreakSound());
                }
                $this->inventory->setItemInHand($newHeldItem);
                $heldItemChanged = true;
            }
        }

        if(!$heldItemChanged){
            $newHeldItem = $oldHeldItem;
        }

        if($heldItemChanged && count($extraReturnedItems) > 0 && $newHeldItem->isNull()){
            $this->inventory->setItemInHand(array_shift($extraReturnedItems));
        }
        foreach($this->inventory->addItem(...$extraReturnedItems) as $drop){
            //TODO: we can't generate a transaction for this since the items aren't coming from an inventory :(
            $ev = new PlayerDropItemEvent($this, $drop);
            if($this->isSpectator()){
                $ev->cancel();
            }
            $ev->call();
            if(!$ev->isCancelled()){
                $this->dropItem($drop);
            }
        }
    }
}