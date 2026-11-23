<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Api\Data\LaunchInterfaceFactory;
use Codilar\ProductLaunch\Api\LaunchRepositoryInterface;
use Codilar\ProductLaunch\Model\BannerManager;
use Codilar\ProductLaunch\Model\DateTimeConverter;
use Codilar\ProductLaunch\Model\LaunchValidator;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_save';

    public function __construct(
        Action\Context $context,
        private readonly LaunchRepositoryInterface $repository,
        private readonly LaunchInterfaceFactory $launchFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly BannerManager $bannerManager,
        private readonly DateTimeConverter $dateTimeConverter,
        private readonly LaunchValidator $validator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $data = (array)$this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($data['launch_id'] ?? 0);

        try {
            $launch = $id ? $this->repository->getById($id) : $this->launchFactory->create();
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This launch no longer exists.'));
            return $redirect->setPath('*/*/');
        }

        try {
            $launch->setTitle((string)($data['title'] ?? ''));
            $launch->setProductId($this->extractProductId($data['product_id'] ?? null));
            $launch->setDescription(isset($data['description']) ? (string)$data['description'] : null);
            $launch->setIsActive(!empty($data['is_active']));
            $urlKey = trim((string)($data['url_key'] ?? ''));
            if ($urlKey !== '') {
                $launch->setUrlKey(strtolower($urlKey));
            }
            $launch->setStoreIds($this->extractStoreIds($data['store_ids'] ?? [0]));

            // Admin enters dates in the store timezone; persist UTC. Malformed => friendly error.
            $startRaw = trim((string)($data['start_at'] ?? ''));
            $endRaw = trim((string)($data['end_at'] ?? ''));
            $launch->setStartAt($this->dateTimeConverter->toUtc($startRaw) ?? '');
            $launch->setEndAt($this->dateTimeConverter->toUtc($endRaw));

            $banner = $launch->getBanner();
            $uploaded = $data['banner'][0] ?? null;
            if (is_array($uploaded) && !empty($uploaded['name'])) {
                if (!empty($uploaded['tmp_name'])) {
                    // freshly uploaded file: validate the rest first so no orphan file is moved on failure
                    $this->validator->assertValid($launch);
                    $banner = $this->bannerManager->moveFromTmp((string)$uploaded['name']);
                }
            } else {
                $banner = null; // image removed in the form
            }
            $launch->setBanner($banner);

            $this->repository->save($launch);
            $this->messageManager->addSuccessMessage(__('The launch "%1" has been saved.', $launch->getTitle()));
            $this->dataPersistor->clear('codilar_productlaunch');
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['launch_id' => $launch->getLaunchId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the launch.'));
        }
        $this->dataPersistor->set('codilar_productlaunch', $data);
        return $redirect->setPath('*/*/edit', $id ? ['launch_id' => $id] : []);
    }

    private function extractProductId(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        return (is_numeric($value) && (int)$value > 0) ? (int)$value : null;
    }

    /**
     * @return int[]
     */
    private function extractStoreIds(mixed $value): array
    {
        $value = is_array($value) ? $value : [$value];
        $ids = array_values(array_unique(array_map('intval', array_filter($value, 'is_numeric'))));
        return $ids ?: [0];
    }
}
