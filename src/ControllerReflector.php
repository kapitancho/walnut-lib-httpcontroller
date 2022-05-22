<?php

namespace Walnut\Lib\HttpController;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionAttribute;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionEnum;
use Walnut\Lib\DataType\Exception\InvalidData;
use Walnut\Lib\DataType\Importer\ClassHydrator;
use Walnut\Lib\HttpMapper\Attribute\ErrorHandler;
use Walnut\Lib\HttpMapper\RequestMapper;
use Walnut\Lib\HttpMapper\RequestMatch;
use Walnut\Lib\HttpMapper\ResponseMapper;

/**
 * @package Walnut\Lib\HttpController
 */
final class ControllerReflector {

	public function __construct(
		private readonly ClassHydrator $classHydrator
	) {}

	/**
	 * @param ServerRequestInterface $request
	 * @param object $targetController
	 * @return array<int, array<string, array>>
	 */
	public function getAllMatches(ServerRequestInterface $request, object $targetController): array {
		$allMatches = [];
		foreach((new ReflectionObject($targetController))->getMethods() as $method) {
			if ($t = $method->getAttributes(RequestMatch::class, ReflectionAttribute::IS_INSTANCEOF)) {
				$m = $t[0]->newInstance();
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
	 * @param ServerRequestInterface $request
	 * @param object $targetController
	 * @return array<string, string>
	 */
	public function getAllExceptionHandlers(ServerRequestInterface $request, object $targetController): array {
		$allMatches = [];
		foreach((new ReflectionObject($targetController))->getMethods() as $method) {
			if ($t = $method->getAttributes(ErrorHandler::class, ReflectionAttribute::IS_INSTANCEOF)) {
				$m = $t[0]->newInstance();
				$allMatches[$m->className] = $method->getName();
			}
		}
		return $allMatches;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ReflectionMethod $method
	 * @param array $methodArgs
	 * @param object $targetController
	 * @return array
	 * @throws ReflectionException
	 * @throws InvalidData
	 */
	public function getMethodArgs(
		ServerRequestInterface $request,
		ReflectionMethod $method,
		array $methodArgs,
		object $targetController
	): array {
		$args = [];
		foreach($method->getParameters() as $parameter) {
			$paramValue = null;
			/**
			 * @var ReflectionAttribute[] $q
			 */
			$q = $parameter->getAttributes(RequestMapper::class, ReflectionAttribute::IS_INSTANCEOF);
			if ($q) {
				/**
				 * @var RequestMapper $n
				 */
				$n = $q[0]->newInstance();
				$paramValue = $n->mapValue($request, $parameter->getName(), $methodArgs);

				$type = $parameter->getType();
				if (($type instanceof ReflectionNamedType) && !$type->isBuiltin()) {
					/**
					 * @var class-string $typeName
					 */
					$typeName = $type->getName();
					if (enum_exists($typeName)) {
						$paramValue = $typeName::tryFrom($paramValue) ??
							$parameter->getDefaultValue() ?? null;
					} else {
						$paramValue = $this->classHydrator->importValue(
							(object)json_decode(json_encode($paramValue)),
							$typeName
						);
					}
				}
			} else {
				$type = $parameter->getType();
				if (($type instanceof ReflectionNamedType) && !$type->isBuiltin()) {
					/**
					 * @var class-string $typeName
					 */
					$typeName = $type->getName();
					if ($typeName === ServerRequestInterface::class) {
						$paramValue = $request;
					}
				}
			}
			$args[] = $paramValue;
		}
		return $args;
	}

	public function getResponseMapper(
		ReflectionMethod $method,
		object $targetController
	): ?ResponseMapper {
		if ($y = $method->getAttributes(ResponseMapper::class, ReflectionAttribute::IS_INSTANCEOF)) {
			/* *
			 * @var ResponseMapper $f
			 */
			$f = $y[0]->newInstance();
			return $f;
		}
		return null;
	}

}
