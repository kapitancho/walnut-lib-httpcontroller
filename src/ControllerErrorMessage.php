<?php

namespace Walnut\Lib\HttpController;

use OutOfBoundsException;

/**
 * @package Walnut\Lib\HttpController
 */
final class ControllerErrorMessage {
	public function __construct(public /*readonly*/ int $httpStatusCode, public /*readonly*/ ?string $message = null) {
		if ($httpStatusCode < 400 || $httpStatusCode >= 600) {
			throw new OutOfBoundsException("Invalid status code: $httpStatusCode");
		}
	}
}