<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\AbstractProcessor;
use App\Feeds\Utils\Data;
use DateTime;

/**
 * @method beforeParse
 * @method afterParse( FeedItem $fi )
 */
interface ParserInterface
{
    public const DEFAULT_AVAIL_NUMBER = 10000;
    public const DEFAULT_PRODUCT_NAME = 'Dummy';

    /**
     * @param Data $data
     * @param array $params
     * @return FeedItem[]
     */
    public function parseContent( Data $data, array $params = [] ): array;

    public function getVendor(): AbstractProcessor;

    public function getProduct(): string;

    public function getBrand(): ?string;

    public function getChildProducts( FeedItem $item ): array;

    public function getMpn(): string;

    public function getListPrice(): ?float;

    public function getCostToUs(): float;

    public function getUpc(): ?string;

    public function getImages(): array;

    public function getMinAmount(): ?int;

    public function getMultOrderQuantity(): ?string;

    public function getCategories(): array;

    public function getAvail(): ?int;

    public function isGroup(): bool;

    public function getProductCode(): string;

    public function getInternalId(): string;

    public function getDescription(): string;

    public function getMinimumPrice(): ?float;

    public function getBrandNormalized(): bool;

    public function setData( $data );

    public function getForsale(): string;

    public function getWeight(): ?float;

    public function getDimX(): ?float;

    public function getDimY(): ?float;

    public function getDimZ(): ?float;

    public function getShippingWeight(): ?float;

    public function getShippingDimX(): ?float;

    public function getShippingDimY(): ?float;

    public function getShippingDimZ(): ?float;

    public function getAttributes(): ?array;

    public function getEtaDate(): ?DateTime;

    public function getShortDescription(): array;

    public function getASIN(): ?string;

    public function getLeadTimeMessage(): ?string;

    public function getProductFiles(): array;

    public function getVideos(): array;
}
