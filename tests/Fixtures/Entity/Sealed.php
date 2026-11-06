<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

/**
 * Fields inaccessible from outside: a private getter, a readonly property, a private
 * property without accessors, a relation without a setter and with a private add.
 *
 * Поля, недоступные снаружи: приватный геттер, readonly-свойство, приватное
 * свойство без аксессоров, связь без сеттера и с приватным add.
 */
final class Sealed
{
    public ?string $id = null;

    public readonly string $code;

    /** @var list<Writer> */
    private array $writers = [];

    private string $hidden = "";

    public function __construct()
    {
        $this->code = "c";
    }

    private function getSecret(): string
    {
        return $this->hidden;
    }

    private function addWriter(Writer $writer): void
    {
        $this->writers[] = $writer;
    }
}
