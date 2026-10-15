<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Test\Unit\Plugin;

use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use DeployEcommerce\LocalDevTooling\Plugin\AdminUrlHostPlugin;
use DeployEcommerce\LocalDevTooling\Plugin\DisableAdobeImsPlugin;
use DeployEcommerce\LocalDevTooling\Plugin\ForceAdminPathPlugin;
use DeployEcommerce\LocalDevTooling\Plugin\SkipTwoFactorAuthPlugin;
use Magento\AdminAdobeIms\Service\ImsConfig;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\TwoFactorAuth\Observer\ControllerActionPredispatch;

beforeEach(function () {
    $this->detector = function (bool $isLocalDevRequest): LocalDevDetector {
        $detector = $this->createMock(LocalDevDetector::class);
        $detector->method('isLocalDevRequest')->willReturn($isLocalDevRequest);

        return $detector;
    };
});

describe('two-factor auth', function () {
    it('skips the 2FA redirect observer on a local dev request', function () {
        $called = false;
        (new SkipTwoFactorAuthPlugin(($this->detector)(true)))->aroundExecute(
            $this->createMock(ControllerActionPredispatch::class),
            function () use (&$called) {
                $called = true;
            },
            new Observer()
        );

        expect($called)->toBeFalse();
    });

    it('runs the 2FA redirect observer otherwise', function () {
        $called = false;
        (new SkipTwoFactorAuthPlugin(($this->detector)(false)))->aroundExecute(
            $this->createMock(ControllerActionPredispatch::class),
            function () use (&$called) {
                $called = true;
            },
            new Observer()
        );

        expect($called)->toBeTrue();
    });
});

describe('Adobe IMS', function () {
    it('reports IMS admin login disabled on a local dev request', function () {
        $plugin = new DisableAdobeImsPlugin(($this->detector)(true));

        expect($plugin->afterEnabled($this->createMock(ImsConfig::class), true))->toBeFalse();
    });

    it('keeps the configured IMS setting otherwise', function (bool $configured) {
        $plugin = new DisableAdobeImsPlugin(($this->detector)(false));

        expect($plugin->afterEnabled($this->createMock(ImsConfig::class), $configured))->toBe($configured);
    })->with([true, false]);
});

describe('admin path', function () {
    it('serves the admin at /admin/ on any host on a local dev request', function () {
        $plugin = new ForceAdminPathPlugin(($this->detector)(true));
        $resolver = $this->createMock(FrontNameResolver::class);

        expect($plugin->afterGetFrontName($resolver, 'backoffice_x7'))->toBe('admin')
            // false means the host check failed against a custom admin URL.
            ->and($plugin->afterGetFrontName($resolver, false))->toBe('admin')
            ->and($plugin->afterIsHostBackend($resolver, false))->toBeTrue();
    });

    it('keeps the configured admin path and host check otherwise', function () {
        $plugin = new ForceAdminPathPlugin(($this->detector)(false));
        $resolver = $this->createMock(FrontNameResolver::class);

        expect($plugin->afterGetFrontName($resolver, 'backoffice_x7'))->toBe('backoffice_x7')
            ->and($plugin->afterGetFrontName($resolver, false))->toBeFalse()
            ->and($plugin->afterIsHostBackend($resolver, false))->toBeFalse();
    });

    it('points admin links at the host being browsed', function () {
        $request = $this->createMock(HttpRequest::class);
        $request->method('isSecure')->willReturn(true);
        $request->method('getHttpHost')->with(false)->willReturn('acme.deploy:8443');
        $url = $this->createMock(Url::class);

        expect((new AdminUrlHostPlugin(($this->detector)(true), $request))
            ->afterGetBaseUrl($url, 'http://admin.acme.com/sub/index.php/'))
            ->toBe('https://acme.deploy:8443/sub/index.php/')
            ->and((new AdminUrlHostPlugin(($this->detector)(false), $request))
                ->afterGetBaseUrl($url, 'http://admin.acme.com/'))
            ->toBe('http://admin.acme.com/');
    });
});
