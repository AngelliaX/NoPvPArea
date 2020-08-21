<?php

namespace Tungst\NoPvPArea;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\object\Painting;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class NoPvPArea extends PluginBase implements Listener
{
	/** @var Config */
	private $config;

	/** @var array looks like ["name"=>["name",[x,y,z],[x,y,z]]] */
	private $check = [];

	public function onEnable()
	{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder() . "config.yml");
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if (!$sender instanceof Player) {
			$sender->sendMessage("Only use in-game");
			return true;
		}
		if (!$sender->hasPermission("nopvparea.command.npa")) {
			$sender->sendMessage("You dont have permission");
			return true;
		}
		if (!isset($args[0])) {
			$sender->sendMessage("Usage: /npa <name>");
			return true;
		}
		$name = $sender->getName();
		if (isset($this->check[$name])) {
			$sender->sendMessage("You already in the set-up statement");
			return true;
		}
		$this->check[$name] = [$args[0]];
		$sender->sendMessage("Break 2 blocks to create a squared area disabling pvp");
		return true;
	}

	public function breakBlock(BlockBreakEvent $ev)
	{
		$player = $ev->getPlayer();
		if (!isset($this->check[$player->getName()])) return;
		$ev->setCancelled();
		$block = $ev->getBlock();
		$name = $player->getName();
		$this->check[$name][count($this->check[$name])] = [$block->x, $block->y, $block->z];
		if (count($this->check[$name]) >= 3) {
			$name = $this->check[$name][0];
			$player->sendMessage("§aFinishing adding area §6$name");
			$this->finish($player);
			unset($this->check[$name]);
			return;
		}
		$player->sendMessage("§aFirst coordinates setted");
	}

	private function finish(Player $player)
	{
		$config = $this->config;
		$name = $player->getName();
		$areaName = $this->check[$name][0];
		$x1 = $this->check[$name][1][0];
		$x2 = $this->check[$name][2][0];
		$y1 = $this->check[$name][1][1];
		$y2 = $this->check[$name][2][1];
		$z1 = $this->check[$name][1][2];
		$z2 = $this->check[$name][2][2];

		/*
		->when you use $block->getPosition(),the returned value will be the lower number.
		Example:
		->a X coord of a block is 160,it's will be 160 and 161 in two side,at middle point)
		->a X coord is -160,it's -160,-159
		*/
		$levelName = $player->getLevel()->getName();
		$config->setNested("$levelName.$areaName.x", [($x1 <= $x2) ? [$x1, $x2 + 1] : [$x2, $x1 + 1]]);
		$config->setNested("$levelName.$areaName.y", [($y1 <= $y2) ? [$y1, $y2 + 1] : [$y2, $y1 + 1]]);
		$config->setNested("$levelName.$areaName.z", [($z1 <= $z2) ? [$z1, $z2 + 1] : [$z2, $z1 + 1]]);
		$config->save();
	}

	public function onQuit(PlayerQuitEvent $ev)
	{
		if (isset($this->check[$ev->getPlayer()->getName()])) {
			unset($this->check[$ev->getPlayer()->getName()]);
		}
	}

	public function onPvP(EntityDamageByEntityEvent $ev)
	{
		$damager = $ev->getDamager();
		$entity = $ev->getEntity();
		if ($entity instanceof Painting or $entity instanceof Player) {
			$levelName = $entity->getLevel()->getName();
			if (is_array($this->config->getNested($levelName))) {
				foreach ($this->config->getNested($levelName) as $value) {
					$x1 = $value["x"][0][0];
					$x2 = $value["x"][0][1];
					$y1 = $value["y"][0][0];
					$y2 = $value["y"][0][1];
					$z1 = $value["z"][0][0];
					$z2 = $value["z"][0][1];
					if ($this->isInside($x1, $x2, $y1, $y2, $z1, $z2, $entity)) {
						$ev->setCancelled(true);
					}
				}
			}
		}
	}

	private function isInside(float $minX, float $maxX, float $minY, float $maxY, float $minZ, float $maxZ, Vector3 $vector)
	{
		if ($vector->x < $minX or $vector->x > $maxX) {
			return false;
		}
		if ($vector->y < $minY or $vector->y > $maxY) {
			return false;
		}

		return $vector->z > $minZ and $vector->z < $maxZ;
	}
}