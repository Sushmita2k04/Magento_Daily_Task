<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\UrlInterface;

/**
 * Maps /launches/<url-key> to launches/launch/view.
 */
class Router implements RouterInterface
{
    public function __construct(private readonly ActionFactory $actionFactory)
    {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if ($request->getAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS)) {
            return null; // already routed once: let a later noroute forward reach the default router
        }
        $path = trim((string)$request->getPathInfo(), '/');
        $parts = explode('/', $path);
        if (count($parts) !== 2 || $parts[0] !== 'launches' || in_array($parts[1], ['index', 'launch'], true)) {
            return null;
        }
        $request->setModuleName('launches')
            ->setControllerName('launch')
            ->setActionName('view')
            ->setParam('url_key', $parts[1]);
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $path);
        return $this->actionFactory->create(Forward::class);
    }
}
