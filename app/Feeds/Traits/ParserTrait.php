<?php

namespace App\Feeds\Traits;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\AbstractProcessor;
use App\Feeds\Utils\Data;
use DateTime;

trait ParserTrait
{
    protected AbstractProcessor $vendor;
    protected ?array $data = null;

    public function __construct( $vendor )
    {
        $this->vendor = $vendor;
    }

    public function beforeParse(): void
    {
    }

    public function afterParse( FeedItem $fi ): void
    {
    }

    public function __call( $name, $parameters )
    {
    }

    /**
     * @return AbstractProcessor
     */
    public function getVendor(): AbstractProcessor
    {
        return $this->vendor;
    }

    /**
     * @param Data $data
     * @param array $params
     * @return FeedItem[]
     */
    public function parseContent( Data $data, array $params = [] ): array
    {
        return [];
    }

    public function getProduct(): string
    {
        return '';
    }

    public function getBrand(): ?string
    {
        return null;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        return [];
    }

    public function getMpn(): string
    {
        return '';
    }

    public function getListPrice(): ?float
    {
        return null;
    }

    public function getCostToUs(): float
    {
        return 0;
    }

    public function getUpc(): ?string
    {
        return null;
    }

    public function getImages(): array
    {
        return [];
    }

    public function getMinAmount(): ?int
    {
        return 1;
    }

    public function getMultOrderQuantity(): ?string
    {
        return 'N';
    }

    public function getCategories(): array
    {
        return [];
    }

    public function getAvail(): ?int
    {
        return null;
    }

    public function isGroup(): bool
    {
        return false;
    }

    public function getProductCode(): string
    {
        return $this->getMpn() ? $this->vendor->getPrefix() . $this->getMpn() : '';
    }

    public function getInternalId(): string
    {
        return method_exists($this, 'getUri') ? $this->getUri() : '';
    }

    public function getDescription(): string
    {
        return $this->getProduct();
    }

    public function getMinimumPrice(): ?float
    {
        return null;
    }

    public function getBrandNormalized(): bool
    {
        return false;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return ParserTrait
     */
    public function setData( $data )
    {
        $this->data = $data;
        return $this;
    }

    public function getForsale(): string
    {
        return 'Y';
    }

    public function getWeight(): ?float
    {
        return null;
    }

    public function getDimX(): ?float
    {
        return null;
    }

    public function getDimY(): ?float
    {
        return null;
    }

    public function getDimZ(): ?float
    {
        return null;
    }

    public function getShippingWeight(): ?float
    {
        return null;
    }

    public function getShippingDimX(): ?float
    {
        return null;
    }

    public function getShippingDimY(): ?float
    {
        return null;
    }

    public function getShippingDimZ(): ?float
    {
        return null;
    }

    public function getAttributes(): ?array
    {
        return null;
    }

    public function getEtaDate(): ?DateTime
    {
        return null;
    }

    public function getShortDescription(): array
    {
        return [];
    }

    public function getASIN(): ?string
    {
        return null;
    }

    public function getLeadTimeMessage(): ?string
    {
        return null;
    }

    public function getProductFiles(): array
    {
        return [];
    }

    public function getOptions(): array
    {
        return [];
    }

    public function getVideos(): array
    {
        return [];
    }
}
