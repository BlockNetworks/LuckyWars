<?php

namespace LuckyWars;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use LuckyWars\ResetMap;
use pocketmine\level\sound\PopSound;
use pocketmine\block\Air;
use pocketmine\math\Vector3;

class LuckyWars extends PluginBase implements Listener {

	public $prefix = TextFormat::GRAY . "[" . TextFormat::YELLOW . TextFormat::RESET . "§eLucky" . TextFormat::GREEN . "§bWars" . TextFormat::RESET . TextFormat::GRAY . "]";
	public $mode = 0;
	public $arenas = [];
	public $currentLevel = "";
	public $op = [];

	public function onEnable() : void {
		$this->getLogger()->info(TextFormat::DARK_AQUA . "LuckyWars has been enabled");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);

		if ($config->get("arenas") != null) {
			$this->arenas = $config->get("arenas");
		}

		foreach ($this->arenas as $lev) {
			$this->getServer()->loadLevel($lev);
		}

		$items = array(
			array(258, 0, 1),
			array(260, 0, 5),
			array(261, 0, 1),
			array(262, 0, 6),
			array(264 ,0, 1),
			array(265, 0, 1),
			array(268, 0, 1),
			array(271, 0, 1),
			array(272, 0, 1),
			array(275, 0, 1),
			array(283, 0, 1),
			array(286, 0, 1),
			array(297, 0, 3),
			array(298, 0, 1),
			array(299, 0, 1),
			array(300, 0, 1),
			array(301, 0, 1),
			array(302, 0, 1),
			array(303, 0, 1),
			array(304, 0, 1),
			array(305, 0, 1),
			array(306, 0, 1),
			array(307, 0, 1),
			array(308, 0, 1),
			array(309, 0, 1),
			array(314, 0, 1),
			array(315, 0, 1),
			array(316, 0, 1),
			array(317, 0, 1),
			array(320, 0, 4),
			array(280, 0, 1),
			array(364, 0, 4),
			array(366, 0, 5),
			array(391, 0, 5),
		);

		if ($config->get("luckyitems") == null) {
			$config->set("luckyitems", $items);
		}

		$config->save();
		$slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
		$slots->save();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 20);
	}

	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();

		if (in_array($level,$this->arenas)) {
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$sofar = $config->get($level . "StartTime");

			if ($sofar > 0) {
				$from = $event->getFrom();
				$to = $event->getTo();
				if ($from->x !== $to->x || $from->z !== $to->z) {
					$event->setCancelled();
				}
			}
		}
	}

	public function onLog(PlayerLoginEvent $event) {
		$player = $event->getPlayer();
		if (in_array($player->getLevel()->getFolderName(), $this->arenas)) {
			$player->getInventory()->clearAll();
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn, 0, 0);
		}
	}

	public function onQuit(PlayerQuitEvent $event) {
		pl = $event->getPlayer();
		$level = $pl->getLevel()->getFolderName();
		if (in_array($level,$this->arenas)) {
			$pl->removeAllEffects();
			$pl->getInventory()->clearAll();
			$slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
			$pl->setNameTag($pl->getName());
			for ($i = 1; $i <= 12; $i++) {
				if ($slots->get("slot" . $i . $level) == $pl->getName()) {
					$slots->set("slot" . $i . $level, 0);
				}
			}
			$slots->save();
		}
	}

	public function onBlockBreaks(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
        $block = $event->getBlock();
		if (in_array($level,$this->arenas)) {
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			if ($config->get($level . "PlayTime") != null) {
				if ($config->get($level . "PlayTime") > 779) {
					$event->setCancelled(true);
				}
			}
			if ($block->getId() == 19) {
				$k = array_rand($config->get("luckyitems"));
				$v = $config->get("luckyitems")[$k];
				$event->setDrops(array(Item::get(Item::AIR, 0, 1)));
				$player->getLevel()->dropItem(new Vector3($block->getX(), $block->getY(), $block->getZ()), Item::get($v[0], $v[1], $v[2]));
			}
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if (in_array($level,$this->arenas)) {
			$event->setCancelled(false);
		}
	}

	public function onCommand(CommandSender $player, Command $command, string $label, array $args) : bool {
        switch ($command->getName()) {
			case "lw":
				if ($player->isOp()) {
					if (!empty($args[0])) {
						if ($args[0] == "create") {
							if (!empty($args[1])) {
								if (file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1])) {
									$this->getServer()->loadLevel($args[1]);
									$this->getServer()->getLevelByName($args[1])->loadChunk(
										$this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(),
										$this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ()
									);
									array_push($this->arenas, $args[1]);
									$this->currentLevel = $args[1];
									$this->mode = 1;
									$player->sendMessage($this->prefix . "Touch the spawn points");
									$player->setGamemode(1);
									array_push($this->op, $player->getName());
									$player->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(), 0, 0);
									$name = $args[1];
									$this->zipper($player, $name);
								} else {
									$player->sendMessage($this->prefix . "ERROR missing world.");
								}
							} else {
								$player->sendMessage($this->prefix . "ERROR missing parameters.");
							}
						} else {
							$player->sendMessage($this->prefix . "Invalid Command.");
						}
					} else {
						$player->sendMessage($this->prefix . "Commands LuckyWars");
						$player->sendMessage($this->prefix . "/lw create [world]: Create LuckyWars arena!");
						$player->sendMessage($this->prefix . "/lwstart: Start game");
					}
				}
				break;
			case "lwstart":
				if ($player->isOp()) {
					if (!empty($args[0])) {
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
						if ($config->get($args[0] . "StartTime") != null) {
							$config->set($args[0] . "StartTime", 10);
							$config->save();
							$player->sendMessage($this->prefix . "Starting in 10...");
						}
					} else {
						$level = $player->getLevel()->getFolderName();
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
						if ($config->get($level . "StartTime") != null) {
							$config->set($level . "StartTime", 10);
							$config->save();
							$player->sendMessage($this->prefix . "Starting in 10...");
						}
					}
				}
				break;
		}
		return true;
	}

	public function onInteract(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);

		if ($tile instanceof Sign) {
			if (($this->mode == 26) && (in_array($player->getName(), $this->op))) {
				$tile->setText(TextFormat::AQUA . "[Join]", TextFormat::YELLOW  . "0 / 12", "§f" . $this->currentLevel,$this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . "Registered arena");
				array_shift($this->op);
			} else {
				$text = $tile->getText();
				if ($text[3] == $this->prefix) {
					if ($text[0] == TextFormat::AQUA . "[Join]") {
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
						$slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
						$namemap = str_replace("§f", "", $text[2]);
						$level = $this->getServer()->getLevelByName($namemap);
						for ($i = 1; $i <= 12; $i++) {
							if ($slots->get("slot" . $i . $namemap) == null) {
								$thespawn = $config->get($namemap . "Spawn" . $i);
								$slots->set("slot" . $i . $namemap, $player->getName());
								$slots->save();
								$player->sendMessage($this->prefix . "§eYou have entered the game");
								foreach ($level->getPlayers() as $playersinarena) {
									$playersinarena->sendMessage($player->getNameTag() ." §eYou have entered the game");
								}
								$spawn = new Position($thespawn[0]+0.5,$thespawn[1],$thespawn[2]+0.5,$level);
								$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
								$player->teleport($spawn,0,0);
								$player->getInventory()->clearAll();
								$player->removeAllEffects();
								$player->setMaxHealth(20);
								$player->setHealth(20);
								$player->setFood(20);
								$player->setGamemode(0);
							} else {
								$player->sendMessage($this->prefix . "No hay Slots");
							}
						}
					} else {
						$player->sendMessage($this->prefix . "You can not get in");
					}
				}
			}
		} elseif (in_array($player->getName(), $this->op) && $this->mode >= 1 && $this->mode <= 11) {
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(), $block->getY() + 1, $block->getZ()));
			$player->sendMessage($this->prefix . "Spawn " . $this->mode . " has been registered!");
			$this->mode++;
			$config->save();
		} elseif (in_array($player->getName(), $this->op) && $this->mode == 12) {
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(), $block->getY() + 1, $block->getZ()));
			$player->sendMessage($this->prefix . "Spawn " . $this->mode . " has been registered!");
			$config->set("arenas", $this->arenas);
			$config->set($this->currentLevel . "start", 0);
			$player->sendMessage($this->prefix . "Touch a sign to register the arena");
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn, 0, 0);
			$config->save();
			$this->mode = 26;
		}
	}

	public function refreshArenas() {
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$config->set("arenas",$this->arenas);
		foreach ($this->arenas as $arena) {
			$config->set($arena . "PlayTime", 780);
			$config->set($arena . "StartTime", 30);
		}
		$config->save();
	}

	public function zipper($player, $name) {
		$path = realpath($player->getServer()->getDataPath() . 'worlds/' . $name);
		$zip = new \ZipArchive;
		@mkdir($this->getDataFolder() . 'arenas/', 0755);
		$zip->open($this->getDataFolder() . 'arenas/' . $name . '.zip', $zip::CREATE | $zip::OVERWRITE);
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $datos) {
			if (!$datos->isDir()) {
				$relativePath = $name . '/' . substr($datos, strlen($path) + 1);
				$zip->addFile($datos, $relativePath);
			}
		}
		$zip->close();
		$player->getServer()->loadLevel($name);
		unset($zip, $path, $files);
	}

}
