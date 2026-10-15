<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use RuntimeException;

/**
 * Fails the Composer run when this package is installed as anything other than a dev dependency.
 *
 * Must not reference Magento classes: it runs inside the Composer process.
 */
class DevDependencyGuard implements PluginInterface, EventSubscriberInterface
{
    public const PACKAGE_NAME = 'deployecommerce/module-local-dev-tooling';

    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::POST_AUTOLOAD_DUMP => 'assertDevDependency'];
    }

    /**
     * Composer writes installed.php, including each package's dev_requirement flag, before the autoload dump.
     *
     * @throws RuntimeException
     */
    public function assertDevDependency(): void
    {
        if ($this->composer->getPackage()->getName() === self::PACKAGE_NAME) {
            return;
        }

        $installedFile = $this->composer->getConfig()->get('vendor-dir') . '/composer/installed.php';
        $installed = is_file($installedFile) ? include $installedFile : [];
        $package = $installed['versions'][self::PACKAGE_NAME] ?? null;

        if ($package === null || ($package['dev_requirement'] ?? false) === true) {
            return;
        }

        $message = sprintf(
            '%1$s MUST be installed as a dev dependency. It signs anyone into the Magento admin without a '
            . 'password and disables two-factor auth. Remove it and re-add it with: '
            . 'composer remove %1$s && composer require --dev %1$s',
            self::PACKAGE_NAME
        );
        $this->io->writeError(['', '<error>' . $message . '</error>', '']);

        throw new RuntimeException($message);
    }
}
