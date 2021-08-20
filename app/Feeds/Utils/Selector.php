<?php

namespace App\Feeds\Utils;

use App\Feeds\Utils\Contracts\SelectorUtilContract;
use Illuminate\Contracts\Support\Arrayable;

/**
 *
 */
class Selector implements SelectorUtilContract, Arrayable
{
    /**
     * @var array|string[]
     */
    protected array $selectors;

    /**
     * @param string $type
     * @param string ...$selector
     */
    public function __construct(protected string $type, string ...$selector)
    {
        $this->selectors = $selector;
    }

    /**
     * @param bool $stringify
     * @return string|array
     */
    public function getSelector(bool $stringify = true): string|array
    {
        return $stringify ? implode(', ', $this->selectors) : $this->selectors;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        
        return $this;
    }

    /**
     * @param string ...$selectors
     * @return $this
     */
    public function setSelectors(string ...$selectors): static
    {
        $this->selectors = $selectors;

        return $this;
    }

    public function addSelectors(string ...$selectors): static
    {
        $this->selectors = [...$this->selectors, ...$selectors];

        return $this;
    }

    public function toArray()
    {
//        return $this->
    }
}