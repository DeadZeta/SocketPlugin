<?php

namespace Lamehov\SocketPlugin\WebSocket;

use pocketmine\scheduler\Task;
use Lamehov\SocketPlugin\WebSocket\WSServer;

class WSListen extends Task {

    /** @var Plugin */
	protected $plugin;

    /** @var WSServer */
	protected $socket;

	public function __construct($plugin, $socket) {
		$this->plugin = $plugin;
		$this->socket = $socket;
	}

	public function onRun(int $currentTick) {
        $changed = $this->socket->connections;
        socket_select($changed, $null, $null, 0, 10);

        if (in_array($this->socket->socket, $changed)) {
            $socket_new = socket_accept($this->socket->socket);
            $this->socket->connections[] = $socket_new;
        
            $header = socket_read($socket_new, 1024);
            WSServer::perform_handshaking($header, $socket_new, $this->socket->bindAddress->getIp(), $this->socket->bindAddress->getPort());
        
            //TODO: onOpen
        
            $found_socket = array_search($this->socket->socket, $changed);
            unset($changed[$found_socket]);
        }

        foreach ($changed as $changed_socket) {
            while($bytes_recv = socket_recv($changed_socket, $buf, 1024, 0) >= 1) {
                if($bytes_recv === false) {
                    unset($this->socket->connections[$changed_socket]);
                    socket_close($changed_socket);
                }

                $received_text = WSServer::unmask($buf);   
                $this->plugin->onMessage($changed_socket, $received_text);
                break 2;
            }
        
            $buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
            if ($buf === false) {
                $found_socket = array_search($changed_socket, $this->socket->connections);
                unset($this->socket->connections[$found_socket]);
                //TODO: onClose
            }
        }
    }
}
?>