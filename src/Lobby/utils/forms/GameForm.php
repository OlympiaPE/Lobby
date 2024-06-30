<?php

namespace Lobby\utils\forms;

use Lobby\libraries\jojoe77777\FormAPI\SimpleForm;
use Lobby\libraries\slq\Hikabrain\game\Game;
use Lobby\libraries\slq\Hikabrain\game\HikabrainTeamFight;
use Lobby\libraries\slq\Hikabrain\game\RushTF;
use Lobby\libraries\slq\Hikabrain\game\utils\Team;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

class GameForm
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
            if($data === "splatoon") {
                $player->sendMessage("§cProchainement...");
                return;
            }

            $player->transfer("46.105.41.127", 19106);
        });
        $form->setTitle("Mini Jeu");
        $form->addButton("Rush / HikaBrain", label: "rush");
        $form->addButton("Splatoon", label: "splatoon");
        $player->sendForm($form);
    }
}