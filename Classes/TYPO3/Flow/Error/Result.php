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

/**
 * Result object for operations dealing with objects, such as the Property Mapper or the Validators.
 *
 * @api
 */
class Result
{
    /**
     * @var array<\TYPO3\Flow\Error\Error>
     */
    protected $errors = array();

    /**
     * Caches the existence of errors
     * @var boolean
     */
    protected $errorsExist = false;

    /**
     * @var array<\TYPO3\Flow\Error\Warning>
     */
    protected $warnings = array();

    /**
     * Caches the existence of warning
     * @var boolean
     */
    protected $warningsExist = false;

    /**
     * @var array<\TYPO3\Flow\Error\Notice>
     */
    protected $notices = array();

    /**
     * Caches the existence of notices
     * @var boolean
     */
    protected $noticesExist = false;

    /**
     * The result objects for the sub properties
     *
     * @var array<\TYPO3\Flow\Error\Result>
     */
    protected $propertyResults = array();

    /**
     * @var \TYPO3\Flow\Error\Result
     */
    protected $parent = null;

    /**
     * Injects the parent result and propagates the
     * cached error states upwards
     *
     * @param \TYPO3\Flow\Error\Result $parent
     * @return void
     */
    public function setParent(Result $parent)
    {
        if ($this->parent !== $parent) {
            $this->parent = $parent;
            if ($this->hasErrors()) {
                $parent->setErrorsExist();
            }
            if ($this->hasWarnings()) {
                $parent->setWarningsExist();
            }
            if ($this->hasNotices()) {
                $parent->setNoticesExist();
            }
        }
    }

    /**
     * Add an error to the current Result object
     *
     * @param \TYPO3\Flow\Error\Error $error
     * @return void
     * @api
     */
    public function addError(Error $error)
    {
        $this->errors[] = $error;
        $this->setErrorsExist();
    }

    /**
     * Add a warning to the current Result object
     *
     * @param \TYPO3\Flow\Error\Warning $warning
     * @return void
     * @api
     */
    public function addWarning(Warning $warning)
    {
        $this->warnings[] = $warning;
        $this->setWarningsExist();
    }

    /**
     * Add a notice to the current Result object
     *
     * @param \TYPO3\Flow\Error\Notice $notice
     * @return void
     * @api
     */
    public function addNotice(Notice $notice)
    {
        $this->notices[] = $notice;
        $this->setNoticesExist();
    }

    /**
     * Get all errors in the current Result object (non-recursive)
     *
     * @return array<\TYPO3\Flow\Error\Error>
     * @api
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get all warnings in the current Result object (non-recursive)
     *
     * @return array<\TYPO3\Flow\Error\Warning>
     * @api
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Get all notices in the current Result object (non-recursive)
     *
     * @return array<\TYPO3\Flow\Error\Notice>
     * @api
     */
    public function getNotices()
    {
        return $this->notices;
    }

    /**
     * Get the first error object of the current Result object (non-recursive)
     *
     * @return \TYPO3\Flow\Error\Error
     * @api
     */
    public function getFirstError()
    {
        reset($this->errors);
        return current($this->errors);
    }

    /**
     * Get the first warning object of the current Result object (non-recursive)
     *
     * @return \TYPO3\Flow\Error\Warning
     * @api
     */
    public function getFirstWarning()
    {
        reset($this->warnings);
        return current($this->warnings);
    }

    /**
     * Get the first notice object of the current Result object (non-recursive)
     *
     * @return \TYPO3\Flow\Error\Notice
     * @api
     */
    public function getFirstNotice()
    {
        reset($this->notices);
        return current($this->notices);
    }

    /**
     * Return a Result object for the given property path. This is
     * a fluent interface, so you will probably use it like:
     * $result->forProperty('foo.bar')->getErrors() -- to get all errors
     * for property "foo.bar"
     *
     * @param string $propertyPath
     * @return \TYPO3\Flow\Error\Result
     * @api
     */
    public function forProperty($propertyPath)
    {
        if ($propertyPath === '' || $propertyPath === null) {
            return $this;
        }
        if (strpos($propertyPath, '.') !== false) {
            return $this->recurseThroughResult(explode('.', $propertyPath));
        }
        if (!isset($this->propertyResults[$propertyPath])) {
            $this->propertyResults[$propertyPath] = new Result();
            $this->propertyResults[$propertyPath]->setParent($this);
        }
        return $this->propertyResults[$propertyPath];
    }

    /**
     * Internal use only!
     *
     * @param array $pathSegments
     * @return \TYPO3\Flow\Error\Result
     */
    public function recurseThroughResult(array $pathSegments)
    {
        if (count($pathSegments) === 0) {
            return $this;
        }

        $propertyName = array_shift($pathSegments);

        if (!isset($this->propertyResults[$propertyName])) {
            $this->propertyResults[$propertyName] = new Result();
            $this->propertyResults[$propertyName]->setParent($this);
        }

        return $this->propertyResults[$propertyName]->recurseThroughResult($pathSegments);
    }

    /**
     * Does the current Result object have Errors? (Recursively)
     *
     * @return boolean
     * @api
     */
    public function hasErrors()
    {
        return $this->errorsExist;
    }

    /**
     * Sets the error cache to TRUE and propagates the information
     * upwards the Result-Object Tree
     *
     * @return void
     */
    protected function setErrorsExist()
    {
        $this->errorsExist = true;
        if ($this->parent !== null) {
            $this->parent->setErrorsExist();
        }
    }

    /**
     * Does the current Result object have Warnings? (Recursively)
     *
     * @return boolean
     * @api
     */
    public function hasWarnings()
    {
        return $this->warningsExist;
    }

    /**
     * Sets the warning cache to TRUE and propagates the information
     * upwards the Result-Object Tree
     *
     * @return void
     */
    protected function setWarningsExist()
    {
        $this->warningsExist = true;
        if ($this->parent !== null) {
            $this->parent->setWarningsExist();
        }
    }

    /**
     * Does the current Result object have Notices? (Recursively)
     *
     * @return boolean
     * @api
     */
    public function hasNotices()
    {
        return $this->noticesExist;
    }

    /**
     * Sets the notices cache to TRUE and propagates the information
     * upwards the Result-Object Tree
     *
     * @return void
     */
    protected function setNoticesExist()
    {
        $this->noticesExist = true;
        if ($this->parent !== null) {
            $this->parent->setNoticesExist();
        }
    }


    /**
     * Does the current Result object have Notices, Errors or Warnings? (Recursively)
     *
     * @return bool
     */
    public function hasMessages()
    {
        return $this->errorsExist || $this->noticesExist || $this->warningsExist;
    }

    /**
     * Get a list of all Error objects recursively. The result is an array,
     * where the key is the property path where the error occurred, and the
     * value is a list of all errors (stored as array)
     *
     * @return array<\TYPO3\Flow\Error\Error>
     * @api
     */
    public function getFlattenedErrors()
    {
        $result = array();
        $this->flattenTree('errors', $result, array());
        return $result;
    }

    /**
     * Get a list of all Warning objects recursively. The result is an array,
     * where the key is the property path where the warning occurred, and the
     * value is a list of all warnings (stored as array)
     *
     * @return array<\TYPO3\Flow\Error\Warning>
     * @api
     */
    public function getFlattenedWarnings()
    {
        $result = array();
        $this->flattenTree('warnings', $result, array());
        return $result;
    }

    /**
     * Get a list of all Notice objects recursively. The result is an array,
     * where the key is the property path where the notice occurred, and the
     * value is a list of all notices (stored as array)
     *
     * @return array<\TYPO3\Flow\Error\Notice>
     * @api
     */
    public function getFlattenedNotices()
    {
        $result = array();
        $this->flattenTree('notices', $result, array());
        return $result;
    }

    /**
     * Only use internally!
     *
     * Flatten a tree of Result objects, based on a certain property.
     *
     * @param string $propertyName
     * @param array $result
     * @param array $level
     * @return void
     */
    public function flattenTree($propertyName, &$result, $level)
    {
        if (count($this->$propertyName) > 0) {
            $result[implode('.', $level)] = $this->$propertyName;
        }
        foreach ($this->propertyResults as $subPropertyName => $subResult) {
            array_push($level, $subPropertyName);
            $subResult->flattenTree($propertyName, $result, $level);
            array_pop($level);
        }
    }

    /**
     * Merge the given Result object into this one.
     *
     * @param \TYPO3\Flow\Error\Result $otherResult
     * @return void
     * @api
     */
    public function merge(Result $otherResult)
    {
        if ($otherResult->errorsExist) {
            $this->mergeProperty($otherResult, 'getErrors', 'addError');
        }
        if ($otherResult->warningsExist) {
            $this->mergeProperty($otherResult, 'getWarnings', 'addWarning');
        }
        if ($otherResult->noticesExist) {
            $this->mergeProperty($otherResult, 'getNotices', 'addNotice');
        }

        foreach ($otherResult->getSubResults() as $subPropertyName => $subResult) {
            /** @var $subResult Result */
            if (array_key_exists($subPropertyName, $this->propertyResults) && $this->propertyResults[$subPropertyName]->hasMessages()) {
                $this->forProperty($subPropertyName)->merge($subResult);
            } else {
                $this->propertyResults[$subPropertyName] = $subResult;
                $subResult->setParent($this);
            }
        }
    }

    /**
     * Merge a single property from the other result object.
     *
     * @param \TYPO3\Flow\Error\Result $otherResult
     * @param string $getterName
     * @param string $adderName
     * @return void
     */
    protected function mergeProperty(Result $otherResult, $getterName, $adderName)
    {
        foreach ($otherResult->$getterName() as $messageInOtherResult) {
            $this->$adderName($messageInOtherResult);
        }
    }

    /**
     * Get a list of all sub Result objects available.
     *
     * @return array<\TYPO3\Flow\Error\Result>
     */
    public function getSubResults()
    {
        return $this->propertyResults;
    }

    /**
     * Clears the result
     *
     * @return void
     */
    public function clear()
    {
        $this->errors = array();
        $this->notices = array();
        $this->warnings = array();

        $this->warningsExist = false;
        $this->noticesExist = false;
        $this->errorsExist = false;

        $this->propertyResults = array();
    }
}
