<?php
namespace TYPO3\Flow\Tests\Unit\Security\Authentication\Provider;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Security\Authentication\Provider\FileBasedSimpleKeyProvider;

/**
 * Testcase for file based simple key authentication provider.
 *
 */
class FileBasedSimpleKeyProviderTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @var string
     */
    protected $testKeyClearText = 'password';

    /**
     * @var string
     */
    protected $testKeyHashed = 'pbkdf2=>DPIFYou4eD8=,nMRkJ9708Ryq3zIZcCLQrBiLQ0ktNfG8tVRJoKPTGcG/6N+tyzQHObfH5y5HCra1hAVTBrbgfMjPU6BipIe9xg==%';

    /**
     * @test
     */
    public function authenticatingAPasswordTokenChecksIfTheGivenClearTextPasswordMatchesThePersistedHashedPassword()
    {
        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService');

        $mockHashService = $this->getMock('TYPO3\Flow\Security\Cryptography\HashService');
        $mockHashService->expects($this->once())->method('validatePassword')->with($this->testKeyClearText, $this->testKeyHashed)->will($this->returnValue(true));

        $mockFileBasedSimpleKeyService = $this->getMock('TYPO3\Flow\Security\Cryptography\FileBasedSimpleKeyService');
        $mockFileBasedSimpleKeyService->expects($this->once())->method('getKey')->with('testKey')->will($this->returnValue($this->testKeyHashed));

        $mockToken = $this->getMock('TYPO3\Flow\Security\Authentication\Token\PasswordToken', array(), array(), '', false);
        $mockToken->expects($this->once())->method('getCredentials')->will($this->returnValue(array('password' => $this->testKeyClearText)));
        $mockToken->expects($this->once())->method('setAuthenticationStatus')->with(\TYPO3\Flow\Security\Authentication\TokenInterface::AUTHENTICATION_SUCCESSFUL);

        $authenticationProvider = $this->getAccessibleMock('TYPO3\Flow\Security\Authentication\Provider\FileBasedSimpleKeyProvider', array('dummy'), array('myProvider', array('keyName' => 'testKey', 'authenticateRoles' => array('TestRoleIdentifier'))));
        $authenticationProvider->_set('hashService', $mockHashService);
        $authenticationProvider->_set('fileBasedSimpleKeyService', $mockFileBasedSimpleKeyService);
        $authenticationProvider->_set('policyService', $mockPolicyService);

        $authenticationProvider->authenticate($mockToken);
    }

    /**
     * @test
     */
    public function authenticationAddsAnAccountHoldingTheConfiguredRoles()
    {
        $mockHashService = $this->getMock('TYPO3\Flow\Security\Cryptography\HashService');
        $mockHashService->expects($this->once())->method('validatePassword')->will($this->returnValue(true));

        $mockFileBasedSimpleKeyService = $this->getMock('TYPO3\Flow\Security\Cryptography\FileBasedSimpleKeyService');

        $mockToken = $this->getMock('TYPO3\Flow\Security\Authentication\Token\PasswordToken', array('dummy'), array(), '', false);

        $authenticationProvider = $this->getAccessibleMock('TYPO3\Flow\Security\Authentication\Provider\FileBasedSimpleKeyProvider', array('dummy'), array('myProvider', array('keyName' => 'testKey', 'authenticateRoles' => array('TestRoleIdentifier'))));
        $authenticationProvider->_set('hashService', $mockHashService);
        $authenticationProvider->_set('fileBasedSimpleKeyService', $mockFileBasedSimpleKeyService);

        $authenticationProvider->authenticate($mockToken);

        $authenticatedRoles = $mockToken->getAccount()->getRoles();
        $this->assertTrue(in_array('TestRoleIdentifier', array_keys($authenticatedRoles)));
    }

    /**
     * @test
     */
    public function authenticationFailsWithWrongCredentialsInAPasswordToken()
    {
        $mockHashService = $this->getMock('TYPO3\Flow\Security\Cryptography\HashService');
        $mockHashService->expects($this->once())->method('validatePassword')->with('wrong password', $this->testKeyHashed)->will($this->returnValue(false));

        $mockFileBasedSimpleKeyService = $this->getMock('TYPO3\Flow\Security\Cryptography\FileBasedSimpleKeyService');
        $mockFileBasedSimpleKeyService->expects($this->once())->method('getKey')->with('testKey')->will($this->returnValue($this->testKeyHashed));

        $mockToken = $this->getMock('TYPO3\Flow\Security\Authentication\Token\PasswordToken', array(), array(), '', false);
        $mockToken->expects($this->once())->method('getCredentials')->will($this->returnValue(array('password' => 'wrong password')));
        $mockToken->expects($this->once())->method('setAuthenticationStatus')->with(\TYPO3\Flow\Security\Authentication\TokenInterface::WRONG_CREDENTIALS);

        $authenticationProvider = $this->getAccessibleMock('TYPO3\Flow\Security\Authentication\Provider\FileBasedSimpleKeyProvider', array('dummy'), array('myProvider', array('keyName' => 'testKey')));
        $authenticationProvider->_set('hashService', $mockHashService);
        $authenticationProvider->_set('fileBasedSimpleKeyService', $mockFileBasedSimpleKeyService);

        $authenticationProvider->authenticate($mockToken);
    }

    /**
     * @test
     */
    public function authenticationIsSkippedIfNoCredentialsInAPasswordToken()
    {
        $mockToken = $this->getMock('TYPO3\Flow\Security\Authentication\Token\PasswordToken', array(), array(), '', false);
        $mockToken->expects($this->once())->method('getCredentials')->will($this->returnValue(array()));
        $mockToken->expects($this->once())->method('setAuthenticationStatus')->with(\TYPO3\Flow\Security\Authentication\TokenInterface::NO_CREDENTIALS_GIVEN);

        $authenticationProvider = $this->getAccessibleMock('TYPO3\Flow\Security\Authentication\Provider\FileBasedSimpleKeyProvider', array('dummy'), array('myProvider'));

        $authenticationProvider->authenticate($mockToken);
    }

    /**
     * @test
     */
    public function getTokenClassNameReturnsCorrectClassNames()
    {
        $authenticationProvider = new FileBasedSimpleKeyProvider('myProvider');
        $this->assertSame($authenticationProvider->getTokenClassNames(), array('TYPO3\Flow\Security\Authentication\Token\PasswordToken'));
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Security\Exception\UnsupportedAuthenticationTokenException
     */
    public function authenticatingAnUnsupportedTokenThrowsAnException()
    {
        $someNiceToken = $this->getMock('TYPO3\Flow\Security\Authentication\TokenInterface');

        $authenticationProvider = new FileBasedSimpleKeyProvider('myProvider');

        $authenticationProvider->authenticate($someNiceToken);
    }

    /**
     * @test
     */
    public function canAuthenticateReturnsTrueOnlyForAnTokenThatHasTheCorrectProviderNameSet()
    {
        $mockToken1 = $this->getMock('TYPO3\Flow\Security\Authentication\TokenInterface');
        $mockToken1->expects($this->once())->method('getAuthenticationProviderName')->will($this->returnValue('myProvider'));
        $mockToken2 = $this->getMock('TYPO3\Flow\Security\Authentication\TokenInterface');
        $mockToken2->expects($this->once())->method('getAuthenticationProviderName')->will($this->returnValue('someOtherProvider'));

        $authenticationProvider = new FileBasedSimpleKeyProvider('myProvider');

        $this->assertTrue($authenticationProvider->canAuthenticate($mockToken1));
        $this->assertFalse($authenticationProvider->canAuthenticate($mockToken2));
    }
}
