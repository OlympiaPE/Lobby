<?php

namespace Lobby\listeners;

use Lobby\entities\Session;
use Lobby\events\PlayerLeaveVehicleEvent;
use Lobby\libraries\SenseiTarzan\ExtraEvent\Class\EventAttribute;
use Lobby\libraries\slq\Hikabrain\block\Bed;
use Lobby\libraries\slq\Hikabrain\data\DataBase;
use Lobby\libraries\slq\Hikabrain\game\Game;
use Lobby\libraries\slq\Hikabrain\game\HikabrainTeamFight;
use Lobby\libraries\slq\Hikabrain\game\RushTF;
use Lobby\libraries\slq\Hikabrain\game\utils\Team;
use Lobby\libraries\slq\Hikabrain\inventory\TradeInventory;
use Lobby\utils\EnderButtCache;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\chat\LegacyRawChatFormatter;
use pocketmine\utils\TextFormat;

class SessionListener
{
    /**
     * @param PlayerCreationEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerCreation(PlayerCreationEvent $event): void
    {
        $event->setPlayerClass(Session::class);
    }

    /**
     * @param PlayerJoinEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setJoinMessage("");
        $player->spawn();
    }

    /**
     * @param PlayerQuitEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setQuitMessage("");
    }

    /**
     * @param PlayerLeaveVehicleEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerLeaveVehicle(PlayerLeaveVehicleEvent $event): void
    {
        $player = $event->getPlayer();
        $entity = $event->getEntity();

        if($entity instanceof EnderPearl) {
            EnderButtCache::getInstance()->removeEnderButt($player);
            $entity->kill();
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onDamage(EntityDamageEvent $event): void
    {
        $event->cancel();
    }

    /**
     * @param PlayerChatEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $message = TextFormat::clean($event->getMessage());
        $event->setMessage($message);
        $event->setFormatter(new LegacyRawChatFormatter("§7{%0}: {%1}"));
    }

    /**
     * @param BlockBreakEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        if(!$player->isCreative(true)) $event->cancel();
    }

    /**
     * @param PlayerInteractEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $action = $event->getAction();
        $item = $event->getItem();
        if(!$player->isCreative(true)) $event->cancel();
    }

    /**
     * @param BlockPlaceEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            if(!$player->isCreative(true)) $event->cancel();
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onDrop(PlayerDropItemEvent $event): void
    {
        $player = $event->getPlayer();
        $event->cancel();
    }

    /**
     * @param PlayerExhaustEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onExhaust(PlayerExhaustEvent $event): void
    {
        $player = $event->getPlayer();
        $player->getHungerManager()->setFood(18);
        $player->getHungerManager()->setSaturation(18);
    }
}