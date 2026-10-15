<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Test\Unit\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BufferIO;
use Composer\Package\RootPackage;
use DeployEcommerce\LocalDevTooling\Composer\DevDependencyGuard;
use RuntimeException;

beforeEach(function () {
    $this->vendorDir = sys_get_temp_dir() . '/local-dev-tooling-guard-' . bin2hex(random_bytes(4));
    mkdir($this->vendorDir . '/composer', 0777, true);

    $this->io = new BufferIO();

    $this->guard = function (?bool $devRequirement, string $rootName = 'acme/shop'): DevDependencyGuard {
        $versions = $devRequirement === null
            ? []
            : [DevDependencyGuard::PACKAGE_NAME => ['dev_requirement' => $devRequirement]];
        file_put_contents(
            $this->vendorDir . '/composer/installed.php',
            '<?php return ' . var_export(['versions' => $versions], true) . ';'
        );

        $config = new Config(false);
        $config->merge(['config' => ['vendor-dir' => $this->vendorDir]]);
        $composer = new Composer();
        $composer->setConfig($config);
        $composer->setPackage(new RootPackage($rootName, '1.0.0.0', '1.0.0'));

        $guard = new DevDependencyGuard();
        $guard->activate($composer, $this->io);

        return $guard;
    };
});

afterEach(function () {
    if (is_file($this->vendorDir . '/composer/installed.php')) {
        unlink($this->vendorDir . '/composer/installed.php');
    }
    rmdir($this->vendorDir . '/composer');
    rmdir($this->vendorDir);
});

it('runs after Composer writes installed.php', function () {
    expect(DevDependencyGuard::getSubscribedEvents())->toBe(['post-autoload-dump' => 'assertDevDependency']);
});

it('fails the Composer run when installed as a normal dependency', function () {
    expect(fn () => ($this->guard)(false)->assertDevDependency())
        ->toThrow(RuntimeException::class, 'composer require --dev ' . DevDependencyGuard::PACKAGE_NAME)
        ->and($this->io->getOutput())->toContain('MUST be installed as a dev dependency');
});

it('allows a dev dependency install', function () {
    ($this->guard)(true)->assertDevDependency();

    expect($this->io->getOutput())->toBe('');
});

it('ignores runs where the package is not installed', function () {
    ($this->guard)(null)->assertDevDependency();

    expect($this->io->getOutput())->toBe('');
});

it('ignores Composer runs inside the module repository itself', function () {
    ($this->guard)(false, DevDependencyGuard::PACKAGE_NAME)->assertDevDependency();

    expect($this->io->getOutput())->toBe('');
});
