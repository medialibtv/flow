<?php
namespace TYPO3\Flow\Tests\Unit\Security\Authorization\Voter;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Testcase for the Policy voter
 *
 */
class PolicyTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @test
     */
    public function voteForJoinPointAbstainsIfNoPrivilegeWasConfigured()
    {
        $mockRoleAdministrator = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), 'role1' . md5(uniqid(mt_rand(), true)), false);
        $mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('ADMINISTRATOR'));

        $mockRoleCustomer = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), 'role2' . md5(uniqid(mt_rand(), true)), false);
        $mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('CUSTOMER'));

        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));
        $mockJoinPoint = $this->getMock('TYPO3\Flow\Aop\JoinPointInterface');

        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService');
        $mockPolicyService->expects($this->any())->method('getPrivilegesForJoinPoint')->will($this->returnValue(array()));

        $Policy = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($Policy->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_ABSTAIN, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForJoinPointAbstainsIfNoRolesAreAvailable()
    {
        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array()));

        $mockJoinPoint = $this->getMock('TYPO3\Flow\Aop\JoinPointInterface', array(), array(), '', false);
        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService', array(), array(), '', false);

        $Policy = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($Policy->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_ABSTAIN, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForJoinPointAbstainsIfNoPolicyEntryCouldBeFound()
    {
        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array(new \TYPO3\Flow\Security\Policy\Role('role1'))));

        $mockJoinPoint = $this->getMock('TYPO3\Flow\Aop\JoinPointInterface', array(), array(), '', false);
        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService', array(), array(), '', false);
        $mockPolicyService->expects($this->once())->method('getPrivilegesForJoinPoint')->will($this->throwException(new \TYPO3\Flow\Security\Exception\NoEntryInPolicyException()));

        $voter = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($voter->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_ABSTAIN, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForJoinPointDeniesAccessIfADenyPrivilegeWasConfiguredForOneOfTheRoles()
    {
        $role1ClassName = 'role1' . md5(uniqid(mt_rand(), true));
        $role2ClassName = 'role2' . md5(uniqid(mt_rand(), true));

        $mockRoleAdministrator = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role1ClassName, false);
        $mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('Administrator'));

        $mockRoleCustomer = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role2ClassName, false);
        $mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('Customer'));

        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));
        $mockJoinPoint = $this->getMock('TYPO3\Flow\Aop\JoinPointInterface');

        $getPrivilegesCallback = function () use (&$role1ClassName) {
            $args = func_get_args();
            if ($args[0] instanceof $role1ClassName) {
                return array(\TYPO3\Flow\Security\Policy\PolicyService::PRIVILEGE_DENY);
            } else {
                return array(\TYPO3\Flow\Security\Policy\PolicyService::PRIVILEGE_GRANT);
            }
        };

        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService');
        $mockPolicyService->expects($this->any())->method('getPrivilegesForJoinPoint')->will($this->returnCallback($getPrivilegesCallback));

        $Policy = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($Policy->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_DENY, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForJoinPointGrantsAccessIfAGrantPrivilegeAndNoDenyPrivilegeWasConfigured()
    {
        $role1ClassName = 'role1' . md5(uniqid(mt_rand(), true));
        $role2ClassName = 'role2' . md5(uniqid(mt_rand(), true));

        $mockRoleAdministrator = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role1ClassName, false);
        $mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('Administrator'));

        $mockRoleCustomer = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role2ClassName, false);
        $mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('Customer'));

        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));
        $mockJoinPoint = $this->getMock('TYPO3\Flow\Aop\JoinPointInterface');

        $getPrivilegesCallback = function () use (&$role1ClassName) {
            $args = func_get_args();
            if ($args[0] instanceof $role1ClassName) {
                return array(\TYPO3\Flow\Security\Policy\PolicyService::PRIVILEGE_GRANT);
            } else {
                return array();
            }
        };

        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService');
        $mockPolicyService->expects($this->any())->method('getPrivilegesForJoinPoint')->will($this->returnCallback($getPrivilegesCallback));

        $Policy = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($Policy->voteForJoinPoint($mockSecurityContext, $mockJoinPoint), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_GRANT, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForResourceAbstainsIfNoRolesAreAvailable()
    {
        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array()));

        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService', array(), array(), '', false);

        $voter = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($voter->voteForResource($mockSecurityContext, 'myResource'), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_ABSTAIN, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForResourceAbstainsIfNoPolicyEntryCouldBeFound()
    {
        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array(new \TYPO3\Flow\Security\Policy\Role('role1'))));

        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService', array(), array(), '', false);
        $mockPolicyService->expects($this->once())->method('getPrivilegeForResource')->will($this->throwException(new \TYPO3\Flow\Security\Exception\NoEntryInPolicyException()));

        $voter = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($voter->voteForResource($mockSecurityContext, 'myResource'), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_ABSTAIN, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForResourceDeniesAccessIfADenyPrivilegeWasConfiguredForOneOfTheRoles()
    {
        $role1ClassName = 'role1' . md5(uniqid(mt_rand(), true));
        $role2ClassName = 'role2' . md5(uniqid(mt_rand(), true));

        $mockRoleAdministrator = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role1ClassName, false);
        $mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('ADMINISTRATOR'));

        $mockRoleCustomer = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role2ClassName, false);
        $mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('CUSTOMER'));

        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));

        $getPrivilegeCallback = function () use (&$role1ClassName) {
            $args = func_get_args();
            if ($args[0] instanceof $role1ClassName) {
                return \TYPO3\Flow\Security\Policy\PolicyService::PRIVILEGE_DENY;
            } else {
                return null;
            }
        };

        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService');
        $mockPolicyService->expects($this->any())->method('getPrivilegeForResource')->will($this->returnCallback($getPrivilegeCallback));

        $Policy = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($Policy->voteForResource($mockSecurityContext, 'myResource'), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_DENY, 'The wrong vote was returned!');
    }

    /**
     * @test
     */
    public function voteForResourceGrantsAccessIfAGrantPrivilegeAndNoDenyPrivilegeWasConfigured()
    {
        $role1ClassName = 'role1' . md5(uniqid(mt_rand(), true));
        $role2ClassName = 'role2' . md5(uniqid(mt_rand(), true));

        $mockRoleAdministrator = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role1ClassName, false);
        $mockRoleAdministrator->expects($this->any())->method('__toString')->will($this->returnValue('Administrator'));

        $mockRoleCustomer = $this->getMock('TYPO3\Flow\Security\Policy\Role', array(), array(), $role2ClassName, false);
        $mockRoleCustomer->expects($this->any())->method('__toString')->will($this->returnValue('Customer'));

        $mockSecurityContext = $this->getMock('TYPO3\Flow\Security\Context', array(), array(), '', false);
        $mockSecurityContext->expects($this->once())->method('getRoles')->will($this->returnValue(array($mockRoleAdministrator, $mockRoleCustomer)));

        $getPrivilegesCallback = function () use (&$role1ClassName) {
            $args = func_get_args();
            if ($args[0] instanceof $role1ClassName) {
                return \TYPO3\Flow\Security\Policy\PolicyService::PRIVILEGE_GRANT;
            } else {
                return null;
            }
        };

        $mockPolicyService = $this->getMock('TYPO3\Flow\Security\Policy\PolicyService');
        $mockPolicyService->expects($this->any())->method('getPrivilegeForResource')->will($this->returnCallback($getPrivilegesCallback));

        $Policy = new \TYPO3\Flow\Security\Authorization\Voter\Policy($mockPolicyService);
        $this->assertEquals($Policy->voteForResource($mockSecurityContext, 'myResource'), \TYPO3\Flow\Security\Authorization\Voter\Policy::VOTE_GRANT, 'The wrong vote was returned!');
    }
}
