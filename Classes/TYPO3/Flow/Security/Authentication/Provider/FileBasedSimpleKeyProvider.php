<?php
namespace TYPO3\Flow\Security\Authentication\Provider;

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
use TYPO3\Flow\Security\Policy\Role;

/**
 * An authentication provider that authenticates
 * TYPO3\Flow\Security\Authentication\Token\PasswordToken tokens.
 * The passwords are stored as encrypted files in persisted data and
 * are fetched using the file based simple key service.
 *
 * The roles set in authenticateRoles will be added to the authenticated
 * token, but will not be persisted in the database as this provider is
 * used for situations in which no database connection might be present.
 *
 * = Example =
 *
 * TYPO3:
 *   Flow:
 *     security:
 *       authentication:
 *         providers:
 *           AdminInterfaceProvider:
 *             provider: FileBasedSimpleKeyProvider
 *             providerOptions:
 *               keyName: AdminKey
 *               authenticateRoles: ['TYPO3.Flow.SomeRole']
 */
class FileBasedSimpleKeyProvider extends \TYPO3\Flow\Security\Authentication\Provider\AbstractProvider
{
    /**
     * @var \TYPO3\Flow\Security\Cryptography\HashService
     * @Flow\Inject
     */
    protected $hashService;

    /**
     * @var \TYPO3\Flow\Security\Cryptography\FileBasedSimpleKeyService
     * @Flow\Inject
     */
    protected $fileBasedSimpleKeyService;

    /**
     * @var \TYPO3\Flow\Security\Policy\PolicyService
     * @Flow\Inject
     */
    protected $policyService;

    /**
     * Returns the class names of the tokens this provider can authenticate.
     *
     * @return array
     */
    public function getTokenClassNames()
    {
        return array('TYPO3\Flow\Security\Authentication\Token\PasswordToken');
    }

    /**
     * Sets isAuthenticated to TRUE for all tokens.
     *
     * @param \TYPO3\Flow\Security\Authentication\TokenInterface $authenticationToken The token to be authenticated
     * @return void
     * @throws \TYPO3\Flow\Security\Exception\UnsupportedAuthenticationTokenException
     */
    public function authenticate(\TYPO3\Flow\Security\Authentication\TokenInterface $authenticationToken)
    {
        if (!($authenticationToken instanceof \TYPO3\Flow\Security\Authentication\Token\PasswordToken)) {
            throw new \TYPO3\Flow\Security\Exception\UnsupportedAuthenticationTokenException('This provider cannot authenticate the given token.', 1217339840);
        }

        $credentials = $authenticationToken->getCredentials();
        if (is_array($credentials) && isset($credentials['password'])) {
            if ($this->hashService->validatePassword($credentials['password'], $this->fileBasedSimpleKeyService->getKey($this->options['keyName']))) {
                $authenticationToken->setAuthenticationStatus(\TYPO3\Flow\Security\Authentication\TokenInterface::AUTHENTICATION_SUCCESSFUL);
                $account = new \TYPO3\Flow\Security\Account();
                $roles = array();
                foreach ($this->options['authenticateRoles'] as $roleIdentifier) {
                    $roles[] = new Role($roleIdentifier, Role::SOURCE_SYSTEM);
                }
                $account->setRoles($roles);
                $authenticationToken->setAccount($account);
            } else {
                $authenticationToken->setAuthenticationStatus(\TYPO3\Flow\Security\Authentication\TokenInterface::WRONG_CREDENTIALS);
            }
        } elseif ($authenticationToken->getAuthenticationStatus() !== \TYPO3\Flow\Security\Authentication\TokenInterface::AUTHENTICATION_SUCCESSFUL) {
            $authenticationToken->setAuthenticationStatus(\TYPO3\Flow\Security\Authentication\TokenInterface::NO_CREDENTIALS_GIVEN);
        }
    }
}
