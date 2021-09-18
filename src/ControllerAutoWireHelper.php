<?php

namespace Walnut\Lib\HttpController;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use ReflectionObject;
use Throwable;
use Walnut\Lib\HttpMapper\ResponseBuilder;
use Walnut\Lib\HttpMapper\ResponseRenderer;

/**
 * @package Walnut\Lib\HttpController
 */
final class ControllerAutoWireHelper implements ControllerHelper {

	public function __construct(
		private /*readonly*/ ResponseBuilder $responseBuilder,
		private /*readonly*/ ControllerReflector $controllerReflector,
		private /*readonly*/ ResponseRenderer $responseRenderer
	) {}

	/**
	 * @param ServerRequestInterface $request
	 * @param object $targetController
	 * @return ResponseInterface
	 * @throws ControllerException|ControllerHelperException
	 */
	public function wire(ServerRequestInterface $request, object $targetController): ResponseInterface {
		try {
			$allMatches = $this->controllerReflector->getAllMatches($request, $targetController);
			if ($allMatches === []) {
				throw new ControllerHelperException("No route match found");
			}
			$highestPriority = max(array_keys($allMatches));
			/**
			 * @var string $bestMatch
			 */
			$bestMatch = array_key_first($allMatches[$highestPriority]);
			$bestMatchArgs = $allMatches[$highestPriority][$bestMatch] ?? [];

			$result = null;
			$responseMapper = null;

			try {
				$method = (new ReflectionObject($targetController))->getMethod($bestMatch);
				$args = $this->controllerReflector->getMethodArgs($request, $method, $bestMatchArgs, $targetController);

				/**
				 * @var mixed $result
				 */
				$result = $method->invokeArgs($targetController, $args);
			} catch (Throwable $rrex) {
				$handlers = $this->controllerReflector
					->getAllExceptionHandlers($request, $targetController);

				$found = false;
				foreach($handlers as $className => $exHandler) {
					if ($rrex instanceof $className) {
						$exMethod = (new ReflectionObject($targetController))->getMethod($exHandler);
						/**
						 * @var mixed
						 */
						$result = $exMethod->invoke($targetController, $rrex);

						$responseMapper = $this->controllerReflector->getResponseMapper($exMethod, $targetController);
						$found = true;
						break;
					}
				}
				if (!$found) {
					throw $rrex;
				}
			}
			if ($result instanceof ControllerErrorMessage) {
				$message = $result->message;
				return $message !== '' ?
					$this->responseBuilder->jsonResponse(['error' => $message], $result->httpStatusCode) :
					$this->responseBuilder->emptyResponse($result->httpStatusCode);
			}

			$responseMapper ??= $this->controllerReflector->getResponseMapper($method, $targetController);
			if ($responseMapper) {
				$result = $responseMapper->mapValue($result,
					$this->responseBuilder,
					$this->responseRenderer
				);
			}
			return ($result instanceof ResponseInterface) ? $result :
				throw new ControllerHelperException("Invalid response detected");
		} catch (ReflectionException $rex) {
			throw new ControllerHelperException("Invalid controller", previous: $rex);
		}
	}


}