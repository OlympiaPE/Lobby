<?php

namespace Lobby\managers\types;

use Lobby\libraries\SenseiTarzan\ExtraEvent\Component\EventLoader;
use Lobby\listeners\PacketListener;
use Lobby\listeners\SessionListener;
use Lobby\Loader;
use Lobby\managers\Manager;

class ListenerManager extends Manager
{
    /**
     * @return void
     */
    public function onLoad(): void
    {
        EventLoader::loadEventWithClass(Loader::getInstance(), new SessionListener());
        EventLoader::loadEventWithClass(Loader::getInstance(), new PacketListener());
    }
}
