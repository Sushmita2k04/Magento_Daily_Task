<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Controller\Wallet;

use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Storefront\Money;
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
 * Applies wallet balance to the cart: all that is allowed, or a chosen amount.
 */
class Apply implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messages
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param Validator $formKeyValidator
     * @param QuoteWalletManager $manager
     * @param Money $money
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
        private readonly Money $money,
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
            $mode = (string)$this->request->getParam('wallet_mode');
            $amount = $mode === 'all' ? null : trim((string)$this->request->getParam('wallet_amount'));
            $applied = $this->manager->apply(
                $this->checkoutSession->getQuote(),
                (int)$this->customerSession->getCustomerId(),
                $amount
            );
            if (Decimal::sign($applied) > 0) {
                $this->messages->addSuccessMessage(
                    __('%1 of your store wallet will be used on this order.', $this->money->format($applied))
                );
            } else {
                $this->messages->addNoticeMessage(
                    __('No wallet balance could be applied to this cart right now.')
                );
            }
        } catch (LocalizedException $e) {
            $this->messages->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Wallet apply failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->messages->addErrorMessage(__('We could not apply your wallet. Please try again.'));
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
