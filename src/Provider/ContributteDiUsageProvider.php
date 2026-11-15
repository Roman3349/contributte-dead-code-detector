<?php declare(strict_types = 1);

namespace Contributte\DeadCodeDetector\Provider;

use Composer\InstalledVersions;
use LogicException;
use Nette\Neon\Entity;
use Nette\Neon\Neon;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMemberUsage;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodUsage;
use ShipMonk\PHPStan\DeadCode\Graph\UsageOrigin;
use ShipMonk\PHPStan\DeadCode\Provider\MemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use function file_get_contents;
use function is_array;
use function is_int;
use function is_string;
use function ltrim;
use function sprintf;
use function str_starts_with;

/**
 * @see https://github.com/contributte/di
 */
final class ContributteDiUsageProvider implements MemberUsageProvider
{

	private const RESOURCE_EXTENSION = 'Contributte\DI\Extension\ResourceExtension';

	private readonly bool $enabled;

	/**
	 * Namespaces autoregistered as services by ResourceExtension.
	 *
	 * @var list<array{namespace: string, excludes: list<string>}>
	 */
	private readonly array $resources;

	/**
	 * @param list<string> $containerNeonPaths Paths to NEON files describing the DI container
	 */
	public function __construct(
		?bool $enabled,
		array $containerNeonPaths,
	)
	{
		$this->enabled = $enabled ?? InstalledVersions::isInstalled('contributte/di');
		$this->resources = $this->enabled && $containerNeonPaths !== []
			? self::loadResources($containerNeonPaths)
			: [];
	}

	/**
	 * Extracts namespaces autoregistered by ResourceExtension.
	 * Its config section can be placed in a different file than the extension registration.
	 *
	 * @param list<string> $neonContents
	 * @return list<array{namespace: string, excludes: list<string>}>
	 */
	public static function findResourcesInNeon(array $neonContents): array
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
				if (is_int($extensionName)) {
					continue; // config section name of unnamed extension is generated
				}

				$extensionClass = $extension instanceof Entity ? $extension->value : $extension;

				if (is_string($extensionClass) && ltrim($extensionClass, '\\') === self::RESOURCE_EXTENSION) {
					$extensionNames[] = $extensionName;
				}
			}
		}

		$resources = [];

		foreach ($decodedFiles as $decoded) {
			foreach ($extensionNames as $extensionName) {
				$extensionConfig = $decoded[$extensionName] ?? null;

				if (!is_array($extensionConfig) || !isset($extensionConfig['resources']) || !is_array($extensionConfig['resources'])) {
					continue;
				}

				foreach ($extensionConfig['resources'] as $namespace => $resource) {
					if (!is_string($namespace)) {
						continue;
					}

					$excludes = [];

					if (is_array($resource) && isset($resource['excludes']) && is_array($resource['excludes'])) {
						foreach ($resource['excludes'] as $exclude) {
							if (is_string($exclude)) {
								$excludes[] = $exclude;
							}
						}
					}

					$resources[] = [
						'namespace' => $namespace,
						'excludes' => $excludes,
					];
				}
			}
		}

		return $resources;
	}

	/**
	 * @return list<ClassMemberUsage>
	 */
	public function getUsages(
		Node $node,
		Scope $scope,
	): array
	{
		if (!$this->enabled || $this->resources === [] || !$node instanceof InClassNode) { // @phpstan-ignore phpstanApi.instanceofAssumption
			return [];
		}

		$usage = $this->getResourceConstructorUsage($node->getClassReflection());

		return $usage === null ? [] : [$usage];
	}

	/**
	 * @param list<string> $containerNeonPaths
	 * @return list<array{namespace: string, excludes: list<string>}>
	 */
	private static function loadResources(array $containerNeonPaths): array
	{
		$neonContents = [];

		foreach ($containerNeonPaths as $containerNeonPath) {
			$contents = file_get_contents($containerNeonPath);

			if ($contents === false) {
				throw new LogicException(sprintf('Nette container file %s does not exist or is not readable', $containerNeonPath));
			}

			$neonContents[] = $contents;
		}

		return self::findResourcesInNeon($neonContents);
	}

	/**
	 * ResourceExtension registers every non-abstract class from the resource namespace (except excludes) as a service.
	 * Resource paths are not checked, as they usually contain dynamic parameters like %appDir%.
	 */
	private function getResourceConstructorUsage(ClassReflection $classReflection): ?ClassMethodUsage
	{
		if (!$classReflection->isClass() || $classReflection->isAbstract() || !$classReflection->hasConstructor()) {
			return null;
		}

		$className = $classReflection->getName();

		foreach ($this->resources as $resource) {
			if (!str_starts_with($className, $resource['namespace'])) {
				continue;
			}

			foreach ($resource['excludes'] as $exclude) {
				if (str_starts_with($className, $exclude)) {
					continue 2;
				}
			}

			return new ClassMethodUsage(
				UsageOrigin::createVirtual($this, VirtualUsageData::withNote('Registered as a service by contributte/di ResourceExtension')),
				new ClassMethodRef(
					$classReflection->getConstructor()->getDeclaringClass()->getName(),
					'__construct',
					possibleDescendant: false,
				),
			);
		}

		return null;
	}

}
