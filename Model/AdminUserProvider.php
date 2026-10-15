<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Model;

use Magento\Authorization\Model\ResourceModel\Rules\CollectionFactory as RulesCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

/**
 * Loads the configured local admin user, creating it with full admin rights on first use.
 */
class AdminUserProvider
{
    private const FULL_ACCESS_RESOURCE = 'Magento_Backend::all';

    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly RulesCollectionFactory $rulesCollectionFactory
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function getOrCreate(string $username): User
    {
        $user = $this->userFactory->create()->loadByUsername($username);
        if ($user->getId()) {
            return $user;
        }

        $user->setData([
            'username' => $username,
            'firstname' => 'Local',
            'lastname' => 'Developer',
            'email' => $username . '@example.com',
            // Nobody types this password; it only has to satisfy Magento's password rules.
            'password' => bin2hex(random_bytes(16)) . 'aA1',
            'is_active' => 1,
        ]);
        $user->setRoleId($this->getFullAccessRoleId());
        $user->save();

        // The saved instance still reports isObjectNew(), so core Auth\Session::refreshAcl() would re-save it
        // with the password unset and fail validation. A fresh load behaves like any existing user.
        return $this->userFactory->create()->loadByUsername($username);
    }

    /**
     * @throws LocalizedException
     */
    private function getFullAccessRoleId(): int
    {
        $rule = $this->rulesCollectionFactory->create()
            ->addFieldToFilter('resource_id', self::FULL_ACCESS_RESOURCE)
            ->addFieldToFilter('permission', 'allow')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$rule->getData('role_id')) {
            throw new LocalizedException(
                __('No admin role grants %1, so the local admin user cannot be created.', self::FULL_ACCESS_RESOURCE)
            );
        }

        return (int)$rule->getData('role_id');
    }
}
