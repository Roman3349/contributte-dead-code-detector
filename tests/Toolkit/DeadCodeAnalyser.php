<?php declare(strict_types = 1);

namespace Tests\Toolkit;

use RuntimeException;

final class DeadCodeAnalyser
{

	/**
	 * Runs PHPStan with the given config and returns dead code errors as "line: message", sorted.
	 *
	 * @return list<string>
	 */
	public static function analyse(string $configFile, string $path): array
	{
		$command = sprintf(
			'cd %s && %s %s analyse --no-progress --error-format=json --memory-limit=1G -c %s %s 2>/dev/null',
			escapeshellarg(dirname(__DIR__, 2)),
			escapeshellarg(PHP_BINARY),
			escapeshellarg(__DIR__ . '/../../vendor/bin/phpstan'),
			escapeshellarg($configFile),
			escapeshellarg($path),
		);

		$output = shell_exec($command);

		if (!is_string($output)) {
			throw new RuntimeException(sprintf('PHPStan did not produce any output for %s', $path));
		}

		$result = json_decode($output, true);

		if (!is_array($result) || !isset($result['files']) || !is_array($result['files'])) {
			throw new RuntimeException(sprintf("Unexpected PHPStan output for %s:\n%s", $path, $output));
		}

		$errors = [];

		foreach ($result['files'] as $fileResult) {
			if (!is_array($fileResult) || !isset($fileResult['messages']) || !is_array($fileResult['messages'])) {
				continue;
			}

			foreach ($fileResult['messages'] as $message) {
				if (!is_array($message) || !is_string($message['identifier'] ?? null) || !str_starts_with($message['identifier'], 'shipmonk.dead')) {
					continue;
				}

				$line = $message['line'] ?? 0;
				$text = $message['message'] ?? '';
				assert(is_int($line) && is_string($text));

				$errors[] = $line . ': ' . $text;
			}
		}

		sort($errors);

		return $errors;
	}

	/**
	 * Collects "// error: message" comments from the fixture as "line: message", sorted.
	 *
	 * @return list<string>
	 */
	public static function expected(string $file): array
	{
		$lines = file($file);

		if ($lines === false) {
			throw new RuntimeException(sprintf('Unable to read %s', $file));
		}

		$errors = [];

		foreach ($lines as $index => $line) {
			$parts = explode('// error: ', rtrim($line));
			array_shift($parts);

			foreach ($parts as $part) {
				$errors[] = ($index + 1) . ': ' . trim($part);
			}
		}

		sort($errors);

		return $errors;
	}

}
