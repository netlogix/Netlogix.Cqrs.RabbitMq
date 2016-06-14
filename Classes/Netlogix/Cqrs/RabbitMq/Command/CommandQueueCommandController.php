<?php
namespace Netlogix\Cqrs\RabbitMq\Command;

/*
 * This file is part of the Netlogix. package.
 */

use Netlogix\Cqrs\Command\Command;
use Netlogix\Cqrs\Command\CommandBus;
use Netlogix\Cqrs\RabbitMq\Amqp\Connection;
use Netlogix\JsonApiOrg\Property\TypeConverter\Entity\PersistentObjectConverter;
use PhpAmqpLib\Channel\AMQPChannel;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Property\PropertyMapper;
use TYPO3\Flow\Property\PropertyMappingConfiguration;

/**
 * @Flow\Scope("singleton")
 */
class CommandQueueCommandController extends CommandController {

	/**
	 * @var Connection
	 * @Flow\Inject
	 */
	protected $rabbitMqConnection;

	/**
	 * @var CommandBus
	 * @Flow\Inject
	 */
	protected $commandBus;

	/**
	 * @var PropertyMapper
	 * @Flow\Inject
	 */
	protected $propertyMapper;

	/**
	 * @var PersistenceManagerInterface
	 * @Flow\Inject
	 */
	protected $persistenceManager;

	/**
	 * @var string
	 * @Flow\InjectConfiguration(path="amqpConnection.exchange")
	 */
	protected $exchangeName;

	public function listenCommand() {
		$channel = $this->getChannel();
		$queueName = 'cli-consumer-' . gethostname();
		$this->initializeQueue($channel, $queueName);

		$channel->basic_consume($queueName, '', FALSE, FALSE, FALSE, FALSE, array($this, 'processMessage'));

		while (count($channel->callbacks)) {
			$channel->wait();
		}
	}

	/**
	 * @return \PhpAmqpLib\Channel\AMQPChannel
	 */
	protected function getChannel() {
		return $this->rabbitMqConnection->getChannel();
	}

	protected function initializeQueue(AMQPChannel $channel, $queueName) {
		$queueArguments = new \PhpAmqpLib\Wire\AMQPTable(array(
			'x-dead-letter-exchange' => $this->exchangeName . '.dlx',
			'x-dead-letter-routing-key' => 'queue.' . $queueName,
		));

		list($queue, ,) = $channel->queue_declare(
			$queueName,
			FALSE,
			TRUE,
			FALSE,
			FALSE,
			FALSE,
			$queueArguments
		);

		$channel->queue_bind($queue, $this->exchangeName, '');
		$channel->queue_bind($queue, $this->exchangeName, 'queue.' . $queueName);
	}

	public function processMessage(\PhpAmqpLib\Message\AMQPMessage $msg) {
//		$this->persistenceManager->clearState();

		/** @var AMQPChannel $channel */
		$channel = $msg->delivery_info['channel'];
		try {
			$jsonApiOrgData = json_decode($msg->body, TRUE);
			$data = $jsonApiOrgData['data'];
			unset($data['id']);

			$command = $this->propertyMapper->convert($data, Command::class);
			$this->outputLine('<success>%s</success>: Execute %s', [date('H:i:s', time()), get_class($command)]);

			$this->commandBus->delegate($command);

			$channel->basic_ack($msg->delivery_info['delivery_tag']);
		} catch (\Exception $e) {
			$channel->basic_nack($msg->delivery_info['delivery_tag']);
			$this->outputLine('<error>%s: ERROR %s</error>', [date('H:i:s', time()), $e->getMessage()]);
		}

		$this->persistenceManager->persistAll();
	}
}
