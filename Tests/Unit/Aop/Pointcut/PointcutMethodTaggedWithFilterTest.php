<?php
namespace TYPO3\Flow\Tests\Unit\Aop\Pointcut;

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
 * Testcase for the Pointcut Method-Tagged-With Filter
 *
 */
class PointcutMethodTaggedWithFilterTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @test
     */
    public function matchesTellsIfTheSpecifiedRegularExpressionMatchesTheGivenTag()
    {
        $mockReflectionService = $this->getMock('TYPO3\Flow\Reflection\ReflectionService', array('getMethodTagsValues'), array(), '', false, true);
        $mockReflectionService->expects($this->any())->method('getMethodTagsValues')->with(__CLASS__, __FUNCTION__)->will($this->onConsecutiveCalls(array('SomeTag' => array(), 'OtherTag' => array('foo')), array()));

        $filter = new \TYPO3\Flow\Aop\Pointcut\PointcutMethodTaggedWithFilter('SomeTag');
        $filter->injectReflectionService($mockReflectionService);

        $this->assertTrue($filter->matches(__CLASS__, __FUNCTION__, __CLASS__, 1234));
        $this->assertFalse($filter->matches(__CLASS__, __FUNCTION__, __CLASS__, 1234));
    }

    /**
     * @test
     */
    public function matchesReturnsFalseIfMethodDoesNotExistOrDeclardingClassHasNotBeenSpecified()
    {
        $mockReflectionService = $this->getMock('TYPO3\Flow\Reflection\ReflectionService', array(), array(), '', false, true);

        $filter = new \TYPO3\Flow\Aop\Pointcut\PointcutMethodTaggedWithFilter('Acme\Some\Annotation');
        $filter->injectReflectionService($mockReflectionService);

        $this->assertFalse($filter->matches(__CLASS__, __FUNCTION__, null, 1234));
        $this->assertFalse($filter->matches(__CLASS__, 'foo', __CLASS__, 1234));
    }
}
