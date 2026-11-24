<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_save';

    public function __construct(Context $context, private readonly ImageUploader $imageUploader)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $result = $this->imageUploader->saveFileToTmpDir('banner');
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }
}
