<?php declare(strict_types = 1);

namespace Tests\Cases\Provider;

use Contributte\DeadCodeDetector\Provider\ContributteDiUsageProvider;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

$extension = "extensions:\n\tautoload: Contributte\\DI\\Extension\\ResourceExtension\n";
$resources = "autoload:\n\tresources:\n\t\tApp\\Model\\:\n\t\t\tpaths: [%appDir%/Model]\n\t\t\texcludes: [App\\Model\\Entity\\]\n";
$expected = [['namespace' => 'App\\Model\\', 'excludes' => ['App\\Model\\Entity\\']]];

$cases = [
	'same file' => [[$extension . $resources], $expected],
	'separate files' => [[$extension, $resources], $expected],
	'leading backslash and entity' => [["extensions:\n\tautoload: \\Contributte\\DI\\Extension\\ResourceExtension()\n" . $resources], $expected],
	'custom extension name' => [["extensions:\n\tres: Contributte\\DI\\Extension\\ResourceExtension\nres:\n\tresources:\n\t\tApp\\:\n\t\t\tpaths: [src]\n"], [['namespace' => 'App\\', 'excludes' => []]]],
	'extension not registered' => [[$resources], []],
	'other extension' => [["extensions:\n\tautoload: Foo\\Bar\n" . $resources], []],
	'unnamed extension' => [["extensions:\n\t- Contributte\\DI\\Extension\\ResourceExtension\n" . $resources], []],
];

foreach ($cases as $name => [$neonContents, $expectedResources]) {
	Toolkit::test(static function () use ($name, $neonContents, $expectedResources): void {
		Assert::same($expectedResources, ContributteDiUsageProvider::findResourcesInNeon($neonContents), $name);
	});
}
