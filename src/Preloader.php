<?php declare(strict_types = 1);

namespace WebChemistry\Preloader;

use Composer\Autoload\ClassLoader;
use LogicException;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;

final class Preloader
{

	/** @var string[] */
	private array $classMap;

	/** @var mixed[] */
	private array $map;

	public function __construct(
		ClassLoader $classLoader,
		private string $jsonFile,
	)
	{
		$this->classMap = $classLoader->getClassMap();
		$map = Json::decode(FileSystem::read($this->jsonFile), Json::FORCE_ARRAY);

		if (!is_array($map)) {
			$this->invalidJson();
		}

		$this->map = $map;
	}

	public function preload(bool $onlyInclude = false): float
	{
		$timer = microtime(true);

		$this->loadClasses();
		$this->loadFiles();

		if (!$onlyInclude) {
			$this->loadCompiles();
		}

		return microtime(true) - $timer;
	}

	/**
	 * @param mixed[] $map
	 */
	private function loadClasses(): void
	{
		$classes = $this->map['classes'] ?? null;

		if (!is_array($classes)) {
			$this->invalidJson();
		}

		foreach ($classes as $class) {
			$file = $this->classMap[$class] ?? null;

			if ($file === null) {
				throw new LogicException(sprintf('Class %s is not in composer.', $class));
			}

			require_once $file;
		}
	}

	/**
	 * @param mixed[] $map
	 */
	private function loadCompiles(): void
	{
		$compile = $this->map['compile'] ?? null;

		if (!is_array($compile)) {
			$this->invalidJson();
		}

		/** @var string $file */
		foreach ($compile as $file) {
			opcache_compile_file($file);
		}
	}

	private function invalidJson(): never
	{
		echo sprintf('Json %s is not in expected format.', $this->jsonFile);

		die(1);
	}

	private function loadFiles(): void
	{
		$files = $this->map['files'] ?? null;

		if (!is_array($files)) {
			$this->invalidJson();
		}

		/** @var string $file */
		foreach ($files as $file) {
			include_once $file;
		}
	}

	public function checkEnvironment(): void
	{
		if (!ini_get('opcache.enable')) {
			echo 'Opcache is not available.';

			die(1);
		}

		if (!function_exists('opcache_compile_file')) {
			echo 'Opcache function opcache_compile_file is not available.';

			die(1);
		}

		if ('cli' === PHP_SAPI && !ini_get('opcache.enable_cli')) {
			echo 'Opcache is not enabled for CLI applications.';

			die(1);
		}
	}

}
