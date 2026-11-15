<?php declare(strict_types = 1);

namespace Contributte\DeadCodeDetector\Provider;

use Apitte\Core\Annotation\Controller\Path;
use Apitte\Core\Annotation\Controller\RequestBody;
use Apitte\Core\DI\ApiExtension;
use Apitte\Core\DI\Plugin\CoreMappingPlugin;
use Apitte\Core\Mapping\Request\BasicEntity as RequestBasicEntity;
use Apitte\Core\Mapping\Response\BasicEntity as ResponseBasicEntity;
use Apitte\Core\UI\Controller\IController;
use Composer\InstalledVersions;
use LogicException;
use Nette\Neon\Entity;
use Nette\Neon\Neon;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use ReflectionMethod;
use ReflectionProperty;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMemberUsage;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodUsage;
use ShipMonk\PHPStan\DeadCode\Graph\UsageOrigin;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use function array_merge;
use function file_get_contents;
use function is_array;
use function is_int;
use function is_string;
use function ltrim;
use function preg_match;
use function sprintf;

/**
 * @see https://github.com/contributte/apitte
 */
final class ApitteUsageProvider extends ReflectionBasedMemberUsageProvider
{

	private readonly ReflectionProvider $reflectionProvider;

	private readonly bool $enabled;

	/**
	 * Whether CoreMappingPlugin instantiates #[RequestBody] entities. Assumed enabled when the container config is unknown.
	 */
	private readonly bool $requestMappingEnabled;

	/**
	 * Classes instantiated by CoreMappingPlugin config (type mappers, entity validator).
	 *
	 * @var array<string, true>
	 */
	private readonly array $mappingClasses;

	/**
	 * @param list<string> $containerNeonPaths Paths to NEON files describing the DI container
	 */
	public function __construct(
		ReflectionProvider $reflectionProvider,
		?bool $enabled,
		array $containerNeonPaths,
	)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->enabled = $enabled ?? InstalledVersions::isInstalled('contributte/apitte');

		$mappingConfig = $this->enabled && $containerNeonPaths !== []
			? self::findMappingConfigInNeon(self::readNeonFiles($containerNeonPaths))
			: null;

		$this->requestMappingEnabled = $mappingConfig === null || $mappingConfig['enabled'];

		$mappingClasses = [];

		foreach ($mappingConfig['classes'] ?? [] as $mappingClass) {
			$mappingClasses[$mappingClass] = true;
		}

		$this->mappingClasses = $mappingClasses;
	}

	/**
	 * Detects whether CoreMappingPlugin is registered in ApiExtension and which classes its config instantiates.
	 *
	 * @param list<string> $neonContents
	 * @return array{enabled: bool, classes: list<string>}
	 */
	public static function findMappingConfigInNeon(array $neonContents): array
	{
		$decodedFiles = [];
		$extensionNames = [];

		foreach ($neonContents as $neonContent) {
			$decoded = Neon::decode($neonContent);

			if (!is_array($decoded)) {
				continue;
			}

			$decodedFiles[] = $decoded;

			if (!isset($decoded['extensions']) || !is_array($decoded['extensions'])) {
				continue;
			}

			foreach ($decoded['extensions'] as $extensionName => $extension) {
				if (!is_int($extensionName) && self::resolveClassName($extension) === ApiExtension::class) {
					$extensionNames[] = $extensionName;
				}
			}
		}

		$enabled = false;
		$classes = [];

		foreach ($decodedFiles as $decoded) {
			foreach ($extensionNames as $extensionName) {
				$extensionConfig = $decoded[$extensionName] ?? null;

				if (!is_array($extensionConfig) || !isset($extensionConfig['plugins']) || !is_array($extensionConfig['plugins'])) {
					continue;
				}

				foreach ($extensionConfig['plugins'] as $pluginClass => $pluginConfig) {
					if (!is_string($pluginClass) || ltrim($pluginClass, '\\') !== CoreMappingPlugin::class) {
						continue;
					}

					$enabled = true;

					if (!is_array($pluginConfig)) {
						continue;
					}

					// RequestParameterMapping::getMapper() instantiates configured type mappers via `new $mapper()`
					if (isset($pluginConfig['types']) && is_array($pluginConfig['types'])) {
						foreach ($pluginConfig['types'] as $mapper) {
							$mapperClass = self::resolveClassName($mapper);

							if ($mapperClass !== null) {
								$classes[] = $mapperClass;
							}
						}
					}

					// the validator is registered as a DI service from the plugin config
					if (isset($pluginConfig['request']) && is_array($pluginConfig['request'])) {
						$validatorClass = self::resolveClassName($pluginConfig['request']['validator'] ?? null);

						if ($validatorClass !== null) {
							$classes[] = $validatorClass;
						}
					}
				}
			}
		}

		return [
			'enabled' => $enabled,
			'classes' => $classes,
		];
	}

	/**
	 * @return list<ClassMemberUsage>
	 */
	public function getUsages(
		Node $node,
		Scope $scope,
	): array
	{
		if (!$this->enabled) {
			return [];
		}

		$usages = parent::getUsages($node, $scope);

		if ($node instanceof InClassNode) { // @phpstan-ignore phpstanApi.instanceofAssumption
			if ($this->requestMappingEnabled) {
				$usages = array_merge($usages, $this->getRequestEntityConstructorUsages($node));
			}

			$mappingClassUsage = $this->getMappingClassConstructorUsage($node->getClassReflection());

			if ($mappingClassUsage !== null) {
				$usages[] = $mappingClassUsage;
			}
		}

		return $usages;
	}

	protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
	{
		if ($this->isEndpointMethod($method)) {
			return VirtualUsageData::withNote('Apitte endpoint');
		}

		return null;
	}

	protected function shouldMarkPropertyAsWritten(ReflectionProperty $property): ?VirtualUsageData
	{
		// BasicEntity::factory() fills public properties declared directly in the instantiated (static) class via reflection
		if (
			$property->isPublic()
			&& !$property->isStatic()
			&& !$property->isReadOnly()
			&& !$property->getDeclaringClass()->isAbstract()
			&& $property->getDeclaringClass()->isSubclassOf(RequestBasicEntity::class)
		) {
			return VirtualUsageData::withNote('Apitte request entity property filled from request');
		}

		return null;
	}

	protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
	{
		// BasicEntity::toResponse() reads public properties declared directly in the instantiated (static) class via reflection
		if (
			$property->isPublic()
			&& !$property->isStatic()
			&& !$property->getDeclaringClass()->isAbstract()
			&& $property->getDeclaringClass()->isSubclassOf(ResponseBasicEntity::class)
		) {
			return VirtualUsageData::withNote('Apitte response entity property serialized to response');
		}

		return null;
	}

	/**
	 * @param list<string> $containerNeonPaths
	 * @return list<string>
	 */
	private static function readNeonFiles(array $containerNeonPaths): array
	{
		$contents = [];

		foreach ($containerNeonPaths as $containerNeonPath) {
			$content = file_get_contents($containerNeonPath);

			if ($content === false) {
				throw new LogicException(sprintf('Nette container file %s does not exist or is not readable', $containerNeonPath));
			}

			$contents[] = $content;
		}

		return $contents;
	}

	private static function resolveClassName(mixed $value): ?string
	{
		if ($value instanceof Entity) {
			$value = $value->value;
		}

		if (!is_string($value) || preg_match('~^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$~', $value) !== 1) {
			return null;
		}

		return ltrim($value, '\\');
	}

	private function getMappingClassConstructorUsage(ClassReflection $classReflection): ?ClassMethodUsage
	{
		if (!isset($this->mappingClasses[$classReflection->getName()]) || !$classReflection->hasConstructor()) {
			return null;
		}

		return new ClassMethodUsage(
			UsageOrigin::createVirtual($this, VirtualUsageData::withNote('Apitte mapping class instantiated from CoreMappingPlugin config')),
			new ClassMethodRef(
				$classReflection->getConstructor()->getDeclaringClass()->getName(),
				'__construct',
				possibleDescendant: false,
			),
		);
	}

	/**
	 * Endpoint methods are public methods of IController services with #[Path] (or @Path annotation in older versions).
	 * Apitte's schema validation rejects controller methods without a path, so every dispatched method has it.
	 */
	private function isEndpointMethod(ReflectionMethod $method): bool
	{
		if (!$method->isPublic() || $method->isStatic()) {
			return false;
		}

		if (!$method->getDeclaringClass()->isSubclassOf(IController::class)) {
			return false;
		}

		if ($method->getAttributes(Path::class) !== []) {
			return true;
		}

		$docComment = $method->getDocComment();

		return $docComment !== false
			&& preg_match('~@(?:[\w\\\\]+\\\\)?Path\s*\(~', $docComment) === 1;
	}

	/**
	 * RequestEntityMapping instantiates #[RequestBody(entity: X::class)] via `new $entityClass()`
	 *
	 * @return list<ClassMethodUsage>
	 */
	private function getRequestEntityConstructorUsages(InClassNode $node): array
	{
		$usages = [];
		$nativeClassReflection = $node->getClassReflection()->getNativeReflection();
		$className = $nativeClassReflection->getName();

		foreach ($nativeClassReflection->getMethods() as $method) {
			if ($method->getDeclaringClass()->getName() !== $className) {
				continue; // inherited endpoints are handled when analysing the declaring class
			}

			if (!$this->isEndpointMethod($method)) {
				continue;
			}

			foreach ($method->getAttributes(RequestBody::class) as $attribute) {
				$arguments = $attribute->getArguments();
				$entity = $arguments['entity'] ?? $arguments[1] ?? null;

				if (!is_string($entity)) {
					continue;
				}

				$entity = ltrim($entity, '\\');

				if (!$this->reflectionProvider->hasClass($entity)) {
					continue;
				}

				$entityReflection = $this->reflectionProvider->getClass($entity);

				if (!$entityReflection->hasConstructor()) {
					continue;
				}

				$usages[] = new ClassMethodUsage(
					UsageOrigin::createVirtual($this, VirtualUsageData::withNote('Apitte request entity instantiated from #[RequestBody]')),
					new ClassMethodRef(
						$entityReflection->getConstructor()->getDeclaringClass()->getName(),
						'__construct',
						possibleDescendant: false,
					),
				);
			}
		}

		return $usages;
	}

}
