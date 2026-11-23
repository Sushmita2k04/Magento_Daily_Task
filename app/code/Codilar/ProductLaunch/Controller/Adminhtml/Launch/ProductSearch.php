<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Magento\Backend\App\Action;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Lazy product lookup for the ui-select element: ?searchKey=&page=&limit=
 */
class ProductSearch extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_save';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $key = trim((string)$this->getRequest()->getParam('searchKey'));
        $page = max(1, (int)$this->getRequest()->getParam('page', 1));
        $limit = min(100, max(1, (int)$this->getRequest()->getParam('limit', 50)));

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name']);
        if ($key !== '') {
            $like = '%' . addcslashes($key, '%_\\') . '%';
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => $like],
                ['attribute' => 'sku', 'like' => $like],
            ]);
        }
        $collection->setOrder('entity_id', 'DESC')->setPageSize($limit)->setCurPage($page);

        $options = [];
        foreach ($collection as $product) {
            $label = sprintf('%s (%s)', (string)$product->getName(), (string)$product->getSku());
            $options[] = [
                'value' => (int)$product->getId(),
                'label' => $label,
                'is_active' => true,
                'path' => $label,
                'optgroup' => false,
                'sku' => (string)$product->getSku(),
            ];
        }
        return $this->jsonFactory->create()->setData(['options' => $options, 'total' => $collection->getSize()]);
    }
}
