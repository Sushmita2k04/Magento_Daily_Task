<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Controller\Account;

use Codilar\LoyaltyWallet\Model\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * "Loyalty & Wallet" page. Guests are sent to the login page and brought back here afterwards.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param Config $config
     * @param Session $session
     * @param RedirectFactory $redirectFactory
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config,
        private readonly Session $session,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        if (!$this->session->isLoggedIn()) {
            $this->session->setBeforeAuthUrl($this->url->getUrl('loyalty/account'));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Loyalty Points & Wallet'));
        return $page;
    }
}
