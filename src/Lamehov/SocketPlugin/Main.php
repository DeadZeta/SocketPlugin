<?php

namespace Lamehov\SocketPlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use Lamehov\SocketPlugin\WebSocket\WSServer;

class Main extends PluginBase implements Listener {

	protected $WSServer;

	public function onEnable() {
		$this->WSServer = new WSServer;
		$this->WSServer->startSocket($this, 8000);
	}

	public function onDisable() {
		$this->WSServer->shutdownSocket($this);
	}

	public function onMessage($connection, $data) {
		WSServer::send($connection, ["Hello Client!"]);
	}
}

?>