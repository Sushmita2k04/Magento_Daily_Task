<?php
declare(strict_types=1);

namespace Codilar\Task\Ui\DataProvider;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Controller\Adminhtml\Task\Save;
use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Feeds the task form: the stored task, or what the admin typed if the last save failed.
 */
class TaskFormDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $result = [];
        foreach ($this->collection->getItems() as $task) {
            $result[$task->getId()] = $task->getData();
        }

        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (!empty($persisted)) {
            $id = $persisted[TaskInterface::TASK_ID] ?? null;
            $result[$id] = array_merge($result[$id] ?? [], $persisted);
            $this->dataPersistor->clear(Save::PERSISTOR_KEY);
        }
        return $result;
    }
}
