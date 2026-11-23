<?php
declare(strict_types=1);

namespace Codilar\Task\Block\Adminhtml\Task\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;

/**
 * Shared helpers for the form buttons.
 */
class GenericButton
{
    /**
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        protected readonly UrlInterface $urlBuilder,
        protected readonly RequestInterface $request,
        protected readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Id of the task being edited, if any.
     *
     * @return int|null
     */
    public function getTaskId(): ?int
    {
        $id = (int) $this->request->getParam('task_id');
        return $id ?: null;
    }

    /**
     * Backend URL.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
