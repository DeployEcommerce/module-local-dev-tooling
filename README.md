# DeployEcommerce_LocalDevTooling

Local development conveniences for Magento 2 admin:

- Signs you into the admin automatically, creating the admin user on first visit.
- Skips two-factor auth (`Magento_TwoFactorAuth`).
- Turns off Adobe IMS admin login (`Magento_AdminAdobeIms`, `Magento_AdminAdobeImsTwoFactorAuth`) so the auto-login session is accepted on Adobe Commerce.
- Serves the admin at `/admin/` on the hostname you are browsing, ignoring `backend/frontName` in `env.php`, the custom admin path and the custom admin URL.

> **Warning:** this module lets anyone who can reach the site into the admin without a password. It must never run on a public environment.

## When it is active

All of these must be true for a request. Otherwise the module does nothing.

1. Magento is **not** in production mode (`MAGE_MODE` is `developer` or `default`).
2. The request hostname contains `.deploy` (configurable, see below). CLI commands have no hostname, so the module never acts on them.
3. The package was installed by Composer as a **dev dependency**.

## Install

```bash
composer config allow-plugins.deployecommerce/module-local-dev-tooling true
composer require --dev deployecommerce/module-local-dev-tooling
bin/magento module:enable DeployEcommerce_LocalDevTooling
bin/magento setup:upgrade
```

The package is a Composer plugin. Installing it with `composer require` (without `--dev`), or as a dependency of any non-dev package, fails the Composer run with an error. Composer does not roll back `composer.json`, so every later `composer install`/`update` keeps failing until the package is moved to `require-dev`. If the plugin is blocked by `allow-plugins`, the module still checks at runtime and stays inactive unless it is a dev dependency.

Production builds run `composer install --no-dev`, so the package is absent there. Magento ignores the `DeployEcommerce_LocalDevTooling` entry in `app/etc/config.php` when the module code is missing.

## Configuration (`app/etc/env.php`)

```php
'local_dev_tooling' => [
    // Substring the request hostname must contain. Case-insensitive. Empty string disables the module.
    'hostname_contains' => '.deploy',
    // Admin user to sign in as. Created with the full-access admin role if it does not exist.
    'admin_username' => 'admin',
],
```

Both keys are optional; the values above are the defaults.

## Compatibility

- PHP 8.1+
- Magento Open Source / Adobe Commerce 2.4.4+

## Tests

Pest unit tests live in `Test/Unit`. They need Magento's classes, so run them from a Magento project that has this package installed, using that project's Pest bootstrap:

```bash
vendor/bin/pest vendor/deployecommerce/module-local-dev-tooling/Test
```

## Not covered

- REST/SOAP admin tokens: 2FA for `POST /V1/integration/admin/token` is unchanged.
- Signing out works, but the next admin page signs you straight back in.
