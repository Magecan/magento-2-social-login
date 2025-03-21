<?php
/**
 * Copyright © Magecan, Inc. All rights reserved.
 */
namespace Magecan\SocialLogin\Helper;

use Hybridauth\Hybridauth;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

class SocialLogin extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Hybridauth\Hybridauth
     */
    protected $hybridauth;

    /**
     * @var string
     */
    protected $socialLoginUrl;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $directoryList
    ) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
    }

    /**
     * Retrieve the Hybridauth instance.
     *
     * If the instance is not already created, it initializes it with the configuration.
     *
     * @return \Hybridauth\Hybridauth
     */
    public function getHybridauth()
    {
        if (!$this->hybridauth) {
            try {
                $this->hybridauth = new Hybridauth($this->getConfig());
            } catch (\Exception $e) {
                throw new \Hybridauth\Exception\InvalidArgumentException(__($e->getMessage()));
            }
        }

        return $this->hybridauth;
    }

    /**
     * Retrieve the list of available social login providers.
     *
     * @return array
     */
    public function getProviders()
    {
        try {
            return $this->getHybridauth()->getProviders();
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve the configuration for social login providers.
     *
     * Constructs the configuration array for Hybridauth, including callback URL,
     * enabled providers, keys, and debugging options if applicable.
     *
     * @return array
     */
    protected function getConfig()
    {
        $socialLoginSectionConfig =  $this->scopeConfig->getValue('social_login', ScopeInterface::SCOPE_STORE);

        $hybridauthConfig = [
            'callback' => $this->getSocialLoginUrl(),
            'providers' => []
        ];

        $generalGroupConfig = $socialLoginSectionConfig['general'];
        if (!(bool)$generalGroupConfig['enabled']) {
            return $hybridauthConfig;
        }

        if (isset($generalGroupConfig['debug']) && (bool)$generalGroupConfig['debug']) {
            $hybridauthConfig['debug_mode'] = 'debug';
            $hybridauthConfig['debug_file'] = $this->directoryList->getRoot() . '/var/log/social-login.log';
        }

        unset($socialLoginSectionConfig['general']);
        foreach ($socialLoginSectionConfig as $providerCode => $providerGroupConfig) {
            if (!isset($providerGroupConfig['active'])
                || !(bool)$providerGroupConfig['active']
                || !isset($providerGroupConfig['client_id'])
                || !isset($providerGroupConfig['client_secret'])
                || !isset($providerGroupConfig['scope'])
            ) {
                continue;
            }

            $providerLabel = ucfirst($providerCode);

            $hybridauthConfig['providers'][$providerLabel] = [
                    'enabled' => true,
                    'keys' => [
                        'id' => $providerGroupConfig['client_id'],
                        'secret' => $providerGroupConfig['client_secret']
                    ],
                    'scope' => $providerGroupConfig['scope']
            ];
        }
        return $hybridauthConfig;
    }

    /**
     * Generate the URL for social login.
     *
     * @return string
     */
    public function getSocialLoginUrl()
    {
        if (!$this->socialLoginUrl) {
            $this->socialLoginUrl = $this->_urlBuilder->getUrl('sociallogin/login');
        }

        return $this->socialLoginUrl;
    }
}
