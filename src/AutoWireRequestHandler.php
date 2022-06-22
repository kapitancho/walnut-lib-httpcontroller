<?php

namespace Walnut\Lib\HttpController;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Walnut\Lib\HttpMapper\ResponseBuilder;
use Walnut\Lib\HttpMapper\ResponseRenderer;
use Walnut\Lib\HttpMapper\ViewRenderer;

/**
 * @package Walnut\Lib\HttpController
 */
final class AutoWireRequestHandler implements RequestHandlerInterface {

	public function __construct(
		private readonly ControllerParser $parser,

		private readonly ResponseBuilder $responseBuilder,
		private readonly ResponseRenderer $responseRenderer,
		private readonly ViewRenderer $viewRenderer,

	) {}

	public function handle(ServerRequestInterface $request): ResponseInterface {
		try {
			['method' => $method, 'args' => $args] = $this->findMatchingMethod($request);

			$responseMapper = null;

			try {
				$args = $this->parser->getMethodParameters($request, $args, $method);
				$result = $this->parser->getCallable($method)(... $args);
			} catch (Throwable $e) {
				$exHandler = $this->findMatchingExceptionHandler($e) ?? throw $e;
				$result = $this->parser->getCallable($exHandler)($e, $request);

				$responseMapper = $this->parser->getResponseMapper($exHandler);
			}
			$responseMapper ??= $this->parser->getResponseMapper($method);

			if ($responseMapper && !($result instanceof ResponseInterface)) {
				$result = $responseMapper->mapValue($result,
					$this->responseBuilder,
					$this->responseRenderer,
					$this->viewRenderer,
				);
			}
			return ($result instanceof ResponseInterface) ? $result :
				throw new ControllerHelperException("Invalid response detected");

		} catch (Throwable $ex) {
			throw new ControllerHelperException(
				sprintf("Invalid controller: %s", $ex->getMessage()), previous: $ex);
		}
	}

	private function findMatchingExceptionHandler(Throwable $e): ?string {
		foreach($this->parser->getAllExceptionHandlers() as $className => $exHandler) {
			if ($e instanceof $className) {
				return $exHandler;
			}
		}
		return null;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @return array{method: string, args: array}
	 */
	private function findMatchingMethod(ServerRequestInterface $request): array {
		$allMatches = $this->parser->getAllMatches($request);
		if ($allMatches === []) {
			throw new ControllerHelperException("No route match found");
		}
		$highestPriority = max(array_keys($allMatches));
		/**
		 * @var string $bestMatch
		 */
		$bestMatch = array_key_first($allMatches[$highestPriority]);
		$bestMatchArgs = $allMatches[$highestPriority][$bestMatch] ?? [];
		return ['method' => $bestMatch, 'args' => $bestMatchArgs];
	}

}