<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Model;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;

/**
 * Decides whether the current request may use the local dev tooling. Every plugin in this module checks it first.
 */
class LocalDevDetector
{
    private ?bool $isLocalDevRequest = null;

    public function __construct(
        private readonly State $appState,
        private readonly HttpRequest $request,
        private readonly Config $config,
        private readonly DevDependencyCheck $devDependencyCheck
    ) {
    }

    public function isLocalDevRequest(): bool
    {
        return $this->isLocalDevRequest ??= $this->appState->getMode() !== State::MODE_PRODUCTION
            && $this->hostnameMatches()
            && $this->devDependencyCheck->isDevDependency();
    }

    private function hostnameMatches(): bool
    {
        $needle = $this->config->getHostnameContains();
        $hostname = (string)$this->request->getHttpHost();

        return $needle !== '' && $hostname !== '' && stripos($hostname, $needle) !== false;
    }
}
