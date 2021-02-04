<?php
namespace TYPO3\Flow\Resource\Publishing;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;

/**
 * Support functions for handling assets
 *
 * @Flow\Scope("singleton")
 */
class ResourcePublisher
{
    /**
     * @var \TYPO3\Flow\Resource\Publishing\ResourcePublishingTargetInterface
     */
    protected $resourcePublishingTarget;

    /**
     * Injects the resource publishing target
     *
     * @param \TYPO3\Flow\Resource\Publishing\ResourcePublishingTargetInterface $resourcePublishingTarget
     * @return void
     */
    public function injectResourcePublishingTarget(\TYPO3\Flow\Resource\Publishing\ResourcePublishingTargetInterface $resourcePublishingTarget)
    {
        $this->resourcePublishingTarget = $resourcePublishingTarget;
    }

    /**
     * Recursively publishes all resources found in the specified source directory
     * to the given destination.
     *
     * @param string $sourcePath Path containing the resources to publish
     * @param string $relativeTargetPath Path relative to the public resources directory where the given resources are mirrored to
     * @return boolean TRUE if publication succeeded or FALSE if the resources could not be published
     */
    public function publishStaticResources($sourcePath, $relativeTargetPath)
    {
        return $this->resourcePublishingTarget->publishStaticResources($sourcePath, $relativeTargetPath);
    }

    /**
     * Publishes a persistent resource
     *
     * @param \TYPO3\Flow\Resource\Resource $resource The resource to publish
     * @return mixed Either the web URI of the published resource or FALSE if the resource source file doesn't exist
     */
    public function publishPersistentResource(\TYPO3\Flow\Resource\Resource $resource)
    {
        return $this->resourcePublishingTarget->publishPersistentResource($resource);
    }

    /**
     * Unpublishes a persistent resource
     *
     * @param \TYPO3\Flow\Resource\Resource $resource The resource to unpublish
     * @return boolean TRUE if at least one file was removed, FALSE otherwise
     */
    public function unpublishPersistentResource(\TYPO3\Flow\Resource\Resource $resource)
    {
        return $this->resourcePublishingTarget->unpublishPersistentResource($resource);
    }

    /**
     * Returns the base URI pointing to the published static resources
     *
     * @return string The base URI pointing to web accessible static resources
     */
    public function getStaticResourcesWebBaseUri()
    {
        return $this->resourcePublishingTarget->getStaticResourcesWebBaseUri();
    }

    /**
     * Returns the URI pointing to the published persistent resource
     *
     * @param \TYPO3\Flow\Resource\Resource $resource The resource to publish
     * @return mixed Either the web URI of the published resource or FALSE if the resource source file doesn't exist or the resource could not be published for other reasons
     */
    public function getPersistentResourceWebUri($resource)
    {
        return $this->resourcePublishingTarget->getPersistentResourceWebUri($resource);
    }
}
