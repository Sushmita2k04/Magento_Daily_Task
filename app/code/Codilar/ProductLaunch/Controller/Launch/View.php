<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Launch;

use Codilar\ProductLaunch\Model\Storefront\Provider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Provider $provider
    ) {
    }

    public function execute()
    {
        try {
            $entry = $this->provider->getByUrlKey(strtolower((string)$this->request->getParam('url_key')));
        } catch (NoSuchEntityException) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $page = $this->pageFactory->create();
        $launch = $entry['launch'];
        $page->getConfig()->getTitle()->set((string)$launch->getTitle());
        $description = trim(strip_tags((string)$launch->getDescription()));
        if ($description !== '') {
            $page->getConfig()->setDescription(mb_substr($description, 0, 255));
        }
        $block = $page->getLayout()->getBlock('productlaunch.launch.view');
        if ($block) {
            $block->setData('entry', $entry);
        }
        return $page;
    }
}
