<?php declare(strict_types = 1);

namespace Tests\Fixtures\Apitte;

use Apitte\Core\Annotation\Controller\Method;
use Apitte\Core\Annotation\Controller\Path;
use Apitte\Core\Annotation\Controller\RequestBody;
use Apitte\Core\Http\ApiRequest;
use Apitte\Core\Http\ApiResponse;
use Apitte\Core\Mapping\Parameter\ITypeMapper;
use Apitte\Core\Mapping\Request\BasicEntity;
use Apitte\Core\Mapping\Response\BasicEntity as ResponseBasicEntity;
use Apitte\Core\Mapping\Validator\IEntityValidator;
use Apitte\Core\UI\Controller\IController;

#[Path('/api')]
abstract class BaseController implements IController
{
	#[Path('/ping')]
	#[Method('GET')]
	public function ping(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response;
	}

	public function helperNotUsed(): void // error: Unused Tests\Fixtures\Apitte\BaseController::helperNotUsed
	{
	}
}

#[Path('/users')]
class UserController extends BaseController
{
	#[Path('/')]
	#[Method('GET')]
	public function list(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response;
	}

	#[Path('/')]
	#[Method('POST')]
	#[RequestBody(entity: UserRequest::class)]
	public function create(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response;
	}

	#[Path('/{id}')]
	#[Method('PUT')]
	#[RequestBody('Update user', PlainRequest::class)]
	public function update(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response;
	}

	/**
	 * @Path("/legacy")
	 * @Method("GET")
	 */
	public function legacy(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response;
	}

	#[Method('GET')]
	public function withoutPath(): void // error: Unused Tests\Fixtures\Apitte\UserController::withoutPath
	{
	}

	#[Path('/private')]
	private function privateMethod(): void // error: Unused Tests\Fixtures\Apitte\UserController::privateMethod
	{
	}
}

class NotController
{
	#[Path('/foo')]
	public function foo(): void // error: Unused Tests\Fixtures\Apitte\NotController::foo
	{
	}
}

/**
 * @extends BasicEntity<string, mixed>
 */
class UserRequest extends BasicEntity
{
	public string $name; // error: Property Tests\Fixtures\Apitte\UserRequest::$name is never read

	public static int $staticProperty = 0; // error: Property Tests\Fixtures\Apitte\UserRequest::$staticProperty is never read

	public readonly int $readonlyProperty; // error: Property Tests\Fixtures\Apitte\UserRequest::$readonlyProperty is never read // error: Property Tests\Fixtures\Apitte\UserRequest::$readonlyProperty is never written

	private int $privateProperty; // error: Property Tests\Fixtures\Apitte\UserRequest::$privateProperty is never read // error: Property Tests\Fixtures\Apitte\UserRequest::$privateProperty is never written

	public function __construct()
	{
	}
}

class PlainRequest
{
	public function __construct()
	{
	}
}

/**
 * @extends ResponseBasicEntity<string, mixed>
 */
class UserResponse extends ResponseBasicEntity
{
	public string $name = '';

	public static int $staticProperty = 0; // error: Property Tests\Fixtures\Apitte\UserResponse::$staticProperty is never read

	private int $privateProperty = 0; // error: Property Tests\Fixtures\Apitte\UserResponse::$privateProperty is never read
}

/**
 * @extends ResponseBasicEntity<string, mixed>
 */
abstract class AbstractResponse extends ResponseBasicEntity
{
	public string $inherited = ''; // error: Property Tests\Fixtures\Apitte\AbstractResponse::$inherited is never read
}

class MoneyTypeMapper implements ITypeMapper
{
	public function __construct()
	{
	}

	public function normalize(mixed $value, array $options = []): mixed
	{
		return $value;
	}
}

class CustomValidator implements IEntityValidator
{
	public function __construct(object $someService)
	{
	}

	public function validate(object $entity): void
	{
	}
}

class UnreferencedEntity
{
	public function __construct() // error: Unused Tests\Fixtures\Apitte\UnreferencedEntity::__construct
	{
	}
}
