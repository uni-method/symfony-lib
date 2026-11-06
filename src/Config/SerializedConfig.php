<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Config;

use LogicException;
use UniMethod\Frame\Config\Config;

/**
 * The Config loaded and validated when the container is built is stored in the
 * container serialized: at runtime it is restored without parsing the
 * file and without repeated validation. base64 — so that `%` in the data is not taken
 * by the container for parameters.
 *
 * Config, загруженный и провалидированный при сборке контейнера, хранится в
 * контейнере сериализованным: в рантайме он восстанавливается без разбора
 * файла и повторной валидации. base64 — чтобы `%` в данных не принимались
 * контейнером за параметры.
 */
final class SerializedConfig
{
    public static function dump(Config $config): string
    {
        return base64_encode(serialize($config));
    }

    public static function restore(string $data): Config
    {
        $decoded = base64_decode($data, true);
        $config = $decoded === false ? false : unserialize($decoded);

        if (!$config instanceof Config) {
            throw new LogicException("Serialized API config in the container is corrupted; clear the cache.");
        }

        return $config;
    }
}
