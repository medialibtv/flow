<?php
namespace TYPO3\Flow\Tests\Functional\Property;

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
 * Testcase for Property Mapper
 *
 */
class PropertyMapperTest extends \TYPO3\Flow\Tests\FunctionalTestCase
{
    /**
     *
     * @var \TYPO3\Flow\Property\PropertyMapper
     */
    protected $propertyMapper;

    protected static $testablePersistenceEnabled = true;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->propertyMapper = $this->objectManager->get('TYPO3\Flow\Property\PropertyMapper');
    }

    /**
     * @test
     */
    public function domainObjectWithSimplePropertiesCanBeCreated()
    {
        $source = array(
            'name' => 'Robert Skaarhoj',
            'age' => '25',
            'averageNumberOfKids' => '1.5'
        );

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity');
        $this->assertSame('Robert Skaarhoj', $result->getName());
        $this->assertSame(25, $result->getAge());
        $this->assertSame(1.5, $result->getAverageNumberOfKids());
    }

    /**
     * @test
     */
    public function domainObjectWithVirtualPropertiesCanBeCreated()
    {
        $source = array(
            'name' => 'Robert Skaarhoj',
            'yearOfBirth' => '1988',
            'averageNumberOfKids' => '1.5'
        );

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity');
        $this->assertSame('Robert Skaarhoj', $result->getName());
        $this->assertSame(25, $result->getAge());
        $this->assertSame(1.5, $result->getAverageNumberOfKids());
    }

    /**
     * @test
     */
    public function simpleObjectWithSimplePropertiesCanBeCreated()
    {
        $source = array(
            'name' => 'Christopher',
            'size' => '187',
            'signedCla' => true
        );

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestClass');
        $this->assertSame('Christopher', $result->getName());
        $this->assertSame(187, $result->getSize());
        $this->assertSame(true, $result->getSignedCla());
    }

    /**
     * @test
     */
    public function valueobjectCanBeMapped()
    {
        $source = array(
            '__identity' => 'abcdefghijkl',
            'name' => 'Christopher',
            'age' => '28'
        );

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestValueobject');
        $this->assertSame('Christopher', $result->getName());
        $this->assertSame(28, $result->getAge());
    }

    /**
     * @test
     */
    public function integerCanBeMappedToString()
    {
        $source = array(
            'name' => 42,
            'size' => 23
        );

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestClass');
        $this->assertSame('42', $result->getName());
        $this->assertSame(23, $result->getSize());
    }

    /**
     * @test
     */
    public function targetTypeForEntityCanBeOverridenIfConfigured()
    {
        $source = array(
            '__type' => 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntitySubclass',
            'name' => 'Arthur',
            'age' => '42'
        );

        $configuration = $this->objectManager->get('TYPO3\Flow\Property\PropertyMappingConfigurationBuilder')->build();
        $configuration->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter', \TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED, true);

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity', $configuration);
        $this->assertInstanceOf('\TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntitySubclass', $result);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception
     */
    public function overridenTargetTypeForEntityMustBeASubclass()
    {
        $source = array(
            '__type' => 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestClass',
            'name' => 'A horse'
        );

        $configuration = $this->objectManager->get('TYPO3\Flow\Property\PropertyMappingConfigurationBuilder')->build();
        $configuration->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter', \TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED, true);

        $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity', $configuration);
    }

    /**
     * @test
     */
    public function targetTypeForSimpleObjectCanBeOverridenIfConfigured()
    {
        $source = array(
            '__type' => 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestSubclass',
            'name' => 'Tower of Pisa'
        );

        $configuration = $this->objectManager->get('TYPO3\Flow\Property\PropertyMappingConfigurationBuilder')->build();
        $configuration->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\ObjectConverter', \TYPO3\Flow\Property\TypeConverter\ObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED, true);

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestClass', $configuration);
        $this->assertInstanceOf('TYPO3\Flow\Tests\Functional\Property\Fixtures\TestSubclass', $result);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception
     */
    public function overridenTargetTypeForSimpleObjectMustBeASubclass()
    {
        $source = array(
            '__type' => 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity',
            'name' => 'A horse'
        );

        $configuration = $this->objectManager->get('TYPO3\Flow\Property\PropertyMappingConfigurationBuilder')->build();
        $configuration->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\ObjectConverter', \TYPO3\Flow\Property\TypeConverter\ObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED, true);

        $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestClass', $configuration);
    }

    /**
     * @test
     */
    public function mappingPersistentEntityOnlyChangesModifiedProperties()
    {
        $entityIdentity = $this->createTestEntity();

        $source = array(
            '__identity' => $entityIdentity,
            'averageNumberOfKids' => '5.5'
        );

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity');
        $this->assertSame('Egon Olsen', $result->getName());
        $this->assertSame(42, $result->getAge());
        $this->assertSame(5.5, $result->getAverageNumberOfKids());
    }

    /**
     * @test
     */
    public function mappingPersistentEntityAllowsToSetValueToNull()
    {
        $entityIdentity = $this->createTestEntity();

        $source = array(
            '__identity' => $entityIdentity,
            'averageNumberOfKids' => ''
        );

        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity');
        $this->assertSame('Egon Olsen', $result->getName());
        $this->assertSame(42, $result->getAge());
        $this->assertSame(null, $result->getAverageNumberOfKids());
    }

    /**
     * @test
     */
    public function mappingOfPropertiesWithUnqualifiedInterfaceName()
    {
        $relatedEntity = new Fixtures\TestEntity();

        $source = array(
            'relatedEntity' => $relatedEntity,
        );
        $result = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity');
        $this->assertSame($relatedEntity, $result->getRelatedEntity());
    }

    /**
     * Testcase for http://forge.typo3.org/issues/36988 - needed for Neos
     * editing
     *
     * @test
     */
    public function ifTargetObjectTypeIsPassedAsArgumentDoNotConvertIt()
    {
        $entity = new Fixtures\TestEntity();
        $entity->setName('Egon Olsen');

        $result = $this->propertyMapper->convert($entity, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity');
        $this->assertSame($entity, $result);
    }

    /**
     * Testcase for http://forge.typo3.org/issues/39445
     *
     * @test
     */
    public function ifTargetObjectTypeIsPassedRecursivelyDoNotConvertIt()
    {
        $entity = new Fixtures\TestEntity();
        $entity->setName('Egon Olsen');

        $result = $this->propertyMapper->convert(array($entity), 'array<TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity>');
        $this->assertSame(array($entity), $result);
    }

    /**
     * Add and persist a test entity, and return the identifier of the newly created
     * entity.
     *
     * @return string identifier of newly created entity
     */
    protected function createTestEntity()
    {
        $entity = new Fixtures\TestEntity();
        $entity->setName('Egon Olsen');
        $entity->setAge(42);
        $entity->setAverageNumberOfKids(3.5);
        $this->persistenceManager->add($entity);
        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);

        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        return $entityIdentifier;
    }

    /**
     * Testcase for #32829
     *
     * @test
     */
    public function mappingToFieldsFromSubclassWorksIfTargetTypeIsOverridden()
    {
        $source = array(
            '__type' => 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntitySubclassWithNewField',
            'testField' => 'A horse'
        );

        $configuration = $this->objectManager->get('TYPO3\Flow\Property\PropertyMappingConfigurationBuilder')->build();
        $configuration->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter', \TYPO3\Flow\Property\TypeConverter\ObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED, true);

        $theHorse = $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity', $configuration);
        $this->assertInstanceOf('TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntitySubclassWithNewField', $theHorse);
    }

    /**
     * @test
     * @dataProvider invalidTypeConverterConfigurationsForOverridingTargetTypes
     * @expectedException \TYPO3\Flow\Property\Exception
     */
    public function mappingToFieldsFromSubclassThrowsExceptionIfTypeConverterOptionIsInvalidOrNotSet(\TYPO3\Flow\Property\PropertyMappingConfigurationInterface $configuration = null)
    {
        $source = array(
            '__type' => 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntitySubclassWithNewField',
            'testField' => 'A horse'
        );

        $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity', $configuration);
    }

    /**
     * Data provider with invalid configuration for target type overrides
     *
     * @return array
     */
    public function invalidTypeConverterConfigurationsForOverridingTargetTypes()
    {
        $configurationWithNoSetting = new \TYPO3\Flow\Property\PropertyMappingConfiguration();

        $configurationWithOverrideOff = new \TYPO3\Flow\Property\PropertyMappingConfiguration();
        $configurationWithOverrideOff->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\ObjectConverter', \TYPO3\Flow\Property\TypeConverter\ObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED, false);

        return array(
            array(null),
            array($configurationWithNoSetting),
            array($configurationWithOverrideOff),
        );
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception
     */
    public function convertFromShouldThrowExceptionIfGivenSourceTypeIsNotATargetType()
    {
        $source = array(
            '__type' => 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestClass',
            'testField' => 'A horse'
        );

        $configuration = $this->objectManager->get('TYPO3\Flow\Property\PropertyMappingConfigurationBuilder')->build();
        $configuration->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter', \TYPO3\Flow\Property\TypeConverter\ObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED, true);

        $this->propertyMapper->convert($source, 'TYPO3\Flow\Tests\Functional\Property\Fixtures\TestEntity', $configuration);
    }

    /**
     * Test case for #47232
     *
     * @test
     */
    public function convertedAccountRolesCanBeSet()
    {
        $source = array(
            'accountIdentifier' => 'someAccountIdentifier',
            'credentialsSource' => 'someEncryptedStuff',
            'authenticationProviderName' => 'DefaultProvider',
            'roles' => array('Anonymous', 'Some.Package:Customer', 'Some.Package:Administrator')
        );

        $roles = array('Anonymous', 'Some.Package:Customer', 'Some.Package:Administrator');

        foreach ($roles as $role) {
            $this->persistenceManager->add(new \TYPO3\Flow\Security\Policy\Role($role));
        }

        $configuration = $this->objectManager->get('TYPO3\Flow\Property\PropertyMappingConfigurationBuilder')->build();
        $configuration->forProperty('roles.*')->allowProperties();

        $account = $this->propertyMapper->convert($source, 'TYPO3\Flow\Security\Account', $configuration);

        $this->assertInstanceOf('\TYPO3\Flow\Security\Account', $account);
        $this->assertEquals(3, count($account->getRoles()));
        $this->assertEquals($roles, array_keys($account->getRoles()));
    }

    /**
     * @test
     */
    public function persistentEntityCanBeSerializedToIdentifierUsingObjectSource()
    {
        $entity = new Fixtures\TestEntity();
        $entity->setName('Egon Olsen');
        $entity->setAge(42);
        $entity->setAverageNumberOfKids(3.5);
        $this->persistenceManager->add($entity);

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);

        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $source = $entity;

        $result = $this->propertyMapper->convert($source, 'string');

        $this->assertSame($entityIdentifier, $result);
    }
}
