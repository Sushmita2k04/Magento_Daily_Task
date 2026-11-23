<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
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

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)($item['launch_id'] ?? 0);
            if (!$id) {
                continue;
            }
            $title = (string)($item['title'] ?? '');
            $actions = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl('productlaunch/launch/view', ['launch_id' => $id]),
                    'label' => __('View'),
                ],
            ];
            if ($this->authorization->isAllowed('Codilar_ProductLaunch::launch_save')) {
                $actions['edit'] = [
                    'href' => $this->urlBuilder->getUrl('productlaunch/launch/edit', ['launch_id' => $id]),
                    'label' => __('Edit'),
                ];
            }
            if ($this->authorization->isAllowed('Codilar_ProductLaunch::launch_delete')) {
                $actions['delete'] = [
                    'href' => $this->urlBuilder->getUrl('productlaunch/launch/delete', ['launch_id' => $id]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete launch'),
                        'message' => __('Are you sure you want to delete the launch "%1"?', $title),
                    ],
                    'post' => true,
                ];
            }
            $item[$this->getData('name')] = $actions;
        }
        return $dataSource;
    }
}
