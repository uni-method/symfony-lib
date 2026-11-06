<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Functional;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ValidateConfigCommandTest extends KernelTestCase
{
    public function testValidConfigPasses(): void
    {
        $tester = $this->tester("default");

        self::assertSame(Command::SUCCESS, $tester->execute(["api" => "main"]));
        self::assertStringContainsString("OK", $tester->getDisplay());
    }

    public function testUnknownApiIsInvalidInput(): void
    {
        $tester = $this->tester("default");

        self::assertSame(Command::INVALID, $tester->execute(["api" => "nope"]));
    }

    private function tester(string $variant): CommandTester
    {
        $kernel = $this->bootKernel($variant);
        $kernel->getContainer()->set(ManagerRegistry::class, $this->createStub(ManagerRegistry::class));

        return new CommandTester(
            (new Application($kernel))->find("symfony-lib:config:validate"),
        );
    }
}
