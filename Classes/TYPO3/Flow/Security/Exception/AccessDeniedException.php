<?php
namespace TYPO3\Flow\Security\Exception;

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
 * An "AccessDenied" Exception
 *
 * @api
 */
class AccessDeniedException extends \TYPO3\Flow\Security\Exception
{
    /**
     * @var integer
     */
    protected $statusCode = 403;
}
