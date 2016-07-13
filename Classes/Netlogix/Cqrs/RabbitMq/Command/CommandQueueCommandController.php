<?php
namespace Netlogix\Cqrs\RabbitMq\Command;

/*
 * This file is part of the Netlogix. package.
 */

use Doctrine\ORM\OptimisticLockException;
use Netlogix\Cqrs\Command\Command;
use Netlogix\Cqrs\Command\CommandBus;
use Netlogix\Cqrs\RabbitMq\Amqp\ConnectionInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Core\Booting\Exception\SubProcessException;
use TYPO3\Flow\Core\Booting\Scripts;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Property\PropertyMapper;

/**
 * @Flow\Scope("singleton")
 */
class CommandQueueCommandController extends CommandController {

	/**
	 * @var ConnectionInterface
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

	/**
	 * @var array
	 * @Flow\InjectConfiguration(package="TYPO3.Flow")
	 */
	protected $flowSettings;

	/**
	 * Listen for commands.
	 */
	public function listenCommand() {
		$channel = $this->getChannel();
		$queueName = 'cli-consumer-' . gethostname();
		$this->initializeQueue($channel, $queueName);

		$channel->basic_qos(null, 1, null);
		$channel->basic_consume($queueName, '', FALSE, FALSE, FALSE, FALSE, array($this, 'processMessage'));

		while (count($channel->callbacks)) {
			$channel->wait();
		}
	}

	/**
	 * Process a given command. This is an internal command used for processing commands in sub processes.
	 *
	 * @internal
	 * @param string $commandData JSON encoded command data taken from the queue
	 */
	public function processCommand($commandData) {
		try {
			$jsonApiOrgData = json_decode($commandData, TRUE);
			$data = $jsonApiOrgData['data'];
			unset($data['id']);

			$this->runCommand($data, 0);
			$this->outputLine();
		} catch (\Exception $e) {
			$this->outputLine('<error>%s: ERROR %s</error>', [date('H:i:s'), $e->getMessage()]);
			$this->response->setExitCode(1);
		}
	}

	/**
	 * @param array $data
	 * @param int $retries
	 * @throws OptimisticLockException
	 * @throws \TYPO3\Flow\Property\Exception
	 * @throws \TYPO3\Flow\Security\Exception
	 */
	protected function runCommand($data, $retries) {
		$command = $this->propertyMapper->convert($data, Command::class);

		$this->outputLine('<success>%s</success>: Execute %s', [date('H:i:s'), get_class($command)]);

		$this->commandBus->delegate($command);
		try {
			$this->persistenceManager->persistAll();
		} catch (OptimisticLockException $e) {
			$this->outputLine('<error>%s: ERROR %s, retry</error>', [date('H:i:s'), $e->getMessage()]);
			if ($retries < 5) {
				$this->runCommand($data, $retries + 1);
			} else {
				throw $e;
			}
		}
	}

	/**
	 * @return \PhpAmqpLib\Channel\AMQPChannel
	 */
	protected function getChannel() {
		return $this->rabbitMqConnection->getChannel();
	}

	/**
	 * @param AMQPChannel $channel
	 * @param string $queueName
	 */
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

	/**
	 * @param \PhpAmqpLib\Message\AMQPMessage $msg
	 */
	public function processMessage(\PhpAmqpLib\Message\AMQPMessage $msg) {
		/** @var AMQPChannel $channel */
		$channel = $msg->delivery_info['channel'];
		try {
			Scripts::executeCommand('netlogix.cqrs.rabbitmq:commandqueue:process', $this->flowSettings, TRUE, array('data' => $this->encodeJsonCliCommandArgument($msg->body)));

			$channel->basic_ack($msg->delivery_info['delivery_tag']);
		} catch (SubProcessException $e) {
			$channel->basic_nack($msg->delivery_info['delivery_tag']);
			fwrite(STDERR, $e->getMessage() . PHP_EOL);
		}
	}

	/**
	 * Flow treats = signs specially when parsing command line arguments. This leads to truncated arguments. To circumvent this
	 * we replace this with its utf8 escape sequence. json_decode() replaces it again for us.
	 *
	 * @param string $argument
	 * @return string
	 */
	protected function encodeJsonCliCommandArgument($argument) {
		return str_replace('=', '\\u003D', $argument);
	}
}
