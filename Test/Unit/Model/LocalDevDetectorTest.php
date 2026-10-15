<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Test\Unit\Model;

use DeployEcommerce\LocalDevTooling\Model\Config;
use DeployEcommerce\LocalDevTooling\Model\DevDependencyCheck;
use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;

/**
 * Every plugin in the module trusts this answer, so a false positive here signs strangers into the admin.
 */
beforeEach(function () {
    $this->detector = function (
        string $mode,
        string $host,
        ?string $hostnameContains = null,
        bool $isDevDependency = true
    ): LocalDevDetector {
        $appState = $this->createMock(State::class);
        $appState->method('getMode')->willReturn($mode);

        $request = $this->createMock(HttpRequest::class);
        $request->method('getHttpHost')->willReturn($host);

        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturnCallback(
            fn (string $path, $default) => $path === Config::PATH_HOSTNAME_CONTAINS && $hostnameContains !== null
                ? $hostnameContains
                : $default
        );

        $devDependencyCheck = $this->createMock(DevDependencyCheck::class);
        $devDependencyCheck->method('isDevDependency')->willReturn($isDevDependency);

        return new LocalDevDetector($appState, $request, new Config($deploymentConfig), $devDependencyCheck);
    };
});

it('is active on a .deploy hostname outside production mode', function (string $mode, string $host) {
    expect(($this->detector)($mode, $host)->isLocalDevRequest())->toBeTrue();
})->with([
    'developer mode' => [State::MODE_DEVELOPER, 'acme.deploy'],
    'default mode' => [State::MODE_DEFAULT, 'www.acme.deploy'],
    'upper-case hostname' => [State::MODE_DEVELOPER, 'ACME.DEPLOY'],
]);

it('stays inactive in production mode even on a .deploy hostname', function () {
    expect(($this->detector)(State::MODE_PRODUCTION, 'acme.deploy')->isLocalDevRequest())->toBeFalse();
});

it('stays inactive on hostnames without .deploy', function (string $host) {
    expect(($this->detector)(State::MODE_DEVELOPER, $host)->isLocalDevRequest())->toBeFalse();
})->with([
    'live site' => ['www.acme.com'],
    'deploy without the dot' => ['deployecommerce.com'],
    'CLI, no hostname' => [''],
]);

it('stays inactive when not installed as a dev dependency', function () {
    expect(($this->detector)(State::MODE_DEVELOPER, 'acme.deploy', null, false)->isLocalDevRequest())
        ->toBeFalse();
});

it('matches the hostname set in env.php instead of .deploy', function () {
    expect(($this->detector)(State::MODE_DEVELOPER, 'acme.test', '.test')->isLocalDevRequest())->toBeTrue()
        ->and(($this->detector)(State::MODE_DEVELOPER, 'acme.deploy', '.test')->isLocalDevRequest())->toBeFalse();
});

it('is switched off by an empty env.php hostname', function () {
    expect(($this->detector)(State::MODE_DEVELOPER, 'acme.deploy', '')->isLocalDevRequest())->toBeFalse();
});
