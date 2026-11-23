<?php
declare(strict_types=1);

namespace Codilar\Task\Model\Source;

use Codilar\Task\Api\Data\TaskInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Task priority options (admin grid/form and storefront labels).
 */
class Priority implements OptionSourceInterface
{
    /**
     * Value => label map.
     *
     * @return array<int, \Magento\Framework\Phrase>
     */
    public function getOptionArray(): array
    {
        return [
            TaskInterface::PRIORITY_LOW => __('Low'),
            TaskInterface::PRIORITY_MEDIUM => __('Medium'),
            TaskInterface::PRIORITY_HIGH => __('High'),
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
