<?php

declare(strict_types=1);

namespace Lobby\libraries\muqsit\simplepackethandler;

use InvalidArgumentException;
use Lobby\libraries\muqsit\simplepackethandler\interceptor\IPacketInterceptor;
use Lobby\libraries\muqsit\simplepackethandler\interceptor\PacketInterceptor;
use Lobby\libraries\muqsit\simplepackethandler\monitor\IPacketMonitor;
use Lobby\libraries\muqsit\simplepackethandler\monitor\PacketMonitor;
use pocketmine\event\EventPriority;
use pocketmine\plugin\Plugin;

final class SimplePacketHandler{

	public static function createInterceptor(Plugin $registerer, int $priority = EventPriority::NORMAL, bool $handle_cancelled = false) : IPacketInterceptor{
		if($priority === EventPriority::MONITOR){
			throw new InvalidArgumentException("Cannot intercept packets at MONITOR priority");
		}
		return new PacketInterceptor($registerer, $priority, $handle_cancelled);
	}

	public static function createMonitor(Plugin $registerer, bool $handle_cancelled = false) : IPacketMonitor{
		return new PacketMonitor($registerer, $handle_cancelled);
	}
}