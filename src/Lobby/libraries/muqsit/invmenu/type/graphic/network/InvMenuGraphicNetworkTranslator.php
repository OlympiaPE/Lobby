<?php

declare(strict_types=1);

namespace Lobby\libraries\muqsit\invmenu\type\graphic\network;

use Lobby\libraries\muqsit\invmenu\session\InvMenuInfo;
use Lobby\libraries\muqsit\invmenu\session\PlayerSession;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;

interface InvMenuGraphicNetworkTranslator{

	public function translate(PlayerSession $session, InvMenuInfo $current, ContainerOpenPacket $packet) : void;
}