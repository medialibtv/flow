<?php
namespace TYPO3\Flow\Tests\Unit\Security\RequestPattern;

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
use TYPO3\Flow\Tests\UnitTestCase;

/**
 * Testcase for the URI request pattern
 */
class UriTest extends UnitTestCase
{
    public function matchRequestDataProvider()
    {
        return array(
            array('uriPath' => '', 'pattern' => '.*', 'shouldMatch' => true),
            array('uriPath' => '', 'pattern' => '/some/nice/.*', 'shouldMatch' => false),
            array('uriPath' => '/some/nice/path/to/index.php', 'pattern' => '/some/nice/.*', 'shouldMatch' => true),
            array('uriPath' => '/some/other/path', 'pattern' => '.*/other/.*', 'shouldMatch' => true),
        );
    }

    /**
     * @test
     * @dataProvider matchRequestDataProvider
     */
    public function matchRequestTests($uriPath, $pattern, $shouldMatch)
    {
        $mockActionRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();

        $mockHttpRequest = $this->getMockBuilder('TYPO3\Flow\Http\Request')->disableOriginalConstructor()->getMock();
        $mockActionRequest->expects($this->atLeastOnce())->method('getHttpRequest')->will($this->returnValue($mockHttpRequest));

        $mockUri = $this->getMockBuilder('TYPO3\Flow\Http\Uri')->disableOriginalConstructor()->getMock();
        $mockHttpRequest->expects($this->atLeastOnce())->method('getUri')->will($this->returnValue($mockUri));

        $mockUri->expects($this->atLeastOnce())->method('getPath')->will($this->returnValue($uriPath));

        $requestPattern = new \TYPO3\Flow\Security\RequestPattern\Uri();
        $requestPattern->setPattern($pattern);

        if ($shouldMatch) {
            $this->assertTrue($requestPattern->matchRequest($mockActionRequest));
        } else {
            $this->assertFalse($requestPattern->matchRequest($mockActionRequest));
        }
    }
}
