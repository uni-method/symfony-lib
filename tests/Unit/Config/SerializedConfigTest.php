<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Config;

use LogicException;
use PHPUnit\Framework\TestCase;
use UniMethod\SymfonyLib\Config\{SerializedConfig, YamlConfigLoader};
use UniMethod\SymfonyLib\Tests\Fixtures\App\TestKernel;

final class SerializedConfigTest extends TestCase
{
    public function testRestoresEqualConfig(): void
    {
        $config = (new YamlConfigLoader())->load(TestKernel::MAIN_CONFIG);

        self::assertEquals($config, SerializedConfig::restore(SerializedConfig::dump($config)));
    }

    public function testCorruptedDataIsRejected(): void
    {
        $this->expectException(LogicException::class);

        SerializedConfig::restore(base64_encode(serialize(new \stdClass())));
    }
}
