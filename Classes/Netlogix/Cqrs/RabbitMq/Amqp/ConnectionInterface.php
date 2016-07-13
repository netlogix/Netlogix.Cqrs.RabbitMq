<?php
namespace Netlogix\Cqrs\RabbitMq\Amqp;

/*
 * This file is part of the Netlogix.Cqrs.RabbitMq package.
 */

use PhpAmqpLib\Channel\AMQPChannel;
use TYPO3\Flow\Annotations as Flow;

interface ConnectionInterface
{
	/**
	 * @return AMQPChannel
	 */
	public function getChannel();
}