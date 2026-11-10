<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(private readonly PageFactory $pageFactory)
    {
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Build Your Own Gift Box'));
        return $page;
    }
}
