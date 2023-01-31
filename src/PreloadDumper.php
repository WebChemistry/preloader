<?php declare(strict_types = 1);

namespace WebChemistry\Preloader;

use Composer\Autoload\ClassLoader;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Stringable;

final class PreloadDumper
{

	public PreloadPatterns $patterns;

	/** @var array<class-string, string> */
	private array $classMap;

	/** @var array<string, string> */
	private array $noMatch = [];

	/** @var array<class-string, class-string> */
	private array $classes = [];

	public function __construct(ClassLoader $loader)
	{
		/** @var array<class-string, string> $classMap */
		$classMap = $loader->getClassMap();

		$this->classMap = $classMap;
		$this->patterns = new PreloadPatterns();
	}

	public function addLegacyFile(string $file): void
	{
		$contents = FileSystem::read($file);

		// function return types
		if (preg_match_all('#public function \w+\([^)]*\): ([a-zA-Z0-9\\\\_]+)#', $contents, $matches)) {
			foreach ($matches[1] as $class) {
				$this->preloadClass($class);
			}
		}

		// new ...
		if (preg_match_all('#new ([a-zA-Z0-9\\\\_]+)#', $contents, $matches)) {
			foreach ($matches[1] as $class) {
				$this->preloadClass($class);
			}
		}

		// implements ...
		if (preg_match_all('#implements ([a-zA-Z0-9\\\\_]+)#', $contents, $matches)) {
			foreach ($matches[1] as $class) {
				$this->preloadClass($class);
			}
		}
	}

	/**
	 * @param iterable<string|Stringable> $files
	 */
	public function addFiles(iterable $files): self
	{
		foreach ($files as $file) {
			$this->addFile((string) $file);
		}

		return $this;
	}

	/**
	 * @param iterable<string|Stringable> $files
	 */
	public function addLegacyFiles(iterable $files): self
	{
		foreach ($files as $file) {
			$this->addLegacyFile((string) $file);
		}

		return $this;
	}

	public function addFile(string|Stringable $file): self
	{
		if (preg_match_all('#^use ([a-zA-Z\\\\0-9_]+)(?:\s+as\s+[a-zA-Z\\\\0-9_]+)?;#m', FileSystem::read((string) $file), $matches)) {
			/** @var class-string $class */
			foreach ($matches[1] as $class) {
				$this->preloadClass($class);
			}
		}

		return $this;
	}

	/**
	 * @param class-string $class
	 */
	public function preloadClass(string $class): void
	{
		if (isset($this->classes[$class])) {
			return;
		}

		foreach ($this->noMatch as $pattern) {
			if (preg_match($pattern, $class)) {
				return;
			}
		}

		$file = $this->classMap[$class] ?? null;

		if ($file !== null) {
			$this->classes[$class] = $class;
		}
	}

	public function exclude(string $pattern): void
	{
		$this->noMatch[$pattern] = $pattern;
	}

	public function preload(string $pattern): void
	{
		foreach (array_keys($this->classMap) as $class) {
			if (preg_match($pattern, $class)) {
				$this->preloadClass($class);
			}
		}
	}

	/**
	 * @return class-string[]
	 */
	public function getClasses(): array
	{
		return array_values($this->classes);
	}

	public function dumpToFile(string $location): void
	{
		FileSystem::write($location, Json::encode([
			'classes' => $this->getClasses(),
		], Json::PRETTY));
	}

}
