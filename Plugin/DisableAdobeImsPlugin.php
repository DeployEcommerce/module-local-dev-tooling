<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Plugin;

use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use Magento\AdminAdobeIms\Service\ImsConfig;

/**
 * Reports Adobe IMS admin login as disabled on local hostnames.
 *
 * Every Magento_AdminAdobeIms and Magento_AdminAdobeImsTwoFactorAuth plugin checks ImsConfig::enabled(),
 * so this one switch stops IMS from rejecting the auto-login session or replacing the admin login flow.
 */
class DisableAdobeImsPlugin
{
    public function __construct(
        private readonly LocalDevDetector $localDevDetector
    ) {
    }

    public function afterEnabled(ImsConfig $subject, bool $result): bool
    {
        return $result && !$this->localDevDetector->isLocalDevRequest();
    }
}
