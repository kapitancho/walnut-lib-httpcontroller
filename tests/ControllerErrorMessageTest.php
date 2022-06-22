<?php

namespace Walnut\Lib\HttpController;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

final class ControllerErrorMessageTest extends TestCase {
	public function testOk(): void {
		$this->assertInstanceOf(ControllerErrorMessage::class,
			new ControllerErrorMessage(402));
	}

	public function testOutOfRangeMin(): void {
		$this->expectException(OutOfBoundsException::class);
		$this->assertInstanceOf(ControllerErrorMessage::class,
			new ControllerErrorMessage(201));
	}

	public function testOutOfRangeMax(): void {
		$this->expectException(OutOfBoundsException::class);
		$this->assertInstanceOf(ControllerErrorMessage::class,
			new ControllerErrorMessage(601));
	}
}
