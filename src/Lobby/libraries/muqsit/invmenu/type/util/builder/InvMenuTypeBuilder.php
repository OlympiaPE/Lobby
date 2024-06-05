<?php

declare(strict_types=1);

namespace Lobby\libraries\muqsit\invmenu\type\util\builder;

use Lobby\libraries\muqsit\invmenu\type\InvMenuType;

interface InvMenuTypeBuilder{

	public function build() : InvMenuType;
}