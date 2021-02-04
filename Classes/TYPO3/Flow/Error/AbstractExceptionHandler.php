<?php
namespace TYPO3\Flow\Error;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Log\ThrowableLoggerInterface;

require_once('Exception.php');

/**
 * An abstract exception handler
 */
abstract class AbstractExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @var \TYPO3\Flow\Log\SystemLoggerInterface
     */
    protected $systemLogger;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * Injects the system logger
     *
     * @param \TYPO3\Flow\Log\SystemLoggerInterface $systemLogger
     * @return void
     */
    public function injectSystemLogger(\TYPO3\Flow\Log\SystemLoggerInterface $systemLogger)
    {
        $this->systemLogger = $systemLogger;
    }

    /**
     * Sets options of this exception handler.
     *
     * @param array $options Options for the exception handler
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
        unset($this->options['className']);
    }

    /**
     * Constructs this exception handler - registers itself as the default exception handler.
     *
     */
    public function __construct()
    {
        set_exception_handler(array($this, 'handleException'));
    }

    /**
     * Handles the given exception
     *
     * @param object $exception The exception object - can be \Exception, or some type of \Throwable in PHP 7
     * @return void
     */
    public function handleException($exception)
    {
        // Ignore if the error is suppressed by using the shut-up operator @
        if (error_reporting() === 0) {
            return;
        }

        if (is_object($this->systemLogger)) {
            $options = $this->resolveCustomRenderingOptions($exception);
            if (isset($options['logException']) && $options['logException']) {
                if ($exception instanceof \Throwable) {
                    if ($this->systemLogger instanceof ThrowableLoggerInterface) {
                        $this->systemLogger->logThrowable($exception);
                    } else {
                        // Convert \Throwable to \Exception for non-supporting logger implementations
                        $this->systemLogger->logException(new \Exception($exception->getMessage(), $exception->getCode()));
                    }
                } elseif ($exception instanceof \Exception) {
                    $this->systemLogger->logException($exception);
                }
            }
        }

        switch (PHP_SAPI) {
            case 'cli':
                $this->echoExceptionCli($exception);
                break;
            default:
                $this->echoExceptionWeb($exception);
        }
    }

    /**
     * Echoes an exception for the command line.
     *
     * @param object $exception \Exception or \Throwable
     * @return void
     */
    abstract protected function echoExceptionCli($exception);

    /**
     * Echoes an exception for the web.
     *
     * @param object $exception \Exception or \Throwable
     * @return void
     */
    abstract protected function echoExceptionWeb($exception);


    /**
     * Prepares a Fluid view for rendering the custom error page.
     *
     * @param object $exception \Exception or \Throwable
     * @param array $renderingOptions Rendering options as defined in the settings
     * @return \TYPO3\Fluid\View\StandaloneView
     */
    protected function buildCustomFluidView($exception, array $renderingOptions)
    {
        $statusCode = 500;
        $referenceCode = null;
        if ($exception instanceof \TYPO3\Flow\Exception) {
            $statusCode = $exception->getStatusCode();
            $referenceCode = $exception->getReferenceCode();
        }
        $statusMessage = \TYPO3\Flow\Http\Response::getStatusMessageByCode($statusCode);

        $fluidView = new \TYPO3\Fluid\View\StandaloneView();
        $fluidView->setTemplatePathAndFilename($renderingOptions['templatePathAndFilename']);
        if (isset($renderingOptions['layoutRootPath'])) {
            $fluidView->setLayoutRootPath($renderingOptions['layoutRootPath']);
        }
        if (isset($renderingOptions['partialRootPath'])) {
            $fluidView->setPartialRootPath($renderingOptions['partialRootPath']);
        }
        if (isset($renderingOptions['format'])) {
            $fluidView->setFormat($renderingOptions['format']);
        }
        if (isset($renderingOptions['variables'])) {
            $fluidView->assignMultiple($renderingOptions['variables']);
        }
        $fluidView->assignMultiple(array(
            'exception' => $exception,
            'renderingOptions' => $renderingOptions,
            'statusCode' => $statusCode,
            'statusMessage' => $statusMessage,
            'referenceCode' => $referenceCode
        ));
        return $fluidView;
    }

    /**
     * Checks if custom rendering rules apply to the given $exception and returns those.
     *
     * @param object $exception \Exception or \Throwable
     * @return array the custom rendering options, or NULL if no custom rendering is defined for this exception
     */
    protected function resolveCustomRenderingOptions($exception)
    {
        $renderingOptions = array();
        if (isset($this->options['defaultRenderingOptions'])) {
            $renderingOptions = $this->options['defaultRenderingOptions'];
        }
        if (!isset($this->options['renderingGroups'])) {
            return $renderingOptions;
        }
        foreach ($this->options['renderingGroups'] as $renderingGroupSettings) {
            if (isset($renderingGroupSettings['matchingExceptionClassNames'])) {
                foreach ($renderingGroupSettings['matchingExceptionClassNames'] as $exceptionClassName) {
                    if ($exception instanceof $exceptionClassName) {
                        $renderingOptions = \TYPO3\Flow\Utility\Arrays::arrayMergeRecursiveOverrule($renderingOptions, $renderingGroupSettings['options']);
                        return $renderingOptions;
                    }
                }
            }
        }
        foreach ($this->options['renderingGroups'] as $renderingGroupSettings) {
            if ($exception instanceof \TYPO3\Flow\Exception && isset($renderingGroupSettings['matchingStatusCodes'])) {
                foreach ($renderingGroupSettings['matchingStatusCodes'] as $statusCode) {
                    if ($statusCode === $exception->getStatusCode()) {
                        $renderingOptions = \TYPO3\Flow\Utility\Arrays::arrayMergeRecursiveOverrule($renderingOptions, $renderingGroupSettings['options']);
                        return $renderingOptions;
                    }
                }
            }
        }
        return $renderingOptions;
    }
}
