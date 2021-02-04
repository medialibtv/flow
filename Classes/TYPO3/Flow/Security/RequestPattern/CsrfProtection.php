<?php
namespace TYPO3\Flow\Security\RequestPattern;

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
use TYPO3\Flow\Mvc\ActionRequest;
use TYPO3\Flow\Security\RequestPatternInterface;

/**
 * This class holds a request pattern that decides, if csrf protection was enabled for the current request and searches
 * for invalid csrf protection tokens.
 */
class CsrfProtection implements RequestPatternInterface
{
    /**
     * @var \TYPO3\Flow\Security\Context
     * @Flow\Inject
     */
    protected $securityContext;

    /**
     * @var \TYPO3\Flow\Security\Authentication\AuthenticationManagerInterface
     * @Flow\Inject
     */
    protected $authenticationManager;

    /**
     * @var \TYPO3\Flow\Object\ObjectManagerInterface
     * @Flow\Inject
     */
    protected $objectManager;

    /**
     * @var \TYPO3\Flow\Reflection\ReflectionService
     * @Flow\Inject
     */
    protected $reflectionService;

    /**
     * @var \TYPO3\Flow\Security\Policy\PolicyService
     * @Flow\Inject
     */
    protected $policyService;

    /**
     * @var \TYPO3\Flow\Log\SystemLoggerInterface
     * @Flow\Inject
     */
    protected $systemLogger;

    /**
     * NULL: This pattern holds no configured pattern value
     *
     * @return string The set pattern (always NULL here)
     */
    public function getPattern()
    {
    }

    /**
     * Does nothing, as this pattern holds no configured pattern value
     *
     * @param string $pattern Not used
     * @return void
     */
    public function setPattern($pattern)
    {
    }

    /**
     * Matches a \TYPO3\Flow\Mvc\RequestInterface against the configured CSRF pattern rules and
     * searches for invalid csrf tokens. If this returns TRUE, the request is invalid!
     *
     * @param \TYPO3\Flow\Mvc\RequestInterface $request The request that should be matched
     * @return boolean TRUE if the pattern matched, FALSE otherwise
     * @throws \TYPO3\Flow\Security\Exception\AuthenticationRequiredException
     */
    public function matchRequest(\TYPO3\Flow\Mvc\RequestInterface $request)
    {
        if (!$request instanceof ActionRequest || $request->getHttpRequest()->isMethodSafe()) {
            $this->systemLogger->log('No CSRF required, safe request', LOG_DEBUG);
            return false;
        }
        if ($this->authenticationManager->isAuthenticated() === false) {
            $this->systemLogger->log('No CSRF required, not authenticated', LOG_DEBUG);
            return false;
        }
        if ($this->securityContext->areAuthorizationChecksDisabled() === true) {
            $this->systemLogger->log('No CSRF required, authorization checks are disabled', LOG_DEBUG);
            return false;
        }

        $controllerClassName = $this->objectManager->getClassNameByObjectName($request->getControllerObjectName());
        $actionName = $request->getControllerActionName() . 'Action';
        if (!$this->policyService->hasPolicyEntryForMethod($controllerClassName, $actionName)) {
            $this->systemLogger->log(sprintf('CSRF protection filter: allowed %s request without requiring CSRF token because action "%s" in controller "%s" is not restricted by a policy.', $request->getHttpRequest()->getMethod(), $actionName, $controllerClassName), LOG_NOTICE);
            return false;
        }
        if ($this->reflectionService->isMethodTaggedWith($controllerClassName, $actionName, 'skipcsrfprotection')) {
            return false;
        }

        $httpRequest = $request->getHttpRequest();
        if ($httpRequest->hasHeader('X-Flow-Csrftoken')) {
            $csrfToken = $httpRequest->getHeader('X-Flow-Csrftoken');
        } else {
            $internalArguments = $request->getMainRequest()->getInternalArguments();
            $csrfToken = isset($internalArguments['__csrfToken']) ? $internalArguments['__csrfToken'] : null;
        }

        if (empty($csrfToken)) {
            $this->systemLogger->log('CSRF token was empty', LOG_DEBUG);
            return true;
        }

        if (!$this->securityContext->hasCsrfProtectionTokens()) {
            throw new \TYPO3\Flow\Security\Exception\AuthenticationRequiredException('No tokens in security context, possible session timeout', 1317309673);
        }

        if ($this->securityContext->isCsrfProtectionTokenValid($csrfToken) === false) {
            $this->systemLogger->log('CSRF token was invalid', LOG_DEBUG);
            return true;
        }

        // the CSRF token was necessary and is valid
        return false;
    }
}
