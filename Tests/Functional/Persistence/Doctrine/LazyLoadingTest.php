<?php
namespace TYPO3\Flow\Tests\Functional\Persistence\Doctrine;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Tests\Functional\Persistence\Fixtures\TestEntity;

/**
 * Testcase for proxy initialization within doctrine lazy loading
 */
class LazyLoadingTest extends \TYPO3\Flow\Tests\FunctionalTestCase
{
    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * @var \TYPO3\Flow\Tests\Functional\Persistence\Fixtures\TestEntityRepository;
     */
    protected $testEntityRepository;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        if (!$this->persistenceManager instanceof \TYPO3\Flow\Persistence\Doctrine\PersistenceManager) {
            $this->markTestSkipped('Doctrine persistence is not enabled');
        }
        $this->testEntityRepository = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Persistence\Fixtures\TestEntityRepository');
    }

    /**
     * @test
     */
    public function dependencyInjectionIsCorrectlyInitializedEvenIfADoctrineProxyGetsInitializedOnTheFlyFromTheOutside()
    {
        $entity = new TestEntity();
        $entity->setName('Andi');
        $relatedEntity = new TestEntity();
        $relatedEntity->setName('Robert');
        $entity->setRelatedEntity($relatedEntity);

        $this->testEntityRepository->add($entity);
        $this->testEntityRepository->add($relatedEntity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);
        $loadedEntity = $this->testEntityRepository->findByIdentifier($entityIdentifier);

        $this->testEntityRepository->findOneByName('Robert');

        $loadedRelatedEntity = $loadedEntity->getRelatedEntity();

        $this->assertNotNull($loadedRelatedEntity->getObjectManager());
    }

    /**
     * @test
     */
    public function aopIsCorrectlyInitializedEvenIfADoctrineProxyGetsInitializedOnTheFlyFromTheOutside()
    {
        $entity = new TestEntity();
        $entity->setName('Andi');
        $relatedEntity = new TestEntity();
        $relatedEntity->setName('Robert');
        $entity->setRelatedEntity($relatedEntity);

        $this->testEntityRepository->add($entity);
        $this->testEntityRepository->add($relatedEntity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);
        $loadedEntity = $this->testEntityRepository->findByIdentifier($entityIdentifier);

        $this->testEntityRepository->findOneByName('Robert');

        $loadedRelatedEntity = $loadedEntity->getRelatedEntity();

        $this->assertEquals($loadedRelatedEntity->sayHello(), 'Hello Andi!');
    }
}
