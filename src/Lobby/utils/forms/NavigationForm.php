<?php

namespace Lobby\utils\forms;

use Lobby\libraries\jojoe77777\FormAPI\SimpleForm;
use Lobby\managers\Managers;
use Lobby\managers\types\servers\ServerInfo;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

class NavigationForm
{
    use SingletonTrait;

    /**
     * @param Player $player
     * @return void
     */
    public function send(Player $player): void
    {
        $form = new SimpleForm(function (Player $player, mixed $data) {
            if(is_null($data)) return;
            $serverInfo = Managers::SERVERS()->getServerByName($data);
            if($serverInfo instanceof ServerInfo) {
                $player->transfer($serverInfo->getAddress(), $serverInfo->getPort(), "Go to {$serverInfo->getName()} ({$serverInfo->getPlayers()})");
            }
        });
        $form->setTitle("Navigation");
        $form->setContent("Veuillez séléctionner un serveur:");
        foreach (Managers::SERVERS()->getServers() as $serverInfo) {
            $form->addButton($serverInfo->getName() . "\n" . $serverInfo->getPlayers() . " joueurs", $serverInfo->hasPath() ? 0 : -1, $serverInfo->getPath(), $serverInfo->getName());
        }
        $player->sendForm($form);
    }
}