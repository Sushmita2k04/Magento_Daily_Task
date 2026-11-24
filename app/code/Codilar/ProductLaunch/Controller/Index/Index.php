<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Index;

use Codilar\ProductLaunch\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set($this->config->getPageTitle());
        return $page;
    }
}
