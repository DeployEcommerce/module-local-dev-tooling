<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Plugin;

use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use Magento\Framework\Event\Observer;
use Magento\TwoFactorAuth\Observer\ControllerActionPredispatch;

/**
 * Skips the observer that redirects signed-in admins to the 2FA setup/challenge pages.
 */
class SkipTwoFactorAuthPlugin
{
    public function __construct(
        private readonly LocalDevDetector $localDevDetector
    ) {
    }

    public function aroundExecute(ControllerActionPredispatch $subject, callable $proceed, Observer $observer): mixed
    {
        if ($this->localDevDetector->isLocalDevRequest()) {
            return null;
        }

        return $proceed($observer);
    }
}
