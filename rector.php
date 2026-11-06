<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\{
    RemoveEmptyClassMethodRector,
    RemoveUnusedPrivateMethodRector,
    RemoveUnusedPromotedPropertyRector,
};
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\ValueObject\PhpVersion;

// Test fixtures intentionally contain unused private members and empty
// methods: ConfigChecker, PropertyPathReader and constraints are checked on them,
// and the empty methods are needed by the interfaces of older Symfony versions
//
// Фикстуры тестов намеренно содержат неиспользуемые приватные члены и пустые
// методы: на них проверяются ConfigChecker, PropertyPathReader и констрейнты,
// а пустые методы нужны интерфейсам старших версий Symfony
$fixtures = [
    __DIR__ . "/tests/Fixtures",
    __DIR__ . "/tests/Unit/Constraint/UnchangedOnUpdateTest.php",
];

return RectorConfig::configure()
    ->withPaths([__DIR__ . "/src", __DIR__ . "/tests"])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
    )
    ->withSkip([
        RemoveUnusedPrivateMethodRector::class => $fixtures,
        RemoveUnusedPrivatePropertyRector::class => $fixtures,
        RemoveUnusedPromotedPropertyRector::class => $fixtures,
        RemoveEmptyClassMethodRector::class => $fixtures,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withPhpVersion(PhpVersion::PHP_83);
