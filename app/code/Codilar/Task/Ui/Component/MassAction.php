<?php
declare(strict_types=1);

namespace Codilar\Task\Ui\Component;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\MassAction as BaseMassAction;

/**
 * Grid mass-action bar that drops the Delete action for users without the delete permission.
 * (The controller enforces the permission too; this only keeps the UI honest.)
 */
class MassAction extends BaseMassAction
{
    private const DELETE_RESOURCE = 'Codilar_Task::task_delete';

    /**
     * @param ContextInterface $context
     * @param AuthorizationInterface $authorization
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepare()
    {
        parent::prepare();

        if ($this->authorization->isAllowed(self::DELETE_RESOURCE)) {
            return;
        }
        // After parent::prepare() the bulk actions live in config['actions']; drop the delete one.
        $config = $this->getData('config') ?: [];
        $config['actions'] = array_values(
            array_filter(
                $config['actions'] ?? [],
                static fn(array $action): bool => ($action['type'] ?? '') !== 'delete'
            )
        );
        $this->setData('config', $config);
    }
}
