<?php

namespace App\Feeds\Utils\Contracts;

/**
 *
 */
interface SelectorUtilContract
{
    public function getSelector(bool $stringify = true): string|array;
    public function setSelectors(string ...$selectors): static;
    public function getType(): string;
    public function setType(string $type): static;
    public function addSelectors(string ...$selectors): static;
}