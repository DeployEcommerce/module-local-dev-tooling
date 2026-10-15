<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Plugin;

use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use Magento\Backend\App\Area\FrontNameResolver;

/**
 * Serves the admin at /admin/ on the current hostname, overriding env.php backend/frontName,
 * the admin/url/use_custom_path custom path and the admin/url/use_custom custom admin URL.
 */
class ForceAdminPathPlugin
{
    public const ADMIN_FRONT_NAME = 'admin';

    public function __construct(
        private readonly LocalDevDetector $localDevDetector
    ) {
    }

    /**
     * @param string|bool $result
     * @return string|bool
     */
    public function afterGetFrontName(FrontNameResolver $subject, $result)
    {
        return $this->localDevDetector->isLocalDevRequest() ? self::ADMIN_FRONT_NAME : $result;
    }

    public function afterIsHostBackend(FrontNameResolver $subject, bool $result): bool
    {
        return $result || $this->localDevDetector->isLocalDevRequest();
    }
}
