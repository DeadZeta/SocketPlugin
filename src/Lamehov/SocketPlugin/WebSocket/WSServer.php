<?php

namespace Lamehov\SocketPlugin\WebSocket;

use pocketmine\scheduler\Task;
use pocketmine\plugin\Plugin;
use raklib\utils\InternetAddress;

class WSServer {

    	/** @var Socket */
	public $socket;

    	/** @var Task */
	public $WSListen;

    	/** @var Plugin */
	public $plugin;

    	/** @var array */
	public $connections;

    	/** @var InternetAddress */
	public $bindAddress;

	public function startSocket(Plugin $plugin, int $port = 8000) {
		$this->plugin = $plugin;
		$this->bindAddress = new InternetAddress("0.0.0.0", $port, 4);

		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if(false === $this->socket) {
			$this->plugin->getLogger()->fatal('Error: ' . socket_strerror(socket_last_error()));
			$this->plugin->getPluginLoader()->disablePlugin($this->plugin);
		}

		$bind = socket_bind($this->socket, $this->bindAddress->getIp(), $this->bindAddress->getPort());
		if (false === $bind) {
			$this->plugin->getLogger()->fatal('Error: ' . socket_strerror(socket_last_error()));
			$this->plugin->getPluginLoader()->disablePlugin($this->plugin);
		}

		socket_set_nonblock($this->socket);
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_listen($this->socket);

		$this->connections = array($this->socket);

		$this->plugin->getLogger()->notice("Starting WebSocket Server " . $this->bindAddress->toString());
		$this->WSListen = $this->plugin->getScheduler()->scheduleRepeatingTask(new WSListen($this->plugin, $this), 1);
	}

	public function shutdownSocket(Plugin $plugin) {
		$this->plugin->getScheduler()->cancelTask($this->WSListen->getTaskId());
		$this->plugin->getLogger()->notice("Stoped WebSocket Server " . $this->bindAddress->toString());

		socket_close($this->socket);
        if (!empty($this->connections)) {
            foreach ($this->connections as $connect) {
            	if (is_resource($connect)) {
	            	socket_shutdown($connect);
	            	socket_close($connect);
	            }
            }
        }
	}

	public static function send($connection, array $data) {
		$data = self::mask(json_encode($data));
		@socket_write($connection, $data, strlen($data));
	}

	public static function unmask($text) {
        $length = ord($text[1]) & 127;
        if($length == 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        }
        elseif($length == 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        }
        else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i%4];
        }
        return $text;
    }

    public static function mask($text) {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127, $length);
        return $header.$text;
    }

    public static function perform_handshaking($receved_header, $client_conn, $host, $port) {
        $headers = array();
        $lines = preg_split("/\r\n/", $receved_header);
        foreach($lines as $line)
        {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
            {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    	//hand shaking header
        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "WebSocket-Origin: $host\r\n" .
        "WebSocket-Location: ws://$host:$port/\r\n".
        "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        socket_write($client_conn, $upgrade, strlen($upgrade));
    }
}

?>
