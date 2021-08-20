<?php

namespace App\Feeds\Utils\Contracts;

interface SelectorFactoryContract
{
    public function setIsGroup(string ...$selectors): static;
    public function setMpn(string ...$selectors): static;
    public function setProduct(string ...$selectors): static;
    public function setShortDescription(string ...$selectors): static;
    public function setImages(string ...$selectors): static;
    public function setDescription(string ...$selectors): static;
    public function setCostToUs(string ...$selectors): static;
    public function setBrand(string ...$selectors): static;
    public function setAvail(string ...$selectors): static;

}