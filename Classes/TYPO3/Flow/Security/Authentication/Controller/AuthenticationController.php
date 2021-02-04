<?php
namespace TYPO3\Flow\Security\Authentication\Controller;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;

/**
 * An action controller for generic authentication in Flow
 *
 * @Flow\Scope("singleton")
 * @deprecated since 1.2 Instead you should inherit from the AbstractAuthenticationController from within your package
 */
class AuthenticationController extends AbstractAuthenticationController
{
    /**
     * The authentication manager
     * @var \TYPO3\Flow\Security\Authentication\AuthenticationManagerInterface
     * @Flow\Inject
     */
    protected $authenticationManager;

    /**
     * @var \TYPO3\Flow\Security\Context
     * @Flow\Inject
     */
    protected $securityContext;

    /**
     * Redirects to a potentially intercepted request. Returns an error message if there has been none.
     *
     * @param \TYPO3\Flow\Mvc\ActionRequest $originalRequest The request that was intercepted by the security framework, NULL if there was none
     * @return string
     */
    protected function onAuthenticationSuccess(\TYPO3\Flow\Mvc\ActionRequest $originalRequest = null)
    {
        if ($originalRequest !== null) {
            $this->redirectToRequest($originalRequest);
        }
        return 'There was no redirect implemented and no intercepted request could be found after authentication.
				Please implement onAuthenticationSuccess() in your login controller to handle this case correctly.
				If you have a template for the authenticate action, simply make sure that onAuthenticationSuccess()
				returns NULL in your login controller.';
    }
}
