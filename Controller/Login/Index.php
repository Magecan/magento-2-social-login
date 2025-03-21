<?php
/**
 * Copyright © Magecan, Inc. All rights reserved.
 */
namespace Magecan\SocialLogin\Controller\Login;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;

class Index extends Action
{
    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
     */
    protected $customerInterfaceFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private $cookieMetadataManager;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var \Magecan\SocialLogin\Helper\SocialLogin
     */
    protected $socialLoginHelper;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param SocialLogin $socialLoginHelper
     * @param Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerInterfaceFactory $customerInterfaceFactory
     * @param PhpCookieManager $cookieMetadataManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        \Magecan\SocialLogin\Helper\SocialLogin $socialLoginHelper,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerInterfaceFactory,
        \Magento\Framework\Stdlib\Cookie\PhpCookieManager $cookieMetadataManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->socialLoginHelper = $socialLoginHelper;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        $this->cookieMetadataManager = $cookieMetadataManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->logger = $logger;
    }

    /**
     * Execute the social login action.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            $provider = $this->validateProvider();
            $userProfile = $this->authenticateUser($provider);

            if (!$userProfile->email || !$userProfile->firstName || !$userProfile->lastName) {
                throw new LocalizedException(__('Incomplete user profile'));
            }

            $customer = $this->loadOrCreateCustomer($userProfile);
            $this->logCustomerIn($customer);
            $this->messageManager->addSuccessMessage(__('You are logged in as %1', $userProfile->email));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Hybridauth\Exception\AuthorizationDeniedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred. Please try again.'));
            $this->logger->error($e);
        }

        return $this->_redirect($this->customerSession->getData('referer_url'));
    }

    /**
     * Validate the provider parameter and store in session.
     *
     * @return string
     * @throws LocalizedException
     */
    private function validateProvider(): string
    {
        $provider = $this->getRequest()->getParam('provider');
        if (isset($provider)) {
            // Validate provider exists in the $config
            if (in_array($provider, $this->socialLoginHelper->getProviders())) {
                $this->customerSession->setData('provider', $provider);
                $this->customerSession->setData('referer_url', $this->_redirect->getRefererUrl());
            } else {
                throw new LocalizedException(__('Invalid provider specified'));
            }
        }

        $provider = $this->customerSession->getData('provider');
        if (!$provider) {
            throw new \Exception(__('provider type missing in customer session'));
        }

        return $provider;
    }

    /**
     * Authenticate user with the specified provider.
     *
     * @param string $provider
     * @return Profile
     * @throws \Exception
     */
    private function authenticateUser($provider): \Hybridauth\User\Profile
    {
        $hybridauth = $this->socialLoginHelper->getHybridauth();
        $hybridauth->authenticate($provider);

        $adapter = $hybridauth->getAdapter($provider);
        $userProfile = $adapter->getUserProfile();
        $adapter->disconnect();

        // Process Amazon display name
        if ($provider == 'Amazon' && $userProfile->displayName) {
            $nameParts = explode(' ', trim($userProfile->displayName ?? ''));
            $userProfile->firstName = $nameParts[0] ?? '';
            $userProfile->lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
        }
        return $userProfile;
    }

    /**
     * Load or create a new customer using the provided user profile.
     *
     * @param Profile $userProfile
     * @return \Magento\Customer\Api\Data\CustomerInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function loadOrCreateCustomer($userProfile): \Magento\Customer\Api\Data\CustomerInterface
    {
        try {
            return $this->customerRepository->get($userProfile->email);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $customer = $this->customerInterfaceFactory->create();
            $customer->setEmail($userProfile->email);
            $customer->setFirstname($userProfile->firstName);
            $customer->setLastname($userProfile->lastName);
            return $this->customerRepository->save($customer);
        }
    }

    /**
     * Log the customer in and manage session cookies.
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return void
     */
    private function logCustomerIn($customer): void
    {
        $this->customerSession->setCustomerDataAsLoggedIn($customer);
        if ($this->cookieMetadataManager->getCookie('mage-cache-sessid')) {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata();
            $metadata->setPath('/');
            $this->cookieMetadataManager->deleteCookie('mage-cache-sessid', $metadata);
        }
    }
}
