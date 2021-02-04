<?php
namespace TYPO3\Flow\Tests\Unit\Mvc;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Http\Response;
use TYPO3\Flow\Mvc\Controller\ControllerInterface;
use TYPO3\Flow\Mvc\Dispatcher;
use TYPO3\Flow\Mvc\Exception\ForwardException;
use TYPO3\Flow\Mvc\RequestInterface;
use TYPO3\Flow\Tests\UnitTestCase;

/**
 * Testcase for the MVC Dispatcher
 */
class DispatcherTest extends UnitTestCase
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var RequestInterface
     */
    protected $mockRequest;

    /**
     * @var Response
     */
    protected $mockResponse;

    /**
     * @var ControllerInterface
     */
    protected $mockController;

    /**
     * Sets up this test case
     */
    public function setUp()
    {
        $this->dispatcher = $this->getMock('TYPO3\Flow\Mvc\Dispatcher', array('resolveController'), array(), '', false);

        $this->mockRequest = $this->getMock('TYPO3\Flow\Mvc\RequestInterface');

        $this->mockResponse = $this->getMock('TYPO3\Flow\Http\Response');

        $this->mockController = $this->getMock('TYPO3\Flow\Mvc\Controller\ControllerInterface', array('processRequest'));
        $this->dispatcher->expects($this->any())->method('resolveController')->will($this->returnValue($this->mockController));
    }

    /**
     * @test
     */
    public function dispatchCallsTheControllersProcessRequestMethodUntilTheIsDispatchedFlagInTheRequestObjectIsSet()
    {
        $this->mockRequest->expects($this->at(0))->method('isDispatched')->will($this->returnValue(false));
        $this->mockRequest->expects($this->at(1))->method('isDispatched')->will($this->returnValue(false));
        $this->mockRequest->expects($this->at(2))->method('isDispatched')->will($this->returnValue(true));

        $this->mockController->expects($this->exactly(2))->method('processRequest')->with($this->mockRequest, $this->mockResponse);

        $this->dispatcher->dispatch($this->mockRequest, $this->mockResponse);
    }

    /**
     * @test
     */
    public function dispatchIgnoresStopExceptionsForFirstLevelActionRequests()
    {
        $this->mockRequest->expects($this->at(0))->method('isDispatched')->will($this->returnValue(false));
        $this->mockRequest->expects($this->at(2))->method('isDispatched')->will($this->returnValue(true));
        $this->mockRequest->expects($this->atLeastOnce())->method('isMainRequest')->will($this->returnValue(true));

        $this->mockController->expects($this->atLeastOnce())->method('processRequest')->will($this->throwException(new \TYPO3\Flow\Mvc\Exception\StopActionException()));

        $this->dispatcher->dispatch($this->mockRequest, $this->mockResponse);
    }

    /**
     * @test
     */
    public function dispatchCatchesStopExceptionOfActionRequestsAndRollsBackToTheParentRequest()
    {
        $subRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();
        $subRequest->expects($this->atLeastOnce())->method('isMainRequest')->will($this->returnValue(false));
        $subRequest->expects($this->atLeastOnce())->method('getParentRequest')->will($this->returnValue($this->mockRequest));
        $subRequest->expects($this->atLeastOnce())->method('isDispatched')->will($this->returnValue(false));

        $this->mockRequest->expects($this->atLeastOnce())->method('isDispatched')->will($this->returnValue(true));

        $this->mockController->expects($this->atLeastOnce())->method('processRequest')->will($this->throwException(new \TYPO3\Flow\Mvc\Exception\StopActionException()));

        $this->dispatcher->dispatch($subRequest, $this->mockResponse);
    }

    /**
     * @test
     */
    public function dispatchContinuesWithNextRequestFoundInAForwardException()
    {
        $subRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();
        $subRequest->expects($this->atLeastOnce())->method('isMainRequest')->will($this->returnValue(false));
        $subRequest->expects($this->atLeastOnce())->method('getParentRequest')->will($this->returnValue($this->mockRequest));
        $subRequest->expects($this->atLeastOnce())->method('isDispatched')->will($this->returnValue(false));

        $nextRequest = $this->getMockBuilder('TYPO3\Flow\Mvc\ActionRequest')->disableOriginalConstructor()->getMock();
        $nextRequest->expects($this->atLeastOnce())->method('isDispatched')->will($this->returnValue(true));

        $this->mockRequest->expects($this->atLeastOnce())->method('isDispatched')->will($this->returnValue(false));

        $this->mockController->expects($this->at(0))->method('processRequest')->with($subRequest)->will($this->throwException(new \TYPO3\Flow\Mvc\Exception\StopActionException()));

        $forwardException = new ForwardException();
        $forwardException->setNextRequest($nextRequest);
        $this->mockController->expects($this->at(1))->method('processRequest')->with($this->mockRequest)->will($this->throwException($forwardException));

        $this->dispatcher->dispatch($subRequest, $this->mockResponse);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Mvc\Exception\InfiniteLoopException
     */
    public function dispatchThrowsAnInfiniteLoopExceptionIfTheRequestCouldNotBeDispachedAfter99Iterations()
    {
        $requestCallCounter = 0;
        $requestCallBack = function () use (&$requestCallCounter) {
            return ($requestCallCounter++ < 101) ? false : true;
        };
        $this->mockRequest->expects($this->any())->method('isDispatched')->will($this->returnCallBack($requestCallBack, '__invoke'));

        $this->dispatcher->dispatch($this->mockRequest, $this->mockResponse);
    }

    /**
     * @test
     */
    public function resolveControllerReturnsTheControllerSpecifiedInTheRequest()
    {
        $mockController = $this->getMock('TYPO3\Flow\Mvc\Controller\ControllerInterface');

        $mockObjectManager = $this->getMock('TYPO3\Flow\Object\ObjectManagerInterface');
        $mockObjectManager->expects($this->once())->method('get')->with($this->equalTo('TYPO3\TestPackage\SomeController'))->will($this->returnValue($mockController));

        $mockRequest = $this->getMock('TYPO3\Flow\Mvc\ActionRequest', array('getControllerPackageKey', 'getControllerObjectName'), array(), '', false);
        $mockRequest->expects($this->any())->method('getControllerObjectName')->will($this->returnValue('TYPO3\TestPackage\SomeController'));

        $dispatcher = $this->getAccessibleMock('TYPO3\Flow\Mvc\Dispatcher', array('dummy'));
        $dispatcher->injectObjectManager($mockObjectManager);

        $this->assertEquals($mockController, $dispatcher->_call('resolveController', $mockRequest));
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Mvc\Controller\Exception\InvalidControllerException
     */
    public function resolveControllerThrowsAnInvalidControllerExceptionIfTheResolvedControllerDoesNotImplementTheControllerInterface()
    {
        $mockController = $this->getMock('stdClass');

        $mockObjectManager = $this->getMock('TYPO3\Flow\Object\ObjectManagerInterface');
        $mockObjectManager->expects($this->once())->method('get')->with($this->equalTo('TYPO3\TestPackage\SomeController'))->will($this->returnValue($mockController));

        $mockRequest = $this->getMock('TYPO3\Flow\Mvc\ActionRequest', array('getControllerPackageKey', 'getControllerObjectName'), array(), '', false);
        $mockRequest->expects($this->any())->method('getControllerObjectName')->will($this->returnValue('TYPO3\TestPackage\SomeController'));

        $dispatcher = $this->getAccessibleMock('TYPO3\Flow\Mvc\Dispatcher', array('dummy'));
        $dispatcher->injectObjectManager($mockObjectManager);

        $this->assertEquals($mockController, $dispatcher->_call('resolveController', $mockRequest));
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Mvc\Controller\Exception\InvalidControllerException
     */
    public function resolveControllerThrowsAnInvalidControllerExceptionIfTheResolvedControllerDoesNotExist()
    {
        $mockHttpRequest = $this->getMock('TYPO3\Flow\Http\Request', array(), array(), '', false);
        $mockRequest = $this->getMock('TYPO3\Flow\Mvc\ActionRequest', array('getControllerObjectName', 'getHttpRequest'), array(), '', false);
        $mockRequest->expects($this->any())->method('getControllerObjectName')->will($this->returnValue(''));
        $mockRequest->expects($this->any())->method('getHttpRequest')->will($this->returnValue($mockHttpRequest));

        $dispatcher = $this->getAccessibleMock('TYPO3\Flow\Mvc\Dispatcher', array('dummy'));

        $dispatcher->_call('resolveController', $mockRequest);
    }
}
