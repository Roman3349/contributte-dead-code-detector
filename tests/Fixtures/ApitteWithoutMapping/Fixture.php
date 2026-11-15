<?php declare(strict_types = 1);

namespace Tests\Fixtures\ApitteWithoutMapping;

use Apitte\Core\Annotation\Controller\Method;
use Apitte\Core\Annotation\Controller\Path;
use Apitte\Core\Annotation\Controller\RequestBody;
use Apitte\Core\Http\ApiRequest;
use Apitte\Core\Http\ApiResponse;
use Apitte\Core\UI\Controller\IController;

#[Path('/users')]
class UserController implements IController
{
	#[Path('/')]
	#[Method('POST')]
	#[RequestBody(entity: UserRequest::class)]
	public function create(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response;
	}
}

class UserRequest
{
	public function __construct() // error: Unused Tests\Fixtures\ApitteWithoutMapping\UserRequest::__construct
	{
	}
}
