<?php

namespace Walnut\Lib\HttpController;

use Psr\Http\Message\ServerRequestInterface;
use Walnut\Lib\HttpMapper\ResponseMapper;

/**
 * @package Walnut\Lib\HttpController
 */
interface ControllerParser {
	/**
	 * @param ServerRequestInterface $request
	 * @return array<int, array<string, array>>
	 */
	public function getAllMatches(ServerRequestInterface $request): array;
	/**
	 * @return array<class-string, string>
	 */
	public function getAllExceptionHandlers(): array;
	public function getResponseMapper(string $methodName): ?ResponseMapper;
	public function getMethodParameters(
		ServerRequestInterface $request,
		array $methodArgs,
		string $methodName
	): array;

	/**
	 * @param string $methodName
	 * @return callable(...):(array|object|int|string|float|bool|null)
	 */
	public function getCallable(string $methodName): callable;
}