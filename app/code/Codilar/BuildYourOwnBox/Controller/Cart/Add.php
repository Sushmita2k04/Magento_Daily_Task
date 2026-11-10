<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Controller\Cart;

use Codilar\BuildYourOwnBox\Model\Config;
use Codilar\BuildYourOwnBox\Model\Product\Type\Box;
use Codilar\BuildYourOwnBox\Model\QuoteGuard;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Adds the assembled box to the cart as ONE quote item. The request only carries product ids and the
 * gift message; prices, discount and categories are always recomputed server-side.
 */
class Add implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly Config $config,
        private readonly Cart $cart,
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteGuard $quoteGuard,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LockManagerInterface $lockManager,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $url,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => (string)__('Your session has expired. Please refresh the page and try again.'),
            ]);
        }

        $lockName = 'byob_add_' . hash('sha256', (string)$this->checkoutSession->getSessionId());
        if (!$this->lockManager->lock($lockName, 0)) {
            return $result->setHttpResponseCode(409)->setData([
                'success' => false,
                'message' => (string)__('Your box is already being added. Please wait a moment.'),
            ]);
        }

        try {
            if ($error = $this->config->getConfigurationError()) {
                throw new LocalizedException(__($error));
            }
            if ($this->quoteGuard->getBoxItems($this->checkoutSession->getQuote())) {
                throw new LocalizedException(
                    __('You already have a gift box in your cart. Remove it before building another one.')
                );
            }

            $product = $this->getBoxProduct();
            $this->cart->addProduct($product, [
                'qty' => 1,
                'byob_selection' => [
                    'coffee' => $this->request->getParam('coffee'),
                    'equipment' => $this->request->getParam('equipment'),
                    'message' => $this->request->getParam('message', ''),
                ],
            ]);
            $this->cart->save();
            $this->checkoutSession->setCartWasUpdated(true);

            $message = (string)__('Your gift box was added to your cart.');
            $this->messageManager->addSuccessMessage($message);
            $data = ['success' => true, 'message' => $message, 'redirect' => $this->url->getUrl('checkout/cart')];
        } catch (LocalizedException $e) {
            $data = ['success' => false, 'message' => $e->getMessage()];
            $result->setHttpResponseCode(400);
        } catch (\Throwable $e) {
            $this->logger->error('BuildYourOwnBox add to cart failed: ' . $e->getMessage(), ['exception' => $e]);
            $data = ['success' => false, 'message' => (string)__('We could not add your box. Please try again.')];
            $result->setHttpResponseCode(500);
        } finally {
            $this->lockManager->unlock($lockName);
        }

        return $result->setData($data);
    }

    /**
     * @throws LocalizedException
     */
    private function getBoxProduct(): \Magento\Catalog\Api\Data\ProductInterface
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        try {
            $configured = $this->config->getBoxProductId();
            $product = $configured
                ? $this->productRepository->getById($configured, false, $storeId)
                : $this->productRepository->get(Config::BOX_SKU, false, $storeId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            throw new LocalizedException(__('Build Your Own Box is not configured correctly.'));
        }
        if ($product->getTypeId() !== Box::TYPE_CODE) {
            throw new LocalizedException(__('Build Your Own Box is not configured correctly.'));
        }
        return $product;
    }
}
