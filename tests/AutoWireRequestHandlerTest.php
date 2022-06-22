<?php

namespace Walnut\Lib\HttpController;

use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Walnut\Lib\HttpMapper\ResponseBuilder;
use Walnut\Lib\HttpMapper\ResponseMapper;
use Walnut\Lib\HttpMapper\ResponseRenderer;
use Walnut\Lib\HttpMapper\ViewRenderer;

final class AutoWireRequestHandlerTest extends TestCase {

	private AutoWireRequestHandler $requestHandler;
	private MockObject $parser;
	private MockObject $responseBuilder;
	private MockObject $responseRenderer;
	private MockObject $viewRenderer;
	private MockObject $serverRequest;
	private MockObject $response;
	private MockObject $responseMapper;

	protected function setUp(): void {
		$this->serverRequest = $this->createMock(ServerRequestInterface::class);
		$this->response = $this->createMock(ResponseInterface::class);
		$this->responseMapper = $this->createMock(ResponseMapper::class);
		$this->requestHandler = new AutoWireRequestHandler(
			$this->parser = $this->createMock(ControllerParser::class),
			$this->responseBuilder = $this->createMock(ResponseBuilder::class),
			$this->responseRenderer = $this->createMock(ResponseRenderer::class),
			$this->viewRenderer = $this->createMock(ViewRenderer::class),
		);
	}

	public function testOk(): void {
		$this->parser->expects($this->once())->method('getAllMatches')
			->willReturn([['methodName' => []]]);
		$this->parser->expects($this->once())->method('getMethodParameters')
			->willReturn([]);
		$this->parser->expects($this->once())->method('getCallable')
			->willReturn(fn() => 1);
		$this->parser->expects($this->once())->method('getResponseMapper')
			->willReturn($this->responseMapper);
		$this->responseMapper->expects($this->once())->method('mapValue')
			->willReturn($this->response);
		$result = $this->requestHandler->handle($this->serverRequest);
		$this->assertEquals($this->response, $result);
	}

	public function testNoRouteMatches(): void {
		$this->parser->expects($this->once())->method('getAllMatches')
			->willReturn([]);
		$this->expectException(ControllerHelperException::class);
		$this->requestHandler->handle($this->serverRequest);
	}

	public function testNoExceptionHandlers(): void {
		$this->parser->expects($this->once())->method('getAllMatches')
			->willReturn([['methodName' => []]]);
		$this->parser->expects($this->once())->method('getMethodParameters')
			->willReturn([]);
		$this->parser->expects($this->once())->method('getCallable')
			->willThrowException(new LogicException());

		$this->expectException(ControllerHelperException::class);
		$this->requestHandler->handle($this->serverRequest);
	}

	public function testThroughExceptionHandler(): void {
		$this->parser->expects($this->once())->method('getAllMatches')
			->willReturn([['methodName' => []]]);
		$this->parser->expects($this->once())->method('getMethodParameters')
			->willReturn([]);
		$this->parser->expects($this->exactly(2))->method('getCallable')
			->willReturnCallback(fn(string $methodName) =>
				$methodName === 'methodName' ? throw new LogicException() : static fn() => 1);
		$this->parser->expects($this->once())->method('getAllExceptionHandlers')
			->willReturn([LogicException::class => 'errorHandler']);
		$this->parser->expects($this->once())->method('getResponseMapper')
			->willReturn($this->responseMapper);
		$this->responseMapper->expects($this->once())->method('mapValue')
			->willReturn($this->response);
		$result = $this->requestHandler->handle($this->serverRequest);
		$this->assertEquals($this->response, $result);
	}

}
