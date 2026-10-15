<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Test\Unit\Model;

use DeployEcommerce\LocalDevTooling\Model\AdminUserProvider;
use Magento\Authorization\Model\ResourceModel\Rules\Collection as RulesCollection;
use Magento\Authorization\Model\ResourceModel\Rules\CollectionFactory as RulesCollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

beforeEach(function () {
    $this->user = $this->getMockBuilder(User::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['loadByUsername', 'getId', 'save'])
        ->getMock();
    $this->user->method('loadByUsername')->willReturnSelf();

    $this->userFactory = $this->createMock(UserFactory::class);
    $this->userFactory->method('create')->willReturn($this->user);

    $this->rules = $this->createMock(RulesCollection::class);
    $this->rules->method('addFieldToFilter')->willReturnSelf();
    $this->rules->method('setPageSize')->willReturnSelf();

    $rulesCollectionFactory = $this->createMock(RulesCollectionFactory::class);
    $rulesCollectionFactory->method('create')->willReturn($this->rules);

    $this->provider = new AdminUserProvider($this->userFactory, $rulesCollectionFactory);
});

it('returns the existing user without saving it', function () {
    $this->user->method('getId')->willReturn(7);
    $this->user->expects($this->never())->method('save');

    expect($this->provider->getOrCreate('admin'))->toBe($this->user);
});

it('creates a missing user as an active member of the full-access admin role', function () {
    $this->user->method('getId')->willReturn(null);
    $this->rules->method('getFirstItem')->willReturn(new DataObject(['role_id' => '1']));
    $this->user->expects($this->once())->method('save');

    $user = $this->provider->getOrCreate('admin');

    expect($user->getData('username'))->toBe('admin')
        ->and($user->getData('email'))->toBe('admin@example.com')
        ->and($user->getData('is_active'))->toBe(1)
        ->and($user->getData('role_id'))->toBe(1)
        // Magento rejects passwords without both letters and digits, and under 7 characters.
        ->and($user->getData('password'))->toMatch('/^(?=.*[a-z])(?=.*\d).{7,}$/i');
});

it('reloads the user it created so the first admin request does not re-save it as new', function () {
    // The saved instance kept isObjectNew() = true; core refreshAcl() then re-saved it without a password
    // and the first auto-login request died with "Password is required field."
    $this->user->method('getId')->willReturn(null);
    $this->rules->method('getFirstItem')->willReturn(new DataObject(['role_id' => '1']));
    $this->userFactory->expects($this->exactly(2))->method('create');

    $this->provider->getOrCreate('admin');
});

it('refuses to create a user when no role grants full access', function () {
    $this->user->method('getId')->willReturn(null);
    $this->rules->method('getFirstItem')->willReturn(new DataObject());
    $this->user->expects($this->never())->method('save');

    $this->provider->getOrCreate('admin');
})->throws(LocalizedException::class);
