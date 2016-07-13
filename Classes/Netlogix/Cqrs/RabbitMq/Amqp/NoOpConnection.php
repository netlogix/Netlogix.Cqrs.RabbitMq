<?php
namespace Netlogix\Cqrs\RabbitMq\Amqp;

/*
 * This file is part of the Netlogix.Cqrs.RabbitMq package.
 */

use PhpAmqpLib\Channel\AMQPChannel;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Object\ObjectManagerInterface;

/**
 * @Flow\Scope("singleton")
 */
class NoOpConnection implements ConnectionInterface
{
	/**
	 * @var AMQPChannel
	 */
	protected $channel;

	/**
	 * @var ObjectManagerInterface
	 * @Flow\Inject
	 */
	protected $objectManager;

	/**
	 * @return AMQPChannel
	 */
	public function getChannel()
	{
		if (!$this->channel) {
			/** @var \PHPUnit_Framework_SkippedTestCase $testCase */
			$testCase = $this->objectManager->get('PHPUnit_Framework_SkippedTestCase', ConnectionInterface::class,
				'getChannel');
			$this->channel = $testCase->getMock(AMQPChannel::class, [], [], '', false);
		}
		return $this->channel;
	}
}