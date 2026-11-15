<?php declare(strict_types = 1);

namespace Tests\Cases\Provider;

use Contributte\DeadCodeDetector\Provider\LatteUsageProvider;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

$cases = [
	'templateType' => ['{templateType App\Template\Params}', ['App\Template\Params']],
	'varType' => ['{varType App\Model\User $user}', ['App\Model\User']],
	'nullable and union' => ['{varType ?App\A|App\B|null $x}', ['App\A', 'App\B', 'null']],
	'generics' => ['{varType array<int, \App\Item> $items}', ['array', 'int', 'App\Item']],
	'leading backslash' => ['{templateType \App\Params}', ['App\Params']],
	'scalar only' => ['{varType string $title}', ['string']],
	'multiple tags' => ["{templateType App\\A}\n<p>{\$x}</p>\n{varType App\\B \$b}", ['App\A', 'App\B']],
	'no type tags' => ['{var $x = 1}{$x}', []],
];

foreach ($cases as $name => [$template, $expected]) {
	Toolkit::test(static function () use ($name, $template, $expected): void {
		Assert::same($expected, LatteUsageProvider::findClassesInTemplate($template), $name);
	});
}
