<?php

namespace Walnut\Lib\HttpController;

use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Walnut\Lib\DataType\Importer\ClassHydrator;
use Walnut\Lib\HttpMapper\ResponseBuilder;
use Walnut\Lib\HttpMapper\ResponseRenderer;
use Walnut\Lib\HttpMapper\ViewRenderer;

final class ControllerAutoWireHelperTest extends TestCase {
	public function testOk(): void {
		$this->assertInstanceOf(RequestHandlerInterface::class,
			(new ControllerAutoWireHelper(
				$this->createMock(ClassHydrator::class),
				$this->createMock(ResponseBuilder::class),
				$this->createMock(ResponseRenderer::class),
				$this->createMock(ViewRenderer::class),
			))->wire($this));
	}
}
