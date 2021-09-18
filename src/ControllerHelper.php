<?php

namespace Walnut\Lib\HttpController;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @package Walnut\Lib\HttpController
 */
interface ControllerHelper {
	/**
	 * @param ServerRequestInterface $request
	 * @param object $targetController
	 * @return ResponseInterface
	 * @throws ControllerException|ControllerHelperException
	 */
	public function wire(ServerRequestInterface $request, object $targetController): ResponseInterface;
}