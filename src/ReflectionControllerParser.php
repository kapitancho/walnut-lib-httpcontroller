<?php

namespace Walnut\Lib\HttpController;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;
use Walnut\Lib\DataType\Importer\ClassHydrator;
use Walnut\Lib\HttpMapper\Attribute\ErrorHandler;
use Walnut\Lib\HttpMapper\RequestMapper;
use Walnut\Lib\HttpMapper\RequestMatch;
use Walnut\Lib\HttpMapper\ResponseMapper;

/**
 * @package Walnut\Lib\HttpController
 */
final class ReflectionControllerParser implements ControllerParser {
	/**
	 * @var array<string, ReflectionMethod>
	 */
	private readonly array $reflectionMethods;

	public function __construct(
		private readonly ClassHydrator $classHydrator,
		private readonly object $targetController,
	) {
		$methods = [];
		foreach((new ReflectionObject($this->targetController))->getMethods() as $method) {
			$methods[$method->getName()] = $method;
		}
		$this->reflectionMethods = $methods;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @return array<int, array<string, array>>
	 */
	public function getAllMatches(ServerRequestInterface $request): array {
		$allMatches = [];
		foreach($this->reflectionMethods as $method) {
			if ($m = $this->getAttribute($method, RequestMatch::class)) {
				$matchResult = $m->matches($request);
				if (is_array($matchResult)) {
					$priority = $m->getMatchPriority();
					$allMatches[$priority] ??= [];
					$allMatches[$priority][$method->getName()] = $matchResult;
				}
			}
		}
		return $allMatches;
	}

	/**
	 * @return array<class-string, string>
	 */
	public function getAllExceptionHandlers(): array {
		$allMatches = [];
		foreach($this->reflectionMethods as $method) {
			if ($m = $this->getAttribute($method, ErrorHandler::class)) {
				$allMatches[$m->className] = $method->getName();
			}
		}
		return $allMatches;
	}

	public function getResponseMapper(string $methodName): ?ResponseMapper {
		return $this->getAttribute(
			$this->getMethod($methodName),
			ResponseMapper::class
		);
	}

	public function getMethodParameters(
		ServerRequestInterface $request,
		array $methodArgs,
		string $methodName
	): array {
		/**
		 * @var array<string, int|float|string|bool|null|array|object>
		 */
		$args = [];
		foreach($this->getMethod($methodName)->getParameters() as $parameter) {
			$args[$parameter->getName()] = $this->getParameterValue(
				$request, $methodArgs, $parameter
			);
		}
		return $args;
	}

	/**
	 * @param string $methodName
	 * @return callable(...): (array|object|int|string|float|bool|null)
	 */
	public function getCallable(string $methodName): callable {
		return fn(mixed ...$args): array|object|int|string|float|bool|null => /**
		 * @var array|object|int|string|float|bool|null
		 */
			$this->getMethod($methodName)->invokeArgs(
				$this->targetController,
				$args
			);
	}

	private function getParameterValue(
		ServerRequestInterface $request,
		array $methodArgs,
		ReflectionParameter $parameter
	): int|float|string|bool|null|array|object {
		$type = $parameter->getType();
		$namedType = ($type instanceof ReflectionNamedType) && !$type->isBuiltin() ? $type->getName() : null;
		if ($namedType === ServerRequestInterface::class) {
			return $request;
		}
		$n = $this->getAttribute($parameter, RequestMapper::class);
		if (!$n) {
			return null;
		}
		$paramValue = $n->mapValue($request, $parameter->getName(), $methodArgs);
		if (!$namedType) {
			return $paramValue;
		}
		/*if (enum_exists($namedType)) {
			$reflectionEnum = (new ReflectionEnum($namedType));
			return $reflectionEnum->hasConstant($paramValue) ?
				$reflectionEnum->getConstant($paramValue) : (
					$parameter->isDefaultValueAvailable() ?
						$parameter->getDefaultValue() : null
				);
		}*/
		return $this->classHydrator->importValue($paramValue, $namedType);
	}

	/**
	 * @template T of object
	 * @param ReflectionMethod|ReflectionParameter $reflector
	 * @param class-string<T> $attributeClassName
	 * @return T|null
	 */
	private function getAttribute(ReflectionMethod|ReflectionParameter $reflector, string $attributeClassName): ?object {
		if ($t = $reflector->getAttributes($attributeClassName, ReflectionAttribute::IS_INSTANCEOF)) {
			return $t[0]->newInstance();
		}
		return null;
	}

	private function getMethod(string $methodName): ReflectionMethod {
		return $this->reflectionMethods[$methodName] ??
			throw new InvalidArgumentException(
				sprintf("Method %s not found in class %s",
				$methodName, $this->targetController::class)
			);
	}
}
