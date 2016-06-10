<?php
namespace Netlogix\Cqrs\RabbitMq\Tests\Unit\Command;

/*
 * This file is part of the Netlogix.Cqrs.RabbitMq package.
 */

use Netlogix\Cqrs\Command\AsynchronousCommandInterface;
use Netlogix\Cqrs\Command\SynchronousCommandInterface;
use Netlogix\Cqrs\RabbitMq\Command\RabbitMqCommandHandler;

class RabbitMqCommandHandlerTest extends \TYPO3\Flow\Tests\UnitTestCase {

	public function testCanHandleAsynchronousCommands() {
		$mockCommand = $this->getMockBuilder(AsynchronousCommandInterface::class)->getMockForAbstractClass();
		
		$rabbitMqCommandHandler = new RabbitMqCommandHandler();
		$this->assertTrue($rabbitMqCommandHandler->canHandle($mockCommand));
	}

	public function testCanNotHandleSynchronousCommands() {
		$mockCommand = $this->getMockBuilder(SynchronousCommandInterface::class)->getMockForAbstractClass();

		$rabbitMqCommandHandler = new RabbitMqCommandHandler();
		$this->assertFalse($rabbitMqCommandHandler->canHandle($mockCommand));
	}
}
