<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Adminhtml\Launch\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;

class GenericButton
{
    public function __construct(
        protected readonly UrlInterface $urlBuilder,
        protected readonly RequestInterface $request,
        protected readonly AuthorizationInterface $authorization
    ) {
    }

    public function getLaunchId(): ?int
    {
        $id = (int)$this->request->getParam('launch_id');
        return $id ?: null;
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
