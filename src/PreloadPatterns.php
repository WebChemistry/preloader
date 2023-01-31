<?php declare(strict_types = 1);

namespace WebChemistry\Preloader;

final class PreloadPatterns
{

	public function startsWithNamespace(string ...$namespace): string
	{
		return sprintf('#^%s\\\\#', preg_quote(implode('\\', $namespace), '#'));
	}

}
