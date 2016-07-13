<?php
namespace Netlogix\Cqrs\RabbitMq\Command;

/*
 * This file is part of the Netlogix.Cqrs.RabbitMq package.
 */

use Netlogix\Cqrs\Command\AbstractCommand;
use Netlogix\Cqrs\Log\CommandLogEntry;
use Netlogix\Cqrs\RabbitMq\Amqp\ConnectionInterface;
use PhpAmqpLib\Message\AMQPMessage;
use TYPO3\Flow\Annotations as Flow;
use Netlogix\Cqrs\Command\AsynchronousCommandInterface;
use Netlogix\Cqrs\Command\CommandHandlerInterface;
use Netlogix\Cqrs\Command\CommandInterface;
use Netlogix\JsonApiOrg\Resource\RelationshipIterator;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;

/**
 * Handles commands and executes them directly
 */
class RabbitMqCommandHandler implements CommandHandlerInterface {

	/**
	 * @var ConnectionInterface
	 * @Flow\Inject
	 */
	protected $rabbitMqConnection;

	/**
	 * @var RelationshipIterator
	 * @Flow\Inject
	 */
	protected $jsonApiRelationshipIterator;

	/**
	 * @var PersistenceManagerInterface
	 * @Flow\Inject
	 */
	protected $persistenceManager;

	/**
	 * @var string
	 * @Flow\InjectConfiguration(path="amqpConnection.exchange")
	 */
	protected $amqpExchange;

	/**
	 * Check whether a command handler can handle a given command
	 *
	 * @param CommandInterface $command
	 * @return boolean
	 */
	public function canHandle(CommandInterface $command) {
		return $command instanceof AsynchronousCommandInterface;
	}

	/**
	 * Execute the given command
	 *
	 * @param CommandInterface $command
	 */
	public function handle(CommandInterface $command) {
		if (!($command instanceof AbstractCommand)) {
			throw new \InvalidArgumentException('RabbitMq can only handle AbstractCommands', 1465562089);
		}

		$commandJson = $this->encodeCommand($command);

		$message = new AMQPMessage($commandJson, array('content_type' => 'application/json', 'delivery_mode' => 2));
		$this->rabbitMqConnection->getChannel()->basic_publish($message, $this->amqpExchange);
	}

	/**
	 * Encodes the given command as JSON.
	 *
	 * @param AbstractCommand $command
	 * @return string
	 */
	protected function encodeCommand(AbstractCommand $command) {
		// Due to some shortcomings in json api encoder the command must be known to the persistence to create URIs.
		// If the command is not (yet) persisted it is temporarily added and later removed.
		$commandLogEntry = NULL;
		if ($this->persistenceManager->getObjectByIdentifier($command->getCommandId(), CommandLogEntry::class) === NULL) {
			$commandLogEntry = new CommandLogEntry($command);
			$this->persistenceManager->add($commandLogEntry);
		}

		$commandJson = json_encode($this->jsonApiRelationshipIterator->createTopLevel($command));

		if ($commandLogEntry !== NULL) {
			$this->persistenceManager->remove($commandLogEntry);
		}

		return $commandJson;
	}
}
