<?php
namespace TYPO3\Flow\Tests\Unit\Security\Authorization;

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
use TYPO3\Flow\Http\Uri;

/**
 * Testcase for the filter firewall
 *
 */
class FilterFirewallTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @test
     * @return void
     */
    public function configuredFiltersAreCreatedCorrectly()
    {
        $resolveRequestPatternClassCallback = function () {
            $args = func_get_args();

            if ($args[0] === 'URI') {
                return 'mockPatternURI';
            } elseif ($args[0] === 'TYPO3\TestRequestPattern') {
                return 'mockPatternTest';
            }
        };

        $resolveInterceptorClassCallback = function () {
            $args = func_get_args();

            if ($args[0] === 'AccessGrant') {
                return 'mockInterceptorAccessGrant';
            } elseif ($args[0] === 'TYPO3\TestSecurityInterceptor') {
                return 'mockInterceptorTest';
            }
        };

        $mockRequestPattern1 = $this->getMock('TYPO3\Flow\Security\RequestPatternInterface', array(), array(), 'pattern1', false);
        $mockRequestPattern1->expects($this->once())->method('setPattern')->with('/some/url/.*');
        $mockRequestPattern2 = $this->getMock('TYPO3\Flow\Security\RequestPatternInterface', array(), array(), 'pattern2', false);
        $mockRequestPattern2->expects($this->once())->method('setPattern')->with('/some/url/blocked.*');

        $getObjectCallback = function () use (&$mockRequestPattern1, &$mockRequestPattern2) {
            $args = func_get_args();

            if ($args[0] === 'mockPatternURI') {
                return $mockRequestPattern1;
            } elseif ($args[0] === 'mockPatternTest') {
                return $mockRequestPattern2;
            } elseif ($args[0] === 'mockInterceptorAccessGrant') {
                return 'AccessGrant';
            } elseif ($args[0] === 'mockInterceptorTest') {
                return 'InterceptorTest';
            } elseif ($args[0] === 'TYPO3\Flow\Security\Authorization\RequestFilter') {
                if ($args[1] == $mockRequestPattern1 && $args[2] === 'AccessGrant') {
                    return 'filter1';
                }
                if ($args[1] == $mockRequestPattern2 && $args[2] === 'InterceptorTest') {
                    return 'filter2';
                }
            }
        };

        $mockObjectManager = $this->getMock('TYPO3\Flow\Object\ObjectManagerInterface', array(), array(), '', false);
        $mockObjectManager->expects($this->any())->method('get')->will($this->returnCallback($getObjectCallback));
        $mockPatternResolver = $this->getMock('TYPO3\Flow\Security\RequestPatternResolver', array(), array(), '', false);
        $mockPatternResolver->expects($this->any())->method('resolveRequestPatternClass')->will($this->returnCallback($resolveRequestPatternClassCallback));
        $mockInterceptorResolver = $this->getMock('TYPO3\Flow\Security\Authorization\InterceptorResolver', array(), array(), '', false);
        $mockInterceptorResolver->expects($this->any())->method('resolveInterceptorClass')->will($this->returnCallback($resolveInterceptorClassCallback));

        $settings = array(
            array(
                'patternType' => 'URI',
                'patternValue' => '/some/url/.*',
                'interceptor' => 'AccessGrant'
            ),
            array(
                'patternType' => 'TYPO3\TestRequestPattern',
                'patternValue' => '/some/url/blocked.*',
                'interceptor' => 'TYPO3\TestSecurityInterceptor'
            )
        );

        $firewall = $this->getAccessibleMock('TYPO3\Flow\Security\Authorization\FilterFirewall', array('blockIllegalRequests'), array(), '', false);
        $firewall->_set('objectManager', $mockObjectManager);
        $firewall->_set('requestPatternResolver', $mockPatternResolver);
        $firewall->_set('interceptorResolver', $mockInterceptorResolver);

        $firewall->_call('buildFiltersFromSettings', $settings);
        $result = $firewall->_get('filters');

        $this->assertEquals(array('filter1', 'filter2'), $result, 'The filters were not built correctly.');
    }

    /**
     * @test
     */
    public function allConfiguredFiltersAreCalled()
    {
        $mockActionRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();

        $mockFilter1 = $this->getMock('TYPO3\Flow\Security\Authorization\RequestFilter', array(), array(), '', false);
        $mockFilter1->expects($this->once())->method('filterRequest')->with($mockActionRequest);
        $mockFilter2 = $this->getMock('TYPO3\Flow\Security\Authorization\RequestFilter', array(), array(), '', false);
        $mockFilter2->expects($this->once())->method('filterRequest')->with($mockActionRequest);
        $mockFilter3 = $this->getMock('TYPO3\Flow\Security\Authorization\RequestFilter', array(), array(), '', false);
        $mockFilter3->expects($this->once())->method('filterRequest')->with($mockActionRequest);

        $firewall = $this->getAccessibleMock('TYPO3\Flow\Security\Authorization\FilterFirewall', array('dummy'), array(), '', false);
        $firewall->_set('filters', array($mockFilter1, $mockFilter2, $mockFilter3));

        $firewall->blockIllegalRequests($mockActionRequest);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Security\Exception\AccessDeniedException
     */
    public function ifRejectAllIsSetAndNoFilterExplicitlyAllowsTheRequestAPermissionDeniedExceptionIsThrown()
    {
        $mockActionRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();

        $mockFilter1 = $this->getMock('TYPO3\Flow\Security\Authorization\RequestFilter', array(), array(), '', false);
        $mockFilter1->expects($this->once())->method('filterRequest')->with($mockActionRequest)->will($this->returnValue(false));
        $mockFilter2 = $this->getMock('TYPO3\Flow\Security\Authorization\RequestFilter', array(), array(), '', false);
        $mockFilter2->expects($this->once())->method('filterRequest')->with($mockActionRequest)->will($this->returnValue(false));
        $mockFilter3 = $this->getMock('TYPO3\Flow\Security\Authorization\RequestFilter', array(), array(), '', false);
        $mockFilter3->expects($this->once())->method('filterRequest')->with($mockActionRequest)->will($this->returnValue(false));

        $firewall = $this->getAccessibleMock('TYPO3\Flow\Security\Authorization\FilterFirewall', array('dummy'), array(), '', false);
        $firewall->_set('filters', array($mockFilter1, $mockFilter2, $mockFilter3));
        $firewall->_set('rejectAll', true);

        $firewall->blockIllegalRequests($mockActionRequest);
    }
}
