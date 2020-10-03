<?php

namespace LuckyWars;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\tile\Sign;
use LuckyWars\ResetMap;
use pocketmine\math\Vector3;

class RefreshSigns extends PluginTask {
   
	public function __construct($plugin) {
		$this->plugin = $plugin;
		$this->prefix = $this->plugin->prefix;
		parent::__construct($plugin);
	}

	public function onRun($tick) {
		$level = $this->plugin->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
		foreach ($tiles as $t) {
			if ($t instanceof Sign) {	
				$text = $t->getText();
				if ($text[3] == $this->prefix) {
					$aop = 0;
					$namemap = str_replace("§f", "", $text[2]);
					$play = $this->plugin->getServer()->getLevelByName($namemap)->getPlayers();
					foreach ($play as $pl) {
						$aop = $aop + 1;
					}
					$ingame = TextFormat::AQUA . "[Join]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if ($config->get($namemap . "PlayTime") != 780) {
						$ingame = TextFormat::DARK_PURPLE . "[In game]";
					} elseif ($aop >= 12) {
						$ingame = TextFormat::GOLD . "[Full]";
					}
					$t->setText($ingame, TextFormat::YELLOW  . $aop . " / 12", $text[2], $this->prefix);
				}
			}
		}
	}

}
