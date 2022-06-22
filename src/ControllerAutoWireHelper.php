<?php

namespace Walnut\Lib\HttpController;

use Psr\Http\Server\RequestHandlerInterface;
use Walnut\Lib\DataType\Importer\ClassHydrator;
use Walnut\Lib\HttpMapper\ResponseBuilder;
use Walnut\Lib\HttpMapper\ResponseRenderer;
use Walnut\Lib\HttpMapper\ViewRenderer;

/**
 * @package Walnut\Lib\HttpController
 */
final class ControllerAutoWireHelper implements ControllerHelper {

	public function __construct(
		private readonly ClassHydrator $classHydrator,
		private readonly ResponseBuilder $responseBuilder,
		private readonly ResponseRenderer $responseRenderer,
		private readonly ViewRenderer $viewRenderer,
	) {}

	public function wire(object $targetController): RequestHandlerInterface {
		return new AutoWireRequestHandler(
			new ReflectionControllerParser($this->classHydrator, $targetController),
			$this->responseBuilder,
			$this->responseRenderer,
			$this->viewRenderer
		);
	}

}