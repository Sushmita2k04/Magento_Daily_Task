<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model\Source;

use Codilar\ProductLaunch\Model\Status;
use Magento\Framework\Data\OptionSourceInterface;

class StatusOptions implements OptionSourceInterface
{
    public function __construct(private readonly Status $status)
    {
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->status->getLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }
}
