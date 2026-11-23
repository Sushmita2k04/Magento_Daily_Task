<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Controller\Wallet;

use Codilar\LoyaltyWallet\Model\Wallet\QuoteWalletManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stops using wallet balance on the cart.
 */
class Remove implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messages
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param Validator $formKeyValidator
     * @param QuoteWalletManager $manager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messages,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly Validator $formKeyValidator,
        private readonly QuoteWalletManager $manager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->redirectFactory->create()->setPath('checkout/cart');
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        try {
            $this->manager->remove(
                $this->checkoutSession->getQuote(),
                (int)$this->customerSession->getCustomerId()
            );
            $this->messages->addSuccessMessage(__('Your store wallet will not be used on this order.'));
        } catch (LocalizedException $e) {
            $this->messages->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Wallet remove failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->messages->addErrorMessage(__('We could not update your wallet. Please try again.'));
        }
        return $redirect;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $this->messages->addErrorMessage(__('Invalid form key. Please refresh the page and try again.'));
        return new InvalidRequestException($this->redirectFactory->create()->setPath('checkout/cart'));
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }
}
