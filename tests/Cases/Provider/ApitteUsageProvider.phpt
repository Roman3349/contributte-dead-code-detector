<?php declare(strict_types = 1);

namespace Tests\Cases\Provider;

use Contributte\DeadCodeDetector\Provider\ApitteUsageProvider;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

$extension = "extensions:\n\tapi: Apitte\\Core\\DI\\ApiExtension\n";
$plugin = "api:\n\tplugins:\n\t\tApitte\\Core\\DI\\Plugin\\CoreMappingPlugin:\n";
$disabled = ['enabled' => false, 'classes' => []];
$enabledWithoutClasses = ['enabled' => true, 'classes' => []];

$cases = [
	'plugin without config' => [[$extension . $plugin], $enabledWithoutClasses],
	'separate files' => [[$extension, $plugin], $enabledWithoutClasses],
	'leading backslashes' => [["extensions:\n\tapi: \\Apitte\\Core\\DI\\ApiExtension\napi:\n\tplugins:\n\t\t\\Apitte\\Core\\DI\\Plugin\\CoreMappingPlugin:\n"], $enabledWithoutClasses],
	'types and validator' => [
		[$extension . $plugin . "\t\t\ttypes:\n\t\t\t\tmoney: App\\MoneyMapper\n\t\t\trequest:\n\t\t\t\tvalidator: App\\Validator(@service)\n"],
		['enabled' => true, 'classes' => ['App\\MoneyMapper', 'App\\Validator']],
	],
	'non-class validator is skipped' => [[$extension . $plugin . "\t\t\trequest:\n\t\t\t\tvalidator: @validatorService\n"], $enabledWithoutClasses],
	'other plugins only' => [[$extension . "api:\n\tplugins:\n\t\tApitte\\Core\\DI\\Plugin\\CoreDecoratorPlugin:\n"], $disabled],
	'extension not registered' => [[$plugin], $disabled],
	'unnamed extension' => [["extensions:\n\t- Apitte\\Core\\DI\\ApiExtension\n" . $plugin], $disabled],
	'no plugins section' => [[$extension . "api:\n\tdebug: true\n"], $disabled],
];

foreach ($cases as $name => [$neonContents, $expected]) {
	Toolkit::test(static function () use ($name, $neonContents, $expected): void {
		Assert::same($expected, ApitteUsageProvider::findMappingConfigInNeon($neonContents), $name);
	});
}
