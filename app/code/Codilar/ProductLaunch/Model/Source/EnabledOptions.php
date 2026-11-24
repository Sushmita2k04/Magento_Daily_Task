<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EnabledOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 1, 'label' => __('Enabled')],
            ['value' => 0, 'label' => __('Disabled')],
        ];
    }
}
