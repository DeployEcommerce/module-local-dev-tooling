<?php
/**
 * Copyright © Deploy Ecommerce. All rights reserved.
 */
declare(strict_types=1);

namespace DeployEcommerce\LocalDevTooling\Plugin;

use DeployEcommerce\LocalDevTooling\Model\LocalDevDetector;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Keeps admin links on the hostname being browsed when a custom admin URL (admin/url/custom) points elsewhere.
 */
class AdminUrlHostPlugin
{
    public function __construct(
        private readonly LocalDevDetector $localDevDetector,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * @param string|null $result
     * @return string|null
     */
    public function afterGetBaseUrl(Url $subject, $result)
    {
        if (!is_string($result) || !$this->localDevDetector->isLocalDevRequest()) {
            return $result;
        }

        $origin = ($this->request->isSecure() ? 'https' : 'http') . '://' . $this->request->getHttpHost(false);

        return preg_replace('#^[a-z][a-z0-9+.-]*://[^/]+#i', $origin, $result, 1);
    }
}
