<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Task;

use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;
use Codilar\Task\Model\TaskMassDeleter;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

/**
 * Deletes the tasks selected in the grid. Needs the "delete" permission, POST only.
 * Partial failures are reported task by task and never abort the rest.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_delete';
    private const MAX_LISTED = 10;

    /**
     * @param Action\Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param TaskMassDeleter $massDeleter
     * @param LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly TaskMassDeleter $massDeleter,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $tasks = $collection->getItems();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setPath('*/*/');
        } catch (\Throwable $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage(__('Could not determine which tasks to delete.'));
            return $redirect->setPath('*/*/');
        }

        if (!$tasks) {
            $this->messageManager->addErrorMessage(__('No tasks were selected.'));
            return $redirect->setPath('*/*/');
        }

        $report = $this->massDeleter->delete($tasks);

        if ($report['deleted']) {
            $this->messageManager->addSuccessMessage(
                __('%1 task(s) deleted: %2', count($report['deleted']), $this->describe($report['deleted']))
            );
        }
        if ($report['failed']) {
            $failed = [];
            foreach ($report['failed'] as $id => $info) {
                $failed[$id] = sprintf('%s (%s)', $info['title'], $info['reason']);
            }
            $this->messageManager->addErrorMessage(
                __('%1 task(s) could not be deleted: %2', count($failed), $this->describe($failed))
            );
        }
        return $redirect->setPath('*/*/');
    }

    /**
     * Short "#id title" list, capped so the message stays readable.
     *
     * @param array $items id => label
     * @return string
     */
    private function describe(array $items): string
    {
        $parts = [];
        foreach (array_slice($items, 0, self::MAX_LISTED, true) as $id => $label) {
            $parts[] = sprintf('#%d %s', $id, $label);
        }
        $more = count($items) - count($parts);
        return implode(', ', $parts) . ($more > 0 ? (string) __(' and %1 more', $more) : '');
    }
}
