<?php

declare(strict_types=1);

namespace Lobby\libraries\muqsit\invmenu\session\network\handler;

use Closure;
use Lobby\libraries\muqsit\invmenu\session\network\NetworkStackLatencyEntry;

interface PlayerNetworkHandler{

	public function createNetworkStackLatencyEntry(Closure $then) : NetworkStackLatencyEntry;
}