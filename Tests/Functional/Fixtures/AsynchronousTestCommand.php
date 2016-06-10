<?php
namespace Netlogix\Cqrs\RabbitMq\Tests\Functional\Fixtures;

/*
 * This file is part of the Netlogix.Cqrs.RabbitMq package.
 */

use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\Cqrs\Command\AbstractCommand;
use Netlogix\Cqrs\Command\AsynchronousCommandInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\ReadModelInterface;

/**
 * @JsonApi\ExposeType(packageKey = "Netlogix.JsonApiOrg.AnnotationGenerics", controllerName = "GenericModel", typeName = "cqrs/asynchronous-test-command")
 */
class AsynchronousTestCommand extends AbstractCommand implements AsynchronousCommandInterface, ReadModelInterface {

	/**
	 * @var string
	 * @JsonApi\ExposeProperty
	 */
	protected $property;

	public function __construct($commandId, $property) {
		$this->commandId = $commandId;
		$this->Persistence_Object_Identifier = $commandId;
		$this->property = $property;
	}

	public function execute() {
		// Do nothing
	}

	/**
	 * @return string
	 */
	public function getProperty() {
		return $this->property;
	}
}