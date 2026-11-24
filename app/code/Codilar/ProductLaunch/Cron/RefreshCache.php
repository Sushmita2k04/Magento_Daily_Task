<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Cron;

use Codilar\ProductLaunch\Model\Launch;
use Codilar\ProductLaunch\Model\ResourceModel\Launch\CollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Cache\CacheConstants;
use Magento\PageCache\Model\Cache\Type as PageCache;

/**
 * A launch changes status purely with time (no save happens), so full-page cache entries must be
 * purged when a start/end boundary is crossed. Runs every minute and purges launches whose
 * boundary passed in the last 5 minutes (idempotent).
 */
class RefreshCache
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly PageCache $pageCache,
        private readonly CacheInterface $cache
    ) {
    }

    public function execute(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $from = $now->modify('-5 minutes')->format('Y-m-d H:i:s');
        $to = $now->format('Y-m-d H:i:s');

        $collection = $this->collectionFactory->create();
        $connection = $collection->getConnection();
        $collection->getSelect()->where(
            $connection->quoteInto('main_table.start_at > ?', $from) . ' AND ' . $connection->quoteInto('main_table.start_at <= ?', $to)
            . ' OR ' . $connection->quoteInto('main_table.end_at > ?', $from) . ' AND ' . $connection->quoteInto('main_table.end_at <= ?', $to)
        );
        $tags = [];
        foreach ($collection as $launch) {
            $tags[] = Launch::CACHE_TAG . '_' . $launch->getId();
        }
        if ($tags) {
            $tags[] = Launch::CACHE_TAG_LIST;
            $this->pageCache->clean(CacheConstants::CLEANING_MODE_MATCHING_ANY_TAG, $tags);
            $this->cache->clean($tags);
        }
    }
}
