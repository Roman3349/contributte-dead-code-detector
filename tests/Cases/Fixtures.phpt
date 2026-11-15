<?php declare(strict_types = 1);

namespace Tests\Cases;

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\DeadCodeAnalyser;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Every fixture is analysed by PHPStan with shipmonk/dead-code-detector and this extension,
 * reported errors must match the "// error: ..." comments in the fixture.
 */
$fixtures = [
	'Apitte',
	'ApitteWithoutMapping',
	'ApitteDisabled',
	'ContributteDi',
	'ContributteDiDisabled',
	'Latte',
	'LatteDisabled',
];

foreach ($fixtures as $fixture) {
	Toolkit::test(static function () use ($fixture): void {
		$dir = __DIR__ . '/../Fixtures/' . $fixture;

		Assert::same(
			DeadCodeAnalyser::expected($dir . '/Fixture.php'),
			DeadCodeAnalyser::analyse($dir . '/phpstan.neon', $dir),
			$fixture,
		);
	});
}
