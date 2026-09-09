<?php
namespace Codilar\CoreFix\Model\Theme\Layout\Config;

use Magento\Framework\Config\Dom\UrnResolver;

/**
 * Corrected copy of Magento\Theme\Model\Layout\Config\SchemaLocator.
 *
 * The vendor constructor hardcodes 'urn:magento:framework:Index/PageLayout/etc/layouts.xsd',
 * but the schema actually lives under 'View/PageLayout/etc/', so resolving it throws a
 * schema-not-found exception whenever the theme layout config is read.
 */
class SchemaLocator extends \Magento\Theme\Model\Layout\Config\SchemaLocator
{
    /**
     * @param UrnResolver $urnResolver
     */
    public function __construct(UrnResolver $urnResolver)
    {
        $this->_schema = $urnResolver->getRealPath('urn:magento:framework:View/PageLayout/etc/layouts.xsd');
    }
}
