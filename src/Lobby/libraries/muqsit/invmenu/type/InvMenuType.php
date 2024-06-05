<?php

declare(strict_types=1);

namespace Lobby\libraries\muqsit\invmenu\type;

use Lobby\libraries\muqsit\invmenu\InvMenu;
use Lobby\libraries\muqsit\invmenu\type\graphic\InvMenuGraphic;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;

interface InvMenuType{

	public function createGraphic(InvMenu $menu, Player $player) : ?InvMenuGraphic;

	public function createInventory() : Inventory;
}