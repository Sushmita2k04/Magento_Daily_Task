<?php
declare(strict_types=1);

namespace Codilar\Task\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit / Delete links per grid row, limited to what the admin user is allowed to do.
 */
class TaskActions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $canView = $this->authorization->isAllowed('Codilar_Task::task_view');
        $canDelete = $this->authorization->isAllowed('Codilar_Task::task_delete');
        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $id = $item['task_id'] ?? null;
            if (!$id) {
                continue;
            }
            $item[$name] = [];
            if ($canView) {
                $item[$name]['edit'] = [
                    'href' => $this->urlBuilder->getUrl('codilar_task/task/edit', ['task_id' => $id]),
                    'label' => __('Edit'),
                ];
            }
            if ($canDelete) {
                $item[$name]['delete'] = [
                    'href' => $this->urlBuilder->getUrl('codilar_task/task/delete', ['task_id' => $id]),
                    'label' => __('Delete'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Delete task'),
                        'message' => __('Are you sure you want to delete this task? This cannot be undone.'),
                    ],
                ];
            }
        }
        return $dataSource;
    }
}
