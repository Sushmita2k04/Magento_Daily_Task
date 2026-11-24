<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Controller\Account;

use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Storefront\Money;
use Magento\Customer\Model\Session;
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
 * Converts points to wallet balance (FR-04). The customer is always the logged-in one; nothing in the request can
 * name another customer. The form's request key makes a double submit convert once.
 */
class Convert implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messages
     * @param Session $session
     * @param Validator $formKeyValidator
     * @param PointsManagementInterface $points
     * @param Config $config
     * @param Money $money
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messages,
        private readonly Session $session,
        private readonly Validator $formKeyValidator,
        private readonly PointsManagementInterface $points,
        private readonly Config $config,
        private readonly Money $money,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->session->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $redirect = $this->redirectFactory->create()->setPath('loyalty/account');
        if (!$this->config->isEnabled()) {
            return $redirect;
        }
        $rawPoints = (string)$this->request->getParam('points');
        $key = (string)$this->request->getParam('request_key');
        if (!ctype_digit($rawPoints) || strlen($rawPoints) > 9 || (int)$rawPoints < 1) {
            $this->messages->addErrorMessage(__('Enter a whole number of points to convert.'));
            return $redirect;
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            $this->messages->addErrorMessage(__('Your session expired. Please try again.'));
            return $redirect;
        }
        try {
            $customer = $this->session->getCustomerDataObject();
            $result = $this->points->convertToWallet(
                (int)$customer->getId(),
                (string)$customer->getEmail(),
                (int)$rawPoints,
                'c' . (int)$customer->getId() . '-' . $key
            );
            $this->messages->addSuccessMessage(__(
                '%1 points converted to %2. Your wallet balance is now %3.',
                $result->pointsConverted,
                $this->money->format($result->walletAmount),
                $this->money->format($result->walletBalance)
            ));
        } catch (LocalizedException $e) {
            $this->messages->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Loyalty conversion failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->messages->addErrorMessage(__('We could not convert your points. Please try again.'));
        }
        return $redirect;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $this->messages->addErrorMessage(__('Invalid form key. Please refresh the page and try again.'));
        return new InvalidRequestException($this->redirectFactory->create()->setPath('loyalty/account'));
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }
}
