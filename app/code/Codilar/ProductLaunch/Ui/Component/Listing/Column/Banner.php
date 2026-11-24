<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Ui\Component\Listing\Column;

use Codilar\ProductLaunch\Model\BannerManager;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Banner extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly BannerManager $bannerManager,
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
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $url = $this->bannerManager->getUrl($item[$name] ?? null);
            $item[$name . '_src'] = $url;
            $item[$name . '_orig_src'] = $url;
            $item[$name . '_alt'] = $item['title'] ?? '';
        }
        return $dataSource;
    }
}
