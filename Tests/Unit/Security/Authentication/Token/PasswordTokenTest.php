<?php
namespace TYPO3\Flow\Tests\Unit\Security\Authentication\Token;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Mvc\ActionRequest;
use TYPO3\Flow\Security\Authentication\TokenInterface;
use TYPO3\Flow\Security\Authentication\Token\PasswordToken;
use TYPO3\Flow\Tests\UnitTestCase;

/**
 * Testcase for password authentication token
 */
class PasswordTokenTest extends UnitTestCase
{
    /**
     * @var PasswordToken
     */
    protected $token;

    /**
     * @var ActionRequest
     */
    protected $mockActionRequest;

    /**
     * @var Request
     */
    protected $mockHttpRequest;

    /**
     * Set up this test case
     */
    public function setUp()
    {
        $this->token = new PasswordToken();

        $this->mockActionRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();

        $this->mockHttpRequest = $this->getMockBuilder('TYPO3\Flow\Http\Request')->disableOriginalConstructor()->getMock();
        $this->mockActionRequest->expects($this->any())->method('getHttpRequest')->will($this->returnValue($this->mockHttpRequest));
    }

    /**
     * @test
     */
    public function credentialsAreSetCorrectlyFromPostArguments()
    {
        $arguments = array();
        $arguments['__authentication']['TYPO3']['Flow']['Security']['Authentication']['Token']['PasswordToken']['password'] = 'verysecurepassword';

        $this->mockHttpRequest->expects($this->atLeastOnce())->method('getMethod')->will($this->returnValue('POST'));
        $this->mockActionRequest->expects($this->atLeastOnce())->method('getInternalArguments')->will($this->returnValue($arguments));

        $this->token->updateCredentials($this->mockActionRequest);

        $expectedCredentials = array('password' => 'verysecurepassword');
        $this->assertEquals($expectedCredentials, $this->token->getCredentials(), 'The credentials have not been extracted correctly from the POST arguments');
    }

    /**
     * @test
     */
    public function updateCredentialsSetsTheCorrectAuthenticationStatusIfNewCredentialsArrived()
    {
        $arguments = array();
        $arguments['__authentication']['TYPO3']['Flow']['Security']['Authentication']['Token']['PasswordToken']['password'] = 'verysecurepassword';

        $this->mockHttpRequest->expects($this->atLeastOnce())->method('getMethod')->will($this->returnValue('POST'));
        $this->mockActionRequest->expects($this->atLeastOnce())->method('getInternalArguments')->will($this->returnValue($arguments));

        $this->token->updateCredentials($this->mockActionRequest);

        $this->assertSame(TokenInterface::AUTHENTICATION_NEEDED, $this->token->getAuthenticationStatus());
    }

    /**
     * @test
     */
    public function updateCredentialsIgnoresAnythingOtherThanPostRequests()
    {
        $arguments = array();
        $arguments['__authentication']['TYPO3']['Flow']['Security']['Authentication']['Token']['PasswordToken']['password'] = 'verysecurepassword';

        $this->mockHttpRequest->expects($this->atLeastOnce())->method('getMethod')->will($this->returnValue('POST'));
        $this->mockActionRequest->expects($this->atLeastOnce())->method('getInternalArguments')->will($this->returnValue($arguments));

        $this->token->updateCredentials($this->mockActionRequest);
        $this->assertEquals(array('password' => 'verysecurepassword'), $this->token->getCredentials());

        $secondToken = new PasswordToken();
        $secondMockActionRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();

        $secondMockHttpRequest = $this->getMockBuilder('TYPO3\Flow\Http\Request')->disableOriginalConstructor()->getMock();
        $secondMockActionRequest->expects($this->any())->method('getHttpRequest')->will($this->returnValue($secondMockHttpRequest));
        $secondMockHttpRequest->expects($this->atLeastOnce())->method('getMethod')->will($this->returnValue('GET'));
        $secondToken->updateCredentials($secondMockActionRequest);
        $this->assertEquals(array('password' => ''), $secondToken->getCredentials());
    }
}
