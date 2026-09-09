<?php
declare(strict_types=1);

namespace Codilar\CoreFix\Model\View\PageLayout;

/**
 * Corrected copy of Magento\Framework\View\PageLayout\Config.
 *
 * getSchemaFile() hardcodes 'urn:magento:framework:Index/PageLayout/etc/layouts.xsd', but the
 * schema actually lives under 'View/PageLayout/etc/', so resolving it throws a
 * schema-not-found exception whenever page layout config is read.
 */
class Config extends \Magento\Framework\View\PageLayout\Config
{
    /**
     * Get absolute path to the XML-schema file
     *
     * @return string
     */
    public function getSchemaFile()
    {
        return $this->urnResolver->getRealPath('urn:magento:framework:View/PageLayout/etc/layouts.xsd');
    }
}
