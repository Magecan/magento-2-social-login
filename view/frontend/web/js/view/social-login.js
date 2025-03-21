/**
 * Copyright © Magecan, Inc. All rights reserved.
 */
define(['uiComponent'], function (Component) {
    return Component.extend({
        defaults: {
            template: 'Magecan_SocialLogin/social-login',
            providers: window.socialLoginProviders
        }
    });
});
