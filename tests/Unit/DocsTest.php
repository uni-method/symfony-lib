<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The documentation does not refer to what does not exist: each `UniMethod\…` name is
 * an existing class/interface/trait/enum or namespace, each
 * local link `[…](…)` is an existing file.
 *
 * Документация не ссылается на то, чего нет: каждое имя `UniMethod\…` —
 * существующий класс/интерфейс/трейт/enum или пространство имён, каждая
 * локальная ссылка `[…](…)` — существующий файл.
 */
final class DocsTest extends TestCase
{
    private const string ROOT = __DIR__ . "/../..";

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDocuments(): iterable
    {
        yield "README.md" => ["README.md"];

        $docs = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::ROOT . "/docs", FilesystemIterator::SKIP_DOTS),
        );
        foreach ($docs as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === "md") {
                $document = substr($file->getPathname(), strlen(self::ROOT) + 1);
                yield $document => [$document];
            }
        }
    }

    #[DataProvider("provideDocuments")]
    public function testNamesExist(string $document): void
    {
        // In the JSON examples the backslash is escaped
        //
        // В JSON-примерах обратный слэш экранирован
        $text = str_replace("\\\\", "\\", $this->read($document));
        preg_match_all('/UniMethod(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/', $text, $matches);

        $missing = array_values(array_filter(
            array_unique($matches[0]),
            fn (string $name): bool => !$this->symbolExists($name) && !$this->namespaceExists($name),
        ));

        self::assertSame([], $missing);
    }

    #[DataProvider("provideDocuments")]
    public function testLocalLinksExist(string $document): void
    {
        preg_match_all('/\]\(([^)#\s]+)/', $this->read($document), $matches);

        $missing = array_values(array_filter(
            $matches[1],
            static fn (string $link): bool => preg_match('#^[a-z]+:#', $link) !== 1
                && !file_exists(self::ROOT . "/" . dirname($document) . "/" . $link),
        ));

        self::assertSame([], $missing);
    }

    private function read(string $document): string
    {
        $text = file_get_contents(self::ROOT . "/" . $document);
        self::assertIsString($text);

        return $text;
    }

    private function symbolExists(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name);
    }

    /**
     * A namespace is a directory by the PSR-4 prefixes of the autoloader.
     *
     * Пространство имён — каталог по PSR-4-префиксам автозагрузчика.
     */
    private function namespaceExists(string $name): bool
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
                if (!str_starts_with($name . "\\", $prefix)) {
                    continue;
                }

                $relative = str_replace("\\", "/", substr($name . "\\", strlen($prefix)));
                foreach ($directories as $directory) {
                    if (is_dir($directory . "/" . $relative)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
