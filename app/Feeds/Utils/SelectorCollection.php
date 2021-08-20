<?php

namespace App\Feeds\Utils;

use App\Feeds\Utils\Contracts\SelectorUtilContract;

/**
 *
 */
class SelectorCollection extends \Illuminate\Support\Collection
{
    /**
     * @var SelectorUtilContract[]
     */
    private array $storage = [];
    /**
     * @var array
     */
    private array $keys = [];

    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * @param string $type
     * @param string ...$selectors
     * @return $this
     */
    public function addSelectors(string $type, string ...$selectors): static
    {
        $this->storage[ $type ] =
            in_array($type, $this->keys)
                ? $this->storage[ $type ]->addSelectors(...$selectors) : $this->setKey($type)
                                                                              ->createSelector($type, ...$selectors);

        return $this;
    }

    /**
     * @param string $type
     * @param bool $transform
     * @return array|string
     */
    public function getSelector(string $type, bool $transform = true): array|string
    {
        return $this->storage[$type]?->getSelector($transform);
    }


        /**
     * @param string $key
     * @return $this
     */
    private function setKey(string $key): static
    {
        $this->keys[] = $key;

        return $this;
    }

    /**
     * @param string $type
     * @param string ...$selectors
     * @return SelectorUtilContract
     */
    private function createSelector(string $type, string ...$selectors): SelectorUtilContract
    {
        return new Selector($type, ...$selectors);
    }
}