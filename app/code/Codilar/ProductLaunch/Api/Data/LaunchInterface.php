<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Api\Data;

interface LaunchInterface
{
    public const LAUNCH_ID = 'launch_id';
    public const TITLE = 'title';
    public const URL_KEY = 'url_key';
    public const PRODUCT_ID = 'product_id';
    public const SKU = 'sku';
    public const DESCRIPTION = 'description';
    public const BANNER = 'banner';
    public const START_AT = 'start_at';
    public const END_AT = 'end_at';
    public const IS_ACTIVE = 'is_active';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const STORE_IDS = 'store_ids';

    /** @return int|null */
    public function getLaunchId();

    /** @param int|string|null $launchId */
    public function setLaunchId($launchId);

    public function getTitle(): ?string;

    public function setTitle(string $title): self;

    public function getUrlKey(): ?string;

    public function setUrlKey(string $urlKey): self;

    public function getProductId(): ?int;

    public function setProductId(?int $productId): self;

    public function getSku(): ?string;

    public function setSku(?string $sku): self;

    public function getDescription(): ?string;

    public function setDescription(?string $description): self;

    public function getBanner(): ?string;

    public function setBanner(?string $banner): self;

    /** Start date-time in UTC (Y-m-d H:i:s) */
    public function getStartAt(): ?string;

    public function setStartAt(string $startAt): self;

    /** End date-time in UTC (Y-m-d H:i:s) or null */
    public function getEndAt(): ?string;

    public function setEndAt(?string $endAt): self;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): self;

    public function getCreatedAt(): ?string;

    public function getUpdatedAt(): ?string;

    /** @return int[] store ids, [0] means all store views */
    public function getStoreIds(): array;

    /** @param int[] $storeIds */
    public function setStoreIds(array $storeIds): self;
}
