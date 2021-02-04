<?php
namespace TYPO3\Flow\Tests\Unit\Security\Policy;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\Flow\Security\Policy\Role;

/**
 * Testcase for for the policy service
 */
class RoleTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * data provider
     *
     * @return array
     */
    public function roleIdentifiersAndPackageKeysAndNames()
    {
        return array(
            array('Everybody', 'Everybody', null),
            array('Acme.Demo:Test', 'Test', 'Acme.Demo'),
            array('Acme.Demo.Sub:Test', 'Test', 'Acme.Demo.Sub')
        );
    }

    /**
     * @dataProvider roleIdentifiersAndPackageKeysAndNames
     * @test
     */
    public function setNameAndPackageKeyWorks($roleIdentifier, $name, $packageKey)
    {
        $role = new Role($roleIdentifier);
        $role->initializeObject();

        $this->assertEquals($name, $role->getName());
        $this->assertEquals($packageKey, $role->getPackageKey());
    }

    /**
     * @test
     */
    public function setParentRolesMakesSureThatParentRolesDontContainDuplicates()
    {
        $role = new Role('Acme.Demo:Test');
        $role->initializeObject();

        $parentRole1 = new Role('Acme.Demo:Parent1');
        $parentRole2 = new Role('Acme.Demo:Parent2');

        $parentRole2->addParentRole($parentRole1);

        $role->setParentRoles(array($parentRole1, $parentRole2, $parentRole2, $parentRole1));

        $expectedParentRoles = array(
            'Acme.Demo:Parent1' => $parentRole1,
            'Acme.Demo:Parent2' => $parentRole2
        );

        // Internally, parentRoles might contain duplicates which Doctrine will try
        // to persist - even though getParentRoles() will return an array which
        // does not contain duplicates:
        $internalParentRolesCollection = ObjectAccess::getProperty($role, 'parentRoles', true);
        $this->assertEquals(2, count($internalParentRolesCollection->toArray()));

        $this->assertEquals($expectedParentRoles, $role->getParentRoles());
    }
}
