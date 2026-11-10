<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use Codilar\BuildYourOwnBox\Model\Product\Type\Box;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

/**
 * Re-validates the boxes of a quote against the live catalog and enforces "one box per order".
 * Component stock itself is checked by Magento's own quote-item validation (qty options).
 */
class QuoteGuard
{
    public function __construct(
        private readonly BoxValidator $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return Item[] box items of the quote
     */
    public function getBoxItems(Quote $quote): array
    {
        return array_values(array_filter(
            $quote->getAllItems(),
            static fn(Item $item): bool => $item->getProductType() === Box::TYPE_CODE
        ));
    }

    /**
     * @return array<int, array{item: Item, message: string}>
     */
    public function getErrors(Quote $quote): array
    {
        $errors = [];
        $boxes = $this->getBoxItems($quote);
        foreach ($boxes as $index => $item) {
            if ($index > 0) {
                $errors[] = ['item' => $item, 'message' => (string)__('Only one gift box is allowed per order.')];
                continue;
            }
            try {
                $composition = BoxComposition::fromProduct($item->getProduct());
                if ($composition === null) {
                    throw new LocalizedException(__('This gift box is no longer valid. Please assemble it again.'));
                }
                $this->validator->revalidate($composition);
            } catch (LocalizedException $e) {
                $errors[] = ['item' => $item, 'message' => $e->getMessage()];
            } catch (\Throwable $e) {
                $this->logger->error('BuildYourOwnBox quote validation failed: ' . $e->getMessage());
                $errors[] = [
                    'item' => $item,
                    'message' => (string)__('This gift box could not be verified. Please assemble it again.'),
                ];
            }
        }
        return $errors;
    }
}
