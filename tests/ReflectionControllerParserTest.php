<?php

namespace Walnut\Lib\HttpController;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;
use Walnut\Lib\DataType\Importer\ClassHydrator;
use Walnut\Lib\HttpMapper\Attribute\ErrorHandler;
use Walnut\Lib\HttpMapper\Attribute\RequestMapper\FromRoute;
use Walnut\Lib\HttpMapper\Attribute\RequestMapper\FromRouteParams;
use Walnut\Lib\HttpMapper\Attribute\RequestMatch\DefaultRouteMatch;
use Walnut\Lib\HttpMapper\Attribute\ResponseMapper\NoContentResponse;

final class ReflectionControllerParserTestController {
	#[ErrorHandler(RuntimeException::class)]
	public function onRuntimeException(): void {}
	#[ErrorHandler(LogicException::class)]
	public function onLogicException(): void {}
	#[NoContentResponse]
	public function responseHandler(): void {}
	#[DefaultRouteMatch]
	public function routeMatch(): void {}
	public function params(
		#[FromRoute('a')]
		string $routeParams,
		?int $shouldBeNull,
		#[FromRouteParams]
		stdClass $object,
		ServerRequestInterface $request
	): void {}
	public function getValue(int $a): int { return $a + 3; }
}

final class ReflectionControllerParserTest extends TestCase {

	private MockObject $classHydrator;
	private MockObject $serverRequest;
	private ReflectionControllerParser $controllerParser;

	protected function setUp(): void {
		$this->classHydrator = $this->createMock(ClassHydrator::class);
		$this->serverRequest = $this->createMock(ServerRequestInterface::class);
		$this->controllerParser = new ReflectionControllerParser(
			$this->classHydrator,
			new ReflectionControllerParserTestController()
		);
	}

	public function testGetAllMatches(): void {
		$this->assertEquals([PHP_INT_MIN => ['routeMatch' => []]],
			$this->controllerParser->getAllMatches($this->serverRequest));
	}

	public function testGetAllExceptionHandlers(): void {
		$this->assertEquals([
			LogicException::class => 'onLogicException',
			RuntimeException::class => 'onRuntimeException',
		], $this->controllerParser->getAllExceptionHandlers());
	}

	public function testNullResponseMapper(): void {
		$this->assertNull(
			$this->controllerParser->getResponseMapper('onLogicException')
		);
	}

	public function testExistingResponseMapper(): void {
		$this->assertInstanceOf(
			NoContentResponse::class,
			$this->controllerParser->getResponseMapper('responseHandler')
		);
	}

	public function testGetMethodParameters(): void {
		$this->classHydrator->expects($this->once())->method('importValue')
			->willReturn(new stdClass);
		$this->assertEquals(
			[
				'routeParams' => 'B',
				'shouldBeNull' => null,
				'object' => new stdClass,
				'request' => $this->serverRequest
			],
			$this->controllerParser->getMethodParameters(
				$this->serverRequest,
				['a' => 'B'],
				'params'
			)
		);
	}

	public function testGetCallable(): void {
		$this->assertEquals(
			5,
			$this->controllerParser->getCallable('getValue')(2)
		);
	}

	public function testInvalidMethod(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->controllerParser->getCallable('invalidMethod')();
	}
}
