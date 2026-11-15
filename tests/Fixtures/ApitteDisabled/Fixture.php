<?php declare(strict_types = 1);

namespace Tests\Fixtures\ApitteDisabled;

use Apitte\Core\Annotation\Controller\Method;
use Apitte\Core\Annotation\Controller\Path;
use Apitte\Core\Http\ApiRequest;
use Apitte\Core\Http\ApiResponse;
use Apitte\Core\UI\Controller\IController;

#[Path('/users')]
class UserController implements IController
{
	#[Path('/')]
	#[Method('GET')]
	public function list(ApiRequest $request, ApiResponse $response): ApiResponse // error: Unused Tests\Fixtures\ApitteDisabled\UserController::list
	{
		return $response;
	}
}
