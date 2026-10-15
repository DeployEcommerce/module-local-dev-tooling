<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Model;

use Composer\InstalledVersions;
use DeployEcommerce\LocalDevTooling\Composer\DevDependencyGuard;

/**
 * Runtime twin of the Composer guard, for installs where the Composer plugin was blocked by allow-plugins.
 * A copy in app/code (not installed by Composer) also counts as not a dev dependency.
 */
class DevDependencyCheck
{
    public function isDevDependency(): bool
    {
        foreach (InstalledVersions::getAllRawData() as $installed) {
            if (isset($installed['versions'][DevDependencyGuard::PACKAGE_NAME])) {
                return ($installed['versions'][DevDependencyGuard::PACKAGE_NAME]['dev_requirement'] ?? false) === true;
            }
        }

        return false;
    }
}
