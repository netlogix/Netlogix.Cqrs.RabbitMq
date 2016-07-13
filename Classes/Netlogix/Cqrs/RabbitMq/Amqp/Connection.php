<?php
namespace Netlogix\Cqrs\RabbitMq\Amqp;

/*
 * This file is part of the Netlogix.Cqrs.RabbitMq package.
 */

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use TYPO3\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class Connection implements ConnectionInterface {

	/**
	 * @var AMQPChannel
	 */
	protected $channel;

	/**
	 * @var string
	 * @Flow\InjectConfiguration(path="amqpConnection.host")
	 */
	protected $host;

	/**
	 * @var integer
	 * @Flow\InjectConfiguration(path="amqpConnection.port")
	 */
	protected $port;

	/**
	 * @var string
	 * @Flow\InjectConfiguration(path="amqpConnection.user")
	 */
	protected $user;

	/**
	 * @var string
	 * @Flow\InjectConfiguration(path="amqpConnection.password")
	 */
	protected $password;

	/**
	 * @var string
	 * @Flow\InjectConfiguration(path="amqpConnection.vhost")
	 */
	protected $vhost;

	/**
	 * @return AMQPChannel
	 */
	public function getChannel() {
		if ($this->channel === NULL) {
			$this->open();
		}
		return $this->channel;
	}

	protected function open() {
		$connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
		$this->channel = $connection->channel();
	}

	protected function close() {
		$this->channel->close();
		$this->channel->getConnection()->close();
	}
}