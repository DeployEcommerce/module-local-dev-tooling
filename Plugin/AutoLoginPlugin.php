<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Plugin;

use DeployEcommerce\LocalDevTooling\Model\AdminUserProvider;
use DeployEcommerce\LocalDevTooling\Model\Config;
use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use Magento\Backend\App\AbstractAction;
use Magento\Backend\Model\Auth;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Security\Model\AdminSessionsManager;

/**
 * Signs the local admin user in before core admin authentication runs, skipping the password check.
 *
 * Mirrors Magento\Backend\Model\Auth::login() minus the credential check, plus the admin_user_session
 * row Magento_Security normally writes after login (without it the session is ended on the next request).
 */
class AutoLoginPlugin
{
    public function __construct(
        private readonly LocalDevDetector $localDevDetector,
        private readonly Config $config,
        private readonly AdminUserProvider $adminUserProvider,
        private readonly Auth $auth,
        private readonly AdminSessionsManager $adminSessionsManager,
        private readonly EventManager $eventManager
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function beforeDispatch(AbstractAction $subject, RequestInterface $request): void
    {
        if (!$this->localDevDetector->isLocalDevRequest()
            || $request->getActionName() === 'logout'
            || $this->auth->isLoggedIn()
        ) {
            return;
        }

        $user = $this->adminUserProvider->getOrCreate($this->config->getAdminUsername());
        $user->getResource()->recordLogin($user);

        $authStorage = $this->auth->getAuthStorage();
        $authStorage->setUser($user);
        $authStorage->processLogin();
        $this->adminSessionsManager->processLogin();

        $this->eventManager->dispatch('backend_auth_user_login_success', ['user' => $user]);
    }
}
