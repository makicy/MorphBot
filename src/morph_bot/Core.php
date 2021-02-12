<?php

namespace morph_bot;


use pocketmine\utils\Config;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;


class Core extends PluginBase
{
	private $thread;
	private $config;

	public function onEnable(): void {
		$this->config = new Config($this->getDataFolder()."words.yml", Config::YAML);
		$words = $this->config->get(0);

		$this->thread = new DiscordThread($this->getFile(), $words);
		$this->thread->start();

		$server = $this->getServer();
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use($server): void {
			foreach($this->thread->fetchMessages() as $message)
				$server->broadcastMessage($message);
		}), 10);
	}

	public function onDisable(): void {
		$this->thread->shutdown();

		$this->config->set(0, array_values(array_unique($this->thread->fetchWords())));
		$this->config->save();
	}
}