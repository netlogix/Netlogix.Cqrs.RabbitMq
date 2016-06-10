<?php
namespace Netlogix\Cqrs\RabbitMq\Tests\Functional\Command;

/*
 * This file is part of the Netlogix. package.
 */

use Netlogix\Cqrs\Command\CommandBus;
use Netlogix\Cqrs\RabbitMq\Command\RabbitMqCommandHandler;
use Netlogix\Cqrs\RabbitMq\Tests\Functional\Fixtures\AsynchronousTestCommand;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqCommandHandlerTest extends \TYPO3\Flow\Tests\FunctionalTestCase {

	protected static $testablePersistenceEnabled = true;

	/**
	 * @var AMQPChannel
	 */
	protected $amqpChannel;

	/**
	 * @var string
	 */
	protected $amqpQueue;

	public function setUp() {
		parent::setUp();

		try {
			$connection = new AMQPStreamConnection('localhost', '5672', 'guest', 'guest', 'commandqueue');
			$this->amqpChannel = $connection->channel();
			$this->amqpChannel->exchange_declare('commands_testing', 'topic', FALSE, TRUE, FALSE);
			list($this->amqpQueue, ,) = $this->amqpChannel->queue_declare(
				'functional_tests',
				FALSE,
				TRUE,
				FALSE,
				FALSE,
				FALSE,
				NULL
			);
			$this->amqpChannel->queue_purge($this->amqpQueue);
			$this->amqpChannel->queue_bind($this->amqpQueue, 'commands_testing', '#');
		} catch (\ErrorException $e) {

		}
	}

	public function tearDown() {
		parent::tearDown();

		if ($this->amqpChannel !== NULL) {
			$this->amqpChannel->close();
			if ($this->amqpChannel->getConnection() !== NULL) {
				$this->amqpChannel->getConnection()->close();
			}
		}
		$this->amqpChannel = NULL;
		$this->amqpQueue = NULL;
	}

	public function testAsynchronousTestCommandIsTransferred() {
		if ($this->amqpChannel === NULL) {
			$this->markTestSkipped('RabbitMq server is not available');
		}

		$command = new AsynchronousTestCommand('3b2635c5-f261-4d02-ae5f-6e98df52bff1', 'foobar');
		
		$rabbitMqCommandHandler = new RabbitMqCommandHandler();
		$rabbitMqCommandHandler->handle($command);

		$test = $this;
		$this->amqpChannel->basic_consume($this->amqpQueue, '', FALSE, FALSE, FALSE, FALSE, function (\PhpAmqpLib\Message\AMQPMessage $msg) use ($test) {
			$test->assertJson($msg->body);
		});
		$this->amqpChannel->wait(NULL, FALSE, 1);
	}

	public function testRabbitMqCommandHandlerIsUsedByCommandBus() {
		if ($this->amqpChannel === NULL) {
			$this->markTestSkipped('RabbitMq server is not available');
		}

		$command = new AsynchronousTestCommand('3b2635c5-f261-4d02-ae5f-6e98df52bff1', 'foobar');

		$commandBus = new CommandBus();
		$commandBus->delegate($command);

		$test = $this;
		$this->amqpChannel->basic_consume($this->amqpQueue, '', FALSE, FALSE, FALSE, FALSE, function (\PhpAmqpLib\Message\AMQPMessage $msg) use ($test) {
			$test->assertJson($msg->body);
		});
		$this->amqpChannel->wait(NULL, FALSE, 1);

	}

}
