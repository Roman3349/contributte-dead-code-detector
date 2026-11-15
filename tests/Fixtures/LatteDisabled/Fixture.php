<?php declare(strict_types = 1);

namespace Tests\Fixtures\LatteDisabled;

use Latte\Engine;

class Parameters
{
	public function getTitle(): string // error: Unused Tests\Fixtures\LatteDisabled\Parameters::getTitle
	{
		return '';
	}
}

function render(Engine $latte): void
{
	$latte->render('page.latte', new Parameters());
}
