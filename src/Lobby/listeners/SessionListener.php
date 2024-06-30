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
use Lobby\utils\reflection\ReflectionUtils;
use pocketmine\block\TNT;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\FlintSteel;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\GameMode as ProtocolGameMode;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\PlaceStackRequestAction;
use pocketmine\network\mcpe\protocol\types\NetworkPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\player\chat\LegacyRawChatFormatter;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\sound\ExplodeSound;
use ReflectionException;

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
        if($player instanceof Session) {
            if(Game::isInGame($player)) {
                $game = Game::getGame($player);
                if($game->isCurrent()) {
                    if($game instanceof HikabrainTeamFight || $game instanceof RushTF) {
                        $team = $game->getPlayerTeam($player);
                        $player->teleport($team->getRespawn());
                        $game->giveKit($player, $team);
                        return;
                    }
                }
            }

            $player->spawn();

        }
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
    public function onDamage(EntityDamageByEntityEvent $event): void
    {
        $player = $event->getEntity();
        if(!$player instanceof Session) return;
        $damager = $event->getDamager();
        if($damager instanceof Player) {
            if(!$event->isCancelled() && $event->isApplicable($event::MODIFIER_PREVIOUS_DAMAGE_COOLDOWN)) {
                $event->cancel();
                return;
            }

            if(Game::isInGame($player) && Game::isInGame($damager)) {
                $game = Game::getGame($player);
                $dGame = Game::getGame($damager);
                if($dGame !== $game) return;
                if ($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                    $team = $game->getPlayerTeam($player);
                    if($team instanceof Team) {
                        $dTeam = $game->getPlayerTeam($damager);
                        if($dTeam instanceof Team) {
                            if($dTeam->getName() === $team->getName()) {
                                $event->cancel();
                                return;
                            }


                            DataBase::GAME_STATS()->addition("{$damager->getName()}.hikabrain.degats", $event->getFinalDamage());
                            DataBase::GLOBAL_STATS()->addition("{$damager->getName()}.degats", $event->getFinalDamage());
                            if(!in_array(strtolower($damager->getName()), array_values($player->lastAttack))) {
                                $player->lastAttack[] = strtolower($damager->getName());
                            }
                        }
                    }
                } elseif($game instanceof RushTF && $game->isCurrent()) {
                    $team = $game->getPlayerTeam($player);
                    if($team instanceof Team) {
                        $dTeam = $game->getPlayerTeam($damager);
                        if($dTeam instanceof Team) {
                            if($dTeam->getName() === $team->getName()) {
                                $event->cancel();
                                return;
                            }


                            DataBase::GAME_STATS()->addition("{$damager->getName()}.rushfast.degats", $event->getFinalDamage());
                            DataBase::GLOBAL_STATS()->addition("{$damager->getName()}.degats", $event->getFinalDamage());
                            if(!in_array(strtolower($damager->getName()), array_values($player->lastAttack))) {
                                $player->lastAttack[] = strtolower($damager->getName());
                            }
                        }
                    }
                }
                if(!$game->isCurrent()) $event->cancel();
            } else $event->cancel();
        }
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

        if(Game::isInGame($player)) {
            $game = Game::getGame($player);
            if($game instanceof HikabrainTeamFight || $game instanceof RushTF) {
                $team = $game->getPlayerTeam($player);
                if(str_starts_with($message, "@") || !$game->isCurrent()) {
                    $game->broadcastMessage("§6[Global] {$team->getColor()}{$team->getName()} {$player->getName()}§7: {$team->getColor()}{$message}");
                    $event->cancel();
                    return;
                }

                $team->broadcastMessage("{$team->getColor()}[Équipe] {$player->getName()}§7: {$team->getColor()}{$message}");
                $event->cancel();
                return;
            }
        }

        $event->setFormatter(new LegacyRawChatFormatter("§7{%0}: {%1}"));
    }

    /**
     * @param PlayerMoveEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onMove(PlayerMoveEvent $event): void
    {
        $player = $event->getPlayer();
        $to = $event->getTo();
        if(Game::isInGame($player)) {
            $game = Game::getGame($player);
            if ($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                /* @var $team Team */
                foreach ($game->getAllTeams() as $team) {
                    if($team->isInTeam($player->getName())) continue;
                    $bed = $team->getRespawn()->subtract(0, 4, 0);
                    $myTeam = $game->getPlayerTeam($player);
                    if($myTeam instanceof Team) {
                        if($to->distance($bed) <= 1) {
                            $game->addWin($player, $myTeam);
                            return;
                        }
                    }
                }

                if($to->getY() < 4.25) {
                    $ev = new EntityDamageEvent($player, EntityDamageEvent::CAUSE_VOID, 1000000000);
                    $player->attack($ev);
                }
            } elseif($game instanceof RushTF && $game->isCurrent()) {
                if($to->getY() < 4.25) {
                    $ev = new EntityDamageEvent($player, EntityDamageEvent::CAUSE_VOID, 1000000000);
                    $player->attack($ev);
                }
            }
        }
    }

    /**
     * @param DataPacketReceiveEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        $player = ($origin = $event->getOrigin())->getPlayer();
        if(!$player instanceof Session) return;

        $currentTradeWindow = $player->currentTradeWindow;
        if(!$currentTradeWindow instanceof TradeInventory) return;

        if($packet instanceof ItemStackRequestPacket) {
            foreach ($packet->getRequests() as $request) {
                $actions = $request->getActions();
                foreach ($actions as $action) {
                    if($action instanceof PlaceStackRequestAction) {
                        $source = $action->getSource();
                        $event->cancel();
                    } elseif($action instanceof DeprecatedCraftingResultsStackRequestAction) {
                        $results = $action->getResults();
                        foreach ($results as $result) {
                            $item = TypeConverter::getInstance()->netItemStackToCore($result);
                            foreach ($currentTradeWindow->getRecipes() as $recipe) {
                                $sell = $recipe["sell"];
                                if($sell instanceof Item && $item->equals($sell, false, false)) {
                                    $buyA = $recipe["buyA"];
                                    $buyB = $recipe["buyB"];

                                    if($buyA instanceof Item) {
                                        $inventory = $player->getInventory();
                                        if($inventory->contains($buyA)) {
                                            if($buyB instanceof Item && !$buyB->isNull()) {
                                                if(!$inventory->contains($buyB)) {
                                                    return;
                                                }
                                                $inventory->removeItem($buyB);
                                            }

                                            $inventory->removeItem($buyA);
                                            $inventory->addItem($sell);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif($packet instanceof ContainerClosePacket) {
            $player->currentTradeWindow = null;
        }
    }

    /**
     * @param DataPacketSendEvent $event
     * @return void
     * @throws ReflectionException
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        $packets = $event->getPackets();
        $targets = $event->getTargets();

        foreach ($packets as $i => $packet) {
            foreach ($targets as $id => $session) {
                $player = $session->getPlayer();
                if($player instanceof Player) {
                    if ($packet instanceof SetPlayerGameTypePacket) {
                        if (($packet->gamemode == ProtocolGameMode::CREATIVE_VIEWER || $packet->gamemode == ProtocolGameMode::CREATIVE) && $player->getGamemode() === GameMode::SPECTATOR) {
                            $packet->gamemode = 6;
                        }
                    } elseif ($packet instanceof StartGamePacket) {
                        if (($packet->playerGamemode == ProtocolGameMode::CREATIVE_VIEWER || $packet->playerGamemode == ProtocolGameMode::CREATIVE) && $player->getGamemode() === GameMode::SPECTATOR) {
                            $packet->playerGamemode = 6;
                        }
                    }
                }
            }
            if ($packet instanceof StartGamePacket) {
                $packet->playerMovementSettings = new PlayerMovementSettings(PlayerMovementType::SERVER_AUTHORITATIVE_V2_REWIND, 0, false);
                $packet->networkPermissions = new NetworkPermissions(disableClientSounds: true);
                $packet->levelSettings->muteEmoteAnnouncements = true;
            }
        }
        $event->setPackets($packets);
        ReflectionUtils::setProperty($event::class, $event, "targets", $targets);
    }

    /**
     * @param PlayerRespawnEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerRespawn(PlayerRespawnEvent $event): void
    {
        $player = $event->getPlayer();
        if(Game::isInGame($player)) {
            $game = Game::getGame($player);
            if ($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                $team = $game->getPlayerTeam($player);
                if($team instanceof Team) {
                    $event->setRespawnPosition($team->getRespawn());
                    $game->giveKit($player, $team);
                }
            } elseif($game instanceof RushTF && $game->isCurrent()) {
                $team = $game->getPlayerTeam($player);
                if($team instanceof Team) {
                    $event->setRespawnPosition($team->getRespawn());
                    if(!is_null($team->bed)) {
                        $game->giveKit($player, $team);
                    } else {
                        $player->setMaxHealth(20);
                        $player->setHealth($player->getMaxHealth());
                        $player->setNameTag("{$team->getColor()}{$team->getName()} {$player->getName()}");
                        $player->getEnderInventory()->clearAll();
                        $player->getCursorInventory()->clearAll();
                        $player->getArmorInventory()->clearAll();
                        $player->getOffHandInventory()->clearAll();
                        $player->setGamemode(GameMode::SPECTATOR());
                    }
                }
            }
        }
    }

    /**
     * @param PlayerDeathEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onPlayerDeath(PlayerDeathEvent $event): void
    {
        $player = $event->getPlayer();
        if(!$player instanceof Session) return;
        $event->setDeathMessage("");

        if(Game::isInGame($player)) {
            $game = Game::getGame($player);
            if ($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                $team = $game->getPlayerTeam($player);
                if($team instanceof Team) {
                    $deathCause = $player->getLastDamageCause();
                    switch ($cause = ($deathCause === null ? EntityDamageEvent::CAUSE_CUSTOM : $deathCause->getCause())) {
                        case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                            if($deathCause instanceof EntityDamageByEntityEvent) {
                                $e = $deathCause->getDamager();
                                if($e instanceof Player) {
                                    if(count($player->lastAttack) >= 1) {
                                        $killers = [];
                                        foreach ($player->lastAttack as $item) {
                                            /* @var $t Team */
                                            foreach ($game->getAllTeams() as $t) {
                                                if($t->getName() === $team->getName()) continue;
                                                foreach ($t->getMembers() as $member) {
                                                    $target = Server::getInstance()->getPlayerExact($member);
                                                    if($target instanceof Player) {
                                                        if(strtolower($target->getName()) === strtolower($item)) {
                                                            $killers[] = "{$t->getColor()}{$target->getName()}§r";
                                                            DataBase::GAME_STATS()->addition("{$target->getName()}.hikabrain.assists");
                                                            DataBase::GLOBAL_STATS()->addition("{$target->getName()}.assists");
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        DataBase::GAME_STATS()->addition("{$e->getName()}.hikabrain.kills");
                                        DataBase::GLOBAL_STATS()->addition("{$e->getName()}.kills");

                                        DataBase::GAME_STATS()->addition("{$e->getName()}.hikabrain.killstreak");
                                        if(($killStreak = DataBase::GAME_STATS()->current("{$e->getName()}.hikabrain.killstreak", 0)) > DataBase::GAME_STATS()->current("{$e->getName()}.hikabrain.bestkillstreak", 0)) {
                                            DataBase::GAME_STATS()->push("{$e->getName()}.hikabrain.bestkillstreak", $killStreak);
                                        }

                                        DataBase::GLOBAL_STATS()->addition("{$e->getName()}.killstreak");
                                        if(($killStreak = DataBase::GLOBAL_STATS()->current("{$e->getName()}.killstreak", 0)) > DataBase::GLOBAL_STATS()->current("{$e->getName()}.bestkillstreak", 0)) {
                                            DataBase::GLOBAL_STATS()->push("{$e->getName()}.bestkillstreak", $killStreak);
                                        }

                                        $message = "§6[Hikabrain] {$team->getColor()}{$player->getName()}§f a été tué par " . implode("§7, ", $killers) . "§f.";
                                        $game->broadcastMessage($message);
                                    } else {
                                        $te = $game->getPlayerTeam($e);
                                        if($te instanceof Team) {
                                            $game->broadcastMessage("§6[Hikabrain] {$team->getColor()}{$player->getName()}§f a été tué par {$te->getColor()}{$e->getName()}§f.");
                                        }
                                    }
                                }
                            }
                            break;
                        case EntityDamageEvent::CAUSE_FALL:
                        case EntityDamageEvent::CAUSE_VOID:
                            if(count($player->lastAttack) >= 1) {
                                $killers = [];
                                $killer = "";
                                foreach ($player->lastAttack as $item) {
                                    /* @var $t Team */
                                    foreach ($game->getAllTeams() as $t) {
                                        if($t->getName() === $team->getName()) continue;
                                        foreach ($t->getMembers() as $member) {
                                            $target = Server::getInstance()->getPlayerExact($member);
                                            if($target instanceof Player) {
                                                if(strtolower($target->getName()) === strtolower($item)) {
                                                    $killers[] = "{$t->getColor()}{$target->getName()}§r";
                                                    DataBase::GAME_STATS()->addition("{$target->getName()}.hikabrain.assists");
                                                    DataBase::GLOBAL_STATS()->addition("{$target->getName()}.assists");
                                                }
                                            }
                                        }
                                    }

                                    $killer = strtolower($item);
                                }

                                if($killer !== "") {
                                    DataBase::GAME_STATS()->addition("{$killer}.hikabrain.kills");
                                    DataBase::GLOBAL_STATS()->addition("{$killer}.kills");

                                    DataBase::GAME_STATS()->addition("{$killer}.hikabrain.killstreak");
                                    if(($killStreak = DataBase::GAME_STATS()->current("{$killer}.hikabrain.killstreak", 0)) > DataBase::GAME_STATS()->current("{$killer}.hikabrain.bestkillstreak", 0)) {
                                        DataBase::GAME_STATS()->push("{$killer}.hikabrain.bestkillstreak", $killStreak);
                                    }

                                    DataBase::GLOBAL_STATS()->addition("{$killer}.killstreak");
                                    if(($killStreak = DataBase::GLOBAL_STATS()->current("{$killer}.killstreak", 0)) > DataBase::GLOBAL_STATS()->current("{$killer}.bestkillstreak", 0)) {
                                        DataBase::GLOBAL_STATS()->push("{$killer}.bestkillstreak", $killStreak);
                                    }
                                }

                                $reason = $cause == EntityDamageEvent::CAUSE_FALL ? "une chute" : "le vide";
                                $message = "§6[Hikabrain] {$team->getColor()}{$player->getName()}§f a été tué par §b{$reason}§f. §7(" . implode("§7, ", array_values($killers)) . "§7)";
                                $game->broadcastMessage($message);
                            } else $event->setDeathMessage("§6[Hikabrain] {$team->getColor()}{$player->getName()}§f est mort.");
                            break;
                        default:
                            $game->broadcastMessage("§6[Hikabrain] {$team->getColor()}{$player->getName()}§f est mort.");
                            break;
                    }
                }

                $player->getWorld()->addSound($player->getPosition(), new ExplodeSound());
                $player->getWorld()->addParticle($player->getPosition(), new HugeExplodeParticle());
                DataBase::GAME_STATS()->push("{$player->getName()}.hikabrain.killstreak", 0);
                DataBase::GLOBAL_STATS()->push("{$player->getName()}.killstreak", 0);
                DataBase::GAME_STATS()->addition("{$player->getName()}.hikabrain.deaths");
                DataBase::GLOBAL_STATS()->addition("{$player->getName()}.deaths");
                $event->setDrops([]);
            } elseif ($game instanceof RushTF && $game->isCurrent()) {
                $team = $game->getPlayerTeam($player);
                if($team instanceof Team) {
                    $deathCause = $player->getLastDamageCause();
                    switch ($cause = ($deathCause === null ? EntityDamageEvent::CAUSE_CUSTOM : $deathCause->getCause())) {
                        case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                            if($deathCause instanceof EntityDamageByEntityEvent){
                                $e = $deathCause->getDamager();
                                if($e instanceof Player){
                                    if(count($player->lastAttack) >= 1) {
                                        $killers = [];
                                        foreach ($player->lastAttack as $item) {
                                            /* @var $t Team */
                                            foreach ($game->getAllTeams() as $t) {
                                                if($t->getName() === $team->getName()) continue;
                                                foreach ($t->getMembers() as $member) {
                                                    $target = Server::getInstance()->getPlayerExact($member);
                                                    if($target instanceof Player) {
                                                        if(strtolower($target->getName()) === strtolower($item)) {
                                                            $killers[] = "{$t->getColor()}{$target->getName()}§r";
                                                            DataBase::GAME_STATS()->addition("{$target->getName()}.rushfast.assists");
                                                            DataBase::GLOBAL_STATS()->addition("{$target->getName()}.assists");
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        DataBase::GAME_STATS()->addition("{$e->getName()}.rushfast.kills");
                                        DataBase::GLOBAL_STATS()->addition("{$e->getName()}.kills");

                                        DataBase::GAME_STATS()->addition("{$e->getName()}.rushfast.killstreak");
                                        if(($killStreak = DataBase::GAME_STATS()->current("{$e->getName()}.rushfast.killstreak", 0)) > DataBase::GAME_STATS()->current("{$e->getName()}.rushfast.bestkillstreak", 0)) {
                                            DataBase::GAME_STATS()->push("{$e->getName()}.rushfast.bestkillstreak", $killStreak);
                                        }

                                        DataBase::GLOBAL_STATS()->addition("{$e->getName()}.killstreak");
                                        if(($killStreak = DataBase::GLOBAL_STATS()->current("{$e->getName()}.killstreak", 0)) > DataBase::GLOBAL_STATS()->current("{$e->getName()}.bestkillstreak", 0)) {
                                            DataBase::GLOBAL_STATS()->push("{$e->getName()}.bestkillstreak", $killStreak);
                                        }

                                        if(is_null($team->bed)) {
                                            $message = "§6[Rush] {$team->getColor()}{$player->getName()}§f a été éliminé par " . implode("§7, ", $killers) . "§f.";
                                            $player->inLife = false;
                                        } else $message = "§6[Rush] {$team->getColor()}{$player->getName()}§f a été tué par " . implode("§7, ", $killers) . "§f.";
                                        $game->broadcastMessage($message);
                                    } else {
                                        $te = $game->getPlayerTeam($e);
                                        if($te instanceof Team) {
                                            $game->broadcastMessage("§6[Rush] {$team->getColor()}{$player->getName()}§f a été tué par {$te->getColor()}{$e->getName()}§f.");
                                        }
                                    }
                                }
                            }
                            break;
                        case EntityDamageEvent::CAUSE_FALL:
                        case EntityDamageEvent::CAUSE_VOID:
                            if(count($player->lastAttack) >= 1) {
                                $killers = [];
                                $killer = "";
                                foreach ($player->lastAttack as $item) {
                                    /* @var $t Team */
                                    foreach ($game->getAllTeams() as $t) {
                                        if($t->getName() === $team->getName()) continue;
                                        foreach ($t->getMembers() as $member) {
                                            $target = Server::getInstance()->getPlayerExact($member);
                                            if($target instanceof Player) {
                                                if(strtolower($target->getName()) === strtolower($item)) {
                                                    $killers[] = "{$t->getColor()}{$target->getName()}§r";
                                                    DataBase::GAME_STATS()->addition("{$target->getName()}.rushfast.assists");
                                                    DataBase::GLOBAL_STATS()->addition("{$target->getName()}.assists");
                                                }
                                            }
                                        }
                                    }

                                    $killer = strtolower($item);
                                }

                                if($killer !== "") {
                                    DataBase::GAME_STATS()->addition("{$killer}.rushfast.kills");
                                    DataBase::GLOBAL_STATS()->addition("{$killer}.kills");

                                    DataBase::GAME_STATS()->addition("{$killer}.rushfast.killstreak");
                                    if(($killStreak = DataBase::GAME_STATS()->current("{$killer}.rushfast.killstreak", 0)) > DataBase::GAME_STATS()->current("{$killer}.rushfast.bestkillstreak", 0)) {
                                        DataBase::GAME_STATS()->push("{$killer}.rushfast.bestkillstreak", $killStreak);
                                    }

                                    DataBase::GLOBAL_STATS()->addition("{$killer}.killstreak");
                                    if(($killStreak = DataBase::GLOBAL_STATS()->current("{$killer}.killstreak", 0)) > DataBase::GLOBAL_STATS()->current("{$killer}.bestkillstreak", 0)) {
                                        DataBase::GLOBAL_STATS()->push("{$killer}.bestkillstreak", $killStreak);
                                    }
                                }

                                $reason = $cause == EntityDamageEvent::CAUSE_FALL ? "une chute" : "le vide";
                                if(is_null($team->bed)) {
                                    $message = "§6[Rush] {$team->getColor()}{$player->getName()}§f a été éliminé par §b{$reason}§f. §7(" . implode("§7, ", array_values($killers)) . "§7)";
                                    $player->inLife = false;
                                } else $message = "§6[Rush] {$team->getColor()}{$player->getName()}§f a été tué par §b{$reason}§f. §7(" . implode("§7, ", array_values($killers)) . "§7)";

                                $game->broadcastMessage($message);
                            } else {
                                if(is_null($team->bed)) {
                                    $game->broadcastMessage("§6[Rush] {$team->getColor()}{$player->getName()}§f a été éliminé.");
                                    $player->inLife = false;
                                } else $game->broadcastMessage("§6[Rush] {$team->getColor()}{$player->getName()}§f est mort.");
                            }
                            break;
                        default:
                            if(is_null($team->bed)) {
                                $game->broadcastMessage("§6[Rush] {$team->getColor()}{$player->getName()}§f a été éliminé.");
                                $player->inLife = false;
                            } else $game->broadcastMessage("§6[Rush] {$team->getColor()}{$player->getName()}§f est mort.");
                            break;
                    }
                }

                $player->getWorld()->addSound($player->getPosition(), new ExplodeSound());
                $player->getWorld()->addParticle($player->getPosition(), new HugeExplodeParticle());
                DataBase::GAME_STATS()->push("{$player->getName()}.rushfast.killstreak", 0);
                DataBase::GLOBAL_STATS()->push("{$player->getName()}.killstreak", 0);
                DataBase::GAME_STATS()->addition("{$player->getName()}.rushfast.deaths");
                DataBase::GLOBAL_STATS()->addition("{$player->getName()}.deaths");
            }
            $player->lastAttack = [];
            return;
        }

        $event->setDeathMessage("");
    }

    /**
     * @param BlockBreakEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if(Game::isInGame($player)) {
            $game = Game::getGame($player);
            if ($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                if(
                    !$block->asItem()->equals(VanillaBlocks::CHISELED_SANDSTONE()->asItem()) &&
                    !$block->asItem()->equals(VanillaBlocks::SANDSTONE()->asItem()) &&
                    !$block->asItem()->equals(VanillaBlocks::CUT_SANDSTONE()->asItem())
                ) {
                    $player->sendMessage(TextFormat::RED . "Vous ne pouvez pas faire ca.");
                    $event->cancel();
                    return;
                }
            } elseif($game instanceof RushTF && $game->isCurrent()) {
                if(
                    !$block->asItem()->equals(VanillaBlocks::TNT()->asItem()) &&
                    !$block->asItem()->equals(VanillaBlocks::CHISELED_SANDSTONE()->asItem()) &&
                    !$block->asItem()->equals(VanillaBlocks::SANDSTONE()->asItem()) &&
                    !$block->asItem()->equals(VanillaBlocks::CUT_SANDSTONE()->asItem())
                ) {
                    $player->sendMessage(TextFormat::RED . "Vous ne pouvez pas faire ca.");
                    $event->cancel();
                    return;
                }
            }
            if(!$game->isCurrent()) $event->cancel();
        } elseif(!$player->isCreative(true)) $event->cancel();
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
        if(Game::isInGame($player)) {
            $game = Game::getGame($player);
            if ($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                if($action === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
                    if (
                        !$block->asItem()->equals(VanillaBlocks::CHISELED_SANDSTONE()->asItem()) &&
                        !$block->asItem()->equals(VanillaBlocks::SANDSTONE()->asItem()) &&
                        !$block->asItem()->equals(VanillaBlocks::CUT_SANDSTONE()->asItem())
                    ) {
                        $event->cancel();
                        return;
                    }
                }
            } elseif ($game instanceof RushTF && $game->isCurrent()) {
                if($action === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
                    if (
                        !$block->asItem()->equals(VanillaBlocks::TNT()->asItem()) &&
                        !$block->asItem()->equals(VanillaBlocks::CHISELED_SANDSTONE()->asItem()) &&
                        !$block->asItem()->equals(VanillaBlocks::SANDSTONE()->asItem()) &&
                        !$block->asItem()->equals(VanillaBlocks::CUT_SANDSTONE()->asItem())
                    ) {
                        $event->cancel();
                        return;
                    }
                } elseif($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                    if($item instanceof FlintSteel) {
                        if(!$block instanceof \Lobby\libraries\slq\Hikabrain\block\TNT) {
                            $player->sendMessage(TextFormat::RED . "Vous ne pouvez pas faire ca.");
                            $event->cancel();
                            return;
                        }
                    }
                }
            }
            if(!$game->isCurrent()) $event->cancel();
        } elseif(!$player->isCreative(true)) $event->cancel();
    }

    /**
     * @param EntityDamageEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onDamageEntity(EntityDamageEvent $event): void
    {
        $player = $event->getEntity();
        if($player instanceof Session) {
            if(!Game::isInGame($player)) {
                $event->cancel();
            } else{
                $game = Game::getGame($player);
                if($game instanceof HikabrainTeamFight || $game instanceof RushTF) {
                    if(!$game->isCurrent()) $event->cancel();
                }
            }
        }
    }

    /**
     * @param EntityExplodeEvent $event
     * @return void
     */
    #[EventAttribute(EventPriority::NORMAL)]
    public function onEntityExplose(EntityExplodeEvent $event): void
    {
        $entity = $event->getEntity();
        if($entity instanceof PrimedTNT) {
            $owning = $entity->getOwningEntity();
            if (!$owning instanceof Session) return;
            if (!Game::isInGame($owning)) return;
            $game = Game::getGame($owning);
            if ($game instanceof RushTF && $game->isCurrent()) {
                $owningTeam = $game->getPlayerTeam($owning);
                if(!$owningTeam instanceof Team) return;

                $blocks = $event->getBlockList();
                foreach ($blocks as $i => $block) {
                    if (
                        !$block->asItem()->equals(VanillaBlocks::BED()->setColor(DyeColor::BLUE())->asItem(), false, false) &&
                        !$block->asItem()->equals(VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem(), false, false) &&
                        !$block->asItem()->equals(VanillaBlocks::CHISELED_SANDSTONE()->asItem(), false, false) &&
                        !$block->asItem()->equals(VanillaBlocks::SANDSTONE()->asItem(), false, false) &&
                        !$block->asItem()->equals(VanillaBlocks::CUT_SANDSTONE()->asItem(), false, false)
                    ) unset($blocks[$i]);

                    if ($block instanceof Bed) {
                        if($block->isHeadPart()) continue;

                        $callable = null;
                        foreach ($game->getAllTeams() as $team) {
                            if (!is_null($team->bed)) {
                                $bed = $team->bed;
                                if ($block->getPosition()->floor()->distance($bed->floor()) <= 2.5) {
                                    if($owningTeam->getName() === $team->getName()) {
                                        unset($blocks[$i]);
                                        continue;
                                    }

                                    $callable = function() use($game, $owning, $team) {
                                        $game->addBed($owning, $team, false);
                                    };
                                    continue;
                                }
                                continue;
                            }
                            continue;
                        }

                        if(is_null($callable)) {
                            $game->addBed($owning, $owningTeam);
                        } else $callable();
                    }
                }
                $event->setBlockList($blocks);
            }
        }
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
            if(Game::isInGame($player)) {
                $game = Game::getGame($player);
                if ($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                    if($y > 14) {
                        $player->sendMessage(TextFormat::RED . "Vous ne pouvez pas faire ca.");
                        $event->cancel();
                        return;
                    }

                    DataBase::GAME_STATS()->addition("{$player->getName()}.hikabrain.blockplace");
                    DataBase::GLOBAL_STATS()->addition("{$player->getName()}.blockplace");
                } elseif($game instanceof RushTF && $game->isCurrent()) {
                    if($y > 73) {
                        $player->sendMessage(TextFormat::RED . "Vous ne pouvez pas faire ca.");
                        $event->cancel();
                        return;
                    }

                    $team = $game->getPlayerTeam($player);
                    if($team instanceof Team) {
                        if(!is_null($team->bed)) {
                            if($team->bed->distance(new Vector3($x, $y, $z)) <= 2) {
                                $player->sendMessage(TextFormat::RED . "Vous ne pouvez pas faire ca.");
                                $event->cancel();
                                return;
                            } elseif($team->bed->distance(new Vector3($x, $y, $z)) <= 10 && $block instanceof TNT) {
                                $player->sendMessage(TextFormat::RED . "Vous ne pouvez pas faire ca.");
                                $event->cancel();
                                return;
                            }
                        }
                    }

                    DataBase::GAME_STATS()->addition("{$player->getName()}.rushfast.blockplace");
                    DataBase::GLOBAL_STATS()->addition("{$player->getName()}.blockplace");
                }
                if(!$game->isCurrent()) $event->cancel();
            } elseif(!$player->isCreative(true)) $event->cancel();
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
        if(Game::isInGame($player)) {
            $game = Game::getGame($player);
            if($game instanceof HikabrainTeamFight && $game->isCurrent()) {
                $event->cancel();
            }elseif($game instanceof RushTF && $game->isCurrent()) {
                $event->uncancel();
            }
        } else $event->cancel();
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