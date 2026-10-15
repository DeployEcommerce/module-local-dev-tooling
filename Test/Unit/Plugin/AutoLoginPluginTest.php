<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Test\Unit\Plugin;

use DeployEcommerce\LocalDevTooling\Model\AdminUserProvider;
use DeployEcommerce\LocalDevTooling\Model\Config;
use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use DeployEcommerce\LocalDevTooling\Plugin\AutoLoginPlugin;
use Magento\Backend\App\AbstractAction;
use Magento\Backend\Model\Auth;
use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Security\Model\AdminSessionsManager;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;

beforeEach(function () {
    // Auth::getAuthStorage() is typed as StorageInterface but core calls the session's magic setUser().
    $this->authStorage = new class implements StorageInterface {
        public ?User $user = null;
        public bool $loginProcessed = false;

        public function setUser(User $user): self
        {
            $this->user = $user;
            return $this;
        }

        public function processLogin()
        {
            $this->loginProcessed = true;
            return $this;
        }

        public function processLogout()
        {
            return $this;
        }

        public function isLoggedIn()
        {
            return $this->user !== null;
        }

        public function prolong()
        {
        }
    };

    $this->auth = $this->createMock(Auth::class);
    $this->auth->method('getAuthStorage')->willReturn($this->authStorage);

    $this->userResource = $this->createMock(UserResource::class);
    $this->user = $this->createMock(User::class);
    $this->user->method('getResource')->willReturn($this->userResource);

    $this->adminUserProvider = $this->createMock(AdminUserProvider::class);
    $this->adminUserProvider->method('getOrCreate')->willReturn($this->user);

    $this->adminSessionsManager = $this->createMock(AdminSessionsManager::class);
    $this->eventManager = $this->createMock(EventManager::class);

    $this->request = $this->createMock(RequestInterface::class);
    $this->action = $this->createMock(AbstractAction::class);

    $this->plugin = function (bool $isLocalDevRequest): AutoLoginPlugin {
        $detector = $this->createMock(LocalDevDetector::class);
        $detector->method('isLocalDevRequest')->willReturn($isLocalDevRequest);

        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturnArgument(1);

        return new AutoLoginPlugin(
            $detector,
            new Config($deploymentConfig),
            $this->adminUserProvider,
            $this->auth,
            $this->adminSessionsManager,
            $this->eventManager
        );
    };
});

it('signs the configured user in and records the admin session', function () {
    $this->request->method('getActionName')->willReturn('index');
    $this->auth->method('isLoggedIn')->willReturn(false);

    $this->adminUserProvider->expects($this->once())->method('getOrCreate')->with('admin');
    $this->userResource->expects($this->once())->method('recordLogin')->with($this->user);
    // Without the admin_user_session row, Magento_Security ends the session on the next request.
    $this->adminSessionsManager->expects($this->once())->method('processLogin');
    $this->eventManager->expects($this->once())->method('dispatch')
        ->with('backend_auth_user_login_success', ['user' => $this->user]);

    ($this->plugin)(true)->beforeDispatch($this->action, $this->request);

    expect($this->authStorage->user)->toBe($this->user)
        ->and($this->authStorage->loginProcessed)->toBeTrue();
});

it('does nothing outside a local dev request', function () {
    $this->request->method('getActionName')->willReturn('index');
    $this->auth->method('isLoggedIn')->willReturn(false);
    $this->adminUserProvider->expects($this->never())->method('getOrCreate');

    ($this->plugin)(false)->beforeDispatch($this->action, $this->request);

    expect($this->authStorage->user)->toBeNull();
});

it('leaves an already signed-in admin alone', function () {
    $this->request->method('getActionName')->willReturn('index');
    $this->auth->method('isLoggedIn')->willReturn(true);
    $this->adminUserProvider->expects($this->never())->method('getOrCreate');

    ($this->plugin)(true)->beforeDispatch($this->action, $this->request);
});

it('lets the logout action run without signing back in first', function () {
    $this->request->method('getActionName')->willReturn('logout');
    $this->auth->method('isLoggedIn')->willReturn(false);
    $this->adminUserProvider->expects($this->never())->method('getOrCreate');

    ($this->plugin)(true)->beforeDispatch($this->action, $this->request);
});
