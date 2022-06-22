<?php

namespace Walnut\Lib\HttpController;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * @package Walnut\Lib\HttpController
 */
interface ControllerHelper {
	/**
	 * @param object $targetController
	 * @return RequestHandlerInterface
	 */
	public function wire(object $targetController): RequestHandlerInterface;
}