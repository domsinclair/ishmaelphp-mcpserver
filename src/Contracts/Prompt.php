<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Contracts;

interface Prompt
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return array<int, array{role: string, content: array{type: string, text: string}}>
     */
    public function getMessages(): array;

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array;
}
