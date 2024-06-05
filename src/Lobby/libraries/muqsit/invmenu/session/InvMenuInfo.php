<?php

declare(strict_types=1);

namespace Lobby\libraries\muqsit\invmenu\session;

use Lobby\libraries\muqsit\invmenu\InvMenu;
use Lobby\libraries\muqsit\invmenu\type\graphic\InvMenuGraphic;

final class InvMenuInfo{

	public function __construct(
		readonly public InvMenu $menu,
		readonly public InvMenuGraphic $graphic,
		readonly public ?string $graphic_name
	){}
}