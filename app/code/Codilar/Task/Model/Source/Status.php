<?php
declare(strict_types=1);

namespace Codilar\Task\Model\Source;

use Codilar\Task\Api\Data\TaskInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Task status options (admin grid/form and storefront labels).
 */
class Status implements OptionSourceInterface
{
    /**
     * Value => label map.
     *
     * @return array<int, \Magento\Framework\Phrase>
     */
    public function getOptionArray(): array
    {
        return [
            TaskInterface::STATUS_PENDING => __('Pending'),
            TaskInterface::STATUS_IN_PROGRESS => __('In Progress'),
            TaskInterface::STATUS_COMPLETED => __('Completed'),
        ];
    }

    /**
     * Options in the shape UI components expect.
     *
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getOptionArray() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    /**
     * Label for one value, or an empty string when unknown.
     *
     * @param int|string|null $value
     * @return \Magento\Framework\Phrase|string
     */
    public function getLabel($value)
    {
        $options = $this->getOptionArray();
        return $options[(int) $value] ?? '';
    }

    /**
     * Whether the value is a strict integer that is one of the options.
     *
     * @param mixed $value
     * @return bool
     */
    public function isValid($value): bool
    {
        return is_numeric($value) && array_key_exists((int) $value, $this->getOptionArray())
            && (string) (int) $value === (string) $value;
    }
}
