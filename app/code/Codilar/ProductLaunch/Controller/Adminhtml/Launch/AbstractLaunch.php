<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Magento\Backend\App\Action;

abstract class AbstractLaunch extends Action
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_view';
}
