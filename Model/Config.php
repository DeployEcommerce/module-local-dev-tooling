<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Model;

use Magento\Framework\App\DeploymentConfig;

/**
 * Reads the module's settings from app/etc/env.php:
 *
 * 'local_dev_tooling' => [
 *     'hostname_contains' => '.deploy',
 *     'admin_username' => 'admin',
 * ],
 */
class Config
{
    public const PATH_HOSTNAME_CONTAINS = 'local_dev_tooling/hostname_contains';
    public const PATH_ADMIN_USERNAME = 'local_dev_tooling/admin_username';

    public const DEFAULT_HOSTNAME_CONTAINS = '.deploy';
    public const DEFAULT_ADMIN_USERNAME = 'admin';

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    public function getHostnameContains(): string
    {
        return (string)$this->deploymentConfig->get(self::PATH_HOSTNAME_CONTAINS, self::DEFAULT_HOSTNAME_CONTAINS);
    }

    public function getAdminUsername(): string
    {
        return (string)$this->deploymentConfig->get(self::PATH_ADMIN_USERNAME, self::DEFAULT_ADMIN_USERNAME);
    }
}
