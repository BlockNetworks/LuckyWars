<?php

namespace LuckyWars;

use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\level\Level;
use LuckyWars\ResetMap;
use pocketmine\level\sound\PopSound;
use pocketmine\block\Air;

class GameSender extends Task {

	public function __construct($plugin) {
		$this->plugin = $plugin;
		$this->prefix = $this->plugin->prefix;
		parent::__construct($plugin);
	}

	public function getResetmap() {
		return new ResetMap($this);
	}

	public function onRun($tick) {
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$arenas = $config->get("arenas");

		if (!empty($arenas)) {
			foreach ($arenas as $arena) {
				$time = $config->get($arena . "PlayTime");
				$timeToStart = $config->get($arena . "StartTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);

				if ($levelArena instanceof Level) {
					$playersArena = $levelArena->getPlayers();
					if (count($playersArena) == 0) {
						$config->set($arena . "PlayTime", 780);
						$config->set($arena . "StartTime", 30);
						$config->set($arena . "start", 0);
					} else {
						if (count($playersArena) >= 2) {
							$config->set($arena . "start", 1);
							$config->save();
						}

						if ($config->get($arena . "start") == 1) {
							if ($timeToStart > 0) {
								$timeToStart--;
								foreach ($playersArena as $pl) {
									$pl->sendPopup(TextFormat::GREEN."Starting in" . TextFormat::GREEN . $timeToStart . TextFormat::RESET);
									if ($timeToStart <= 5) {
										$levelArena->addSound(new PopSound($pl));
									}
								}

								if ($timeToStart == 29) {
									$levelArena->setTime(7000);
									$levelArena->stopTime();
								}

								if ($timeToStart <= 0) {
									foreach ($playersArena as $pl) {
										$pl->getLevel()->setBlock($pl->floor()->subtract(0, 1), new Air());
									}
								}
								$config->set($arena . "StartTime", $timeToStart);
							} else {
								$aop = count($playersArena);

								if ($aop==1) {
									foreach ($playersArena as $pl) {
										$this->plugin->getServer()->broadcastMessage($this->prefix . $pl->getName() . TextFormat::GREEN . " Won in the arena " . TextFormat::AQUA . $arena);
										$pl->getInventory()->clearAll();
										$pl->removeAllEffects();
										$pl->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);
										$pl->setHealth(20);
										$pl->setFood(20);
										$pl->setNameTag($pl->getName());
										$this->getResetmap()->reload($levelArena);
									}
									$config->set($arena . "PlayTime", 780);
									$config->set($arena . "StartTime", 30);
									$config->set($arena . "Start", 0);
								}
								if (($aop >= 2)) {
									foreach ($playersArena as $pl) {
										$pl->sendPopup(TextFormat::BOLD . TextFormat::GOLD . $aop . " " . TextFormat::AQUA . "Players remaining" . TextFormat::RESET);
									}
								}
								$time--;
								if ($time == 779) {
									$slots = new Config($this->plugin->getDataFolder() . "/slots.yml", Config::YAML);
									for ($i = 1; $i <= 12; $i++) {
										$slots->set("slot" . $i . $arena, 0);
									}
									$slots->save();
									foreach ($playersArena as $pl) {
										$pl->sendMessage(TextFormat::YELLOW . ">--------------------------------");
										$pl->sendMessage(TextFormat::YELLOW . ">" . TextFormat::RED . "Attention: " . TextFormat::GREEN . "The game has started");
										$pl->sendMessage(TextFormat::YELLOW . ">" . TextFormat::WHITE . "Map: " . TextFormat::AQUA . $arena);
										$pl->sendMessage(TextFormat::YELLOW . ">--------------------------------");
									}
								}

								if ($time == 550) {
									foreach ($playersArena as $pl) {
										$pl->sendMessage(TextFormat::YELLOW . ">" . TextFormat::AQUA . "You are playing in LuckyWars");
										$pl->sendMessage(TextFormat::YELLOW . ">" . TextFormat::AQUA . "Thank you" . TextFormat::GREEN . " for playing");
									}
								}

								if ($time < 300) {
									$minutes = $time / 60;
									if (is_int($minutes) && $minutes > 0) {
										foreach ($playersArena as $pl) {
											$pl->sendMessage($this->prefix . TextFormat::YELLOW . $minutes . " " . TextFormat::GREEN . "minutes remaining");
										}
									} elseif ($time == 30 || $time == 15 || $time == 10 || $time == 5 || $time == 4 || $time == 3 || $time == 2 || $time == 1) {
										foreach ($playersArena as $pl) {
											$pl->sendMessage($this->prefix . TextFormat::YELLOW . $time . " " . TextFormat::GREEN . "seconds remaining");
										}
									}
									if ($time <= 0) {
										$this->plugin->getServer()->broadcastMessage($this->prefix . TextFormat::GREEN . "There are no winners in the arena " . TextFormat::AQUA . $arena);
										foreach ($playersArena as $pl) {
											$pl->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);
											$pl->getInventory()->clearAll();
											$pl->removeAllEffects();
											$pl->setFood(20);
											$pl->setHealth(20);
											$pl->setNameTag($pl->getName());
											$this->getResetmap()->reload($levelArena);
											$config->set($arena . "start", 0);
											$config->save();
										}
										$time = 780;
									}
								}
								$config->set($arena . "PlayTime", $time);
							}
						} else {
							foreach ($playersArena as $pl) {
								$pl->sendPopup(TextFormat::DARK_AQUA . "Players Remaining" . TextFormat::RESET);
							}
							$config->set($arena . "PlayTime", 780);
							$config->set($arena . "StartTime", 30);
						}
					}
				}
			}
		}
		$config->save();
	}

}
