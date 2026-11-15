<?php

namespace Tests\Fixtures\ContributteDi\Services {

	abstract class BaseService
	{
		public function __construct() {} // inherited by registered ConcreteService
	}

	class ConcreteService extends BaseService
	{
	}

	class OwnConstructorService
	{
		public function __construct() {}
	}

	abstract class AbstractOnly
	{
		public function __construct() {} // error: Unused Tests\Fixtures\ContributteDi\Services\AbstractOnly::__construct
	}

	interface SomeInterface
	{
		public function __construct(); // error: Unused Tests\Fixtures\ContributteDi\Services\SomeInterface::__construct
	}

}

namespace Tests\Fixtures\ContributteDi\Services\Excluded {

	class ExcludedService
	{
		public function __construct() {} // error: Unused Tests\Fixtures\ContributteDi\Services\Excluded\ExcludedService::__construct
	}

}

namespace Tests\Fixtures\ContributteDi\Other {

	class OutsideService
	{
		public function __construct() {} // error: Unused Tests\Fixtures\ContributteDi\Other\OutsideService::__construct
	}

}
