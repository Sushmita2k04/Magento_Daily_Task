<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
namespace Codilar\CoreFix\Model\Layout\Update;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\Config\DomFactory;
use Magento\Framework\Config\ValidationStateInterface;
use Magento\Framework\View\Model\Layout\Update\Validator as CoreValidator;

/**
 * Corrected copy of Magento\Framework\View\Model\Layout\Update\Validator.
 *
 * The vendor class hardcodes 'urn:magento:framework:Index/Layout/etc/*.xsd' in its
 * constructor, but those schemas actually live under 'View/Layout/etc/', so every
 * construction of that class throws a schema-not-found exception. That exception surfaces
 * during controller_action_predispatch (CMS custom layout update validation), which
 * FrontController::dispatch() treats as a NotFoundException, forwards to noroute, and repeats
 * forever until it hits the 100 router-match-iteration cap.
 *
 * This subclass extends the vendor class (other core classes type-hint it concretely) but
 * replaces the constructor to resolve the correct schema URNs, skipping the buggy parent
 * constructor entirely.
 */
class Validator extends CoreValidator
{
    /**
     * @param DomFactory $domConfigFactory
     * @param UrnResolver $urnResolver
     * @param ValidationStateInterface|null $validationState
     */
    public function __construct(
        DomFactory $domConfigFactory,
        UrnResolver $urnResolver,
        ?ValidationStateInterface $validationState = null
    ) {
        $this->_domConfigFactory = $domConfigFactory;
        $this->_initMessageTemplates();
        $this->_xsdSchemas = [
            self::LAYOUT_SCHEMA_PAGE_HANDLE => $urnResolver->getRealPath(
                'urn:magento:framework:View/Layout/etc/page_layout.xsd'
            ),
            self::LAYOUT_SCHEMA_MERGED => $urnResolver->getRealPath(
                'urn:magento:framework:View/Layout/etc/layout_merged.xsd'
            ),
        ];
        $this->validationState = $validationState
            ?: ObjectManager::getInstance()->get(ValidationStateInterface::class);

        \Laminas\Validator\AbstractValidator::__construct();
    }
}
