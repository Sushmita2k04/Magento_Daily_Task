<?php
namespace Codilar\CoreFix\Model\View\Layout\PageType\Config;

use Magento\Framework\Config\Dom\UrnResolver;

/**
 * Corrected copy of Magento\Framework\View\Layout\PageType\Config\SchemaLocator.
 *
 * The vendor constructor hardcodes 'urn:magento:framework:Index/Layout/etc/page_types.xsd',
 * but the schema actually lives under 'View/Layout/etc/', so resolving it throws a
 * schema-not-found exception whenever page-type config is read.
 */
class SchemaLocator extends \Magento\Framework\View\Layout\PageType\Config\SchemaLocator
{
    /**
     * @param UrnResolver $urnResolver
     */
    public function __construct(UrnResolver $urnResolver)
    {
        $this->schema = $urnResolver->getRealPath('urn:magento:framework:View/Layout/etc/page_types.xsd');
    }
}
