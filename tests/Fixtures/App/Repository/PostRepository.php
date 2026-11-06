<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Repository;

use UniMethod\Frame\Persistence\EntityCollectionInterface;

/**
 * An application repository. The class is loaded only by the autoloader — it is used
 * to check that the tag is set without loading the class in advance.
 *
 * Репозиторий приложения. Класс подгружается только автозагрузчиком — по нему
 * проверяется, что тег ставится без предварительной загрузки класса.
 */
final class PostRepository implements EntityCollectionInterface
{
    public function list(
        object|null $filter = null,
        object|null $sort = null,
        object|null $pagination = null,
        array $included = [],
    ): array {
        return [];
    }

    public function countForList(object|null $filter = null): int
    {
        return 0;
    }
}
