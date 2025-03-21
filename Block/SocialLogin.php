<?php
/**
 * Copyright © Magecan, Inc. All rights reserved.
 */
namespace Magecan\SocialLogin\Block;

class SocialLogin extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magecan\SocialLogin\Helper\SocialLogin
     */
    protected $socialLoginHelper;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magecan\SocialLogin\Helper\SocialLogin $socialLoginHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magecan\SocialLogin\Helper\SocialLogin $socialLoginHelper,
        array $data = []
    ) {
        $this->socialLoginHelper = $socialLoginHelper;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve enabled social login providers with details for rendering
     *
     * @return array
     */
    public function getProviders()
    {
        $providers = [];

        foreach ($this->socialLoginHelper->getProviders() as $provider) {
            $providerCode = strtolower($provider);
            
            $providers[] = [
                'label' => $provider,
                'code' => $providerCode,
                'socialLoginUrl' => $this->socialLoginHelper->getSocialLoginUrl() . '?provider=' . $provider,
                'logoIconUrl' => $this->getViewFileUrl('Magecan_SocialLogin::images/' . $providerCode . '.svg')
            ];
        }

        return $providers;
    }
}
