<?php
namespace TYPO3\Flow\Session;

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
 * Session Manager
 *
 * @Flow\Scope("singleton")
 */
class SessionManager implements SessionManagerInterface
{
    /**
     * @var \TYPO3\Flow\Session\SessionInterface
     */
    protected $currentSession;

    /**
     * @var array
     */
    protected $remoteSessions;

    /**
     * Meta data cache used by sessions
     *
     * @Flow\Inject
     * @var \TYPO3\Flow\Cache\Frontend\VariableFrontend
     */
    protected $metaDataCache;

    /**
     * Returns the currently active session which stores session data for the
     * current HTTP request on this local system.
     *
     * @return \TYPO3\Flow\Session\SessionInterface
     * @api
     */
    public function getCurrentSession()
    {
        if ($this->currentSession === null) {
            $this->currentSession = new Session();
        }
        return $this->currentSession;
    }

    /**
     * Returns the specified session. If no session with the given identifier exists,
     * NULL is returned.
     *
     * @param string $sessionIdentifier The session identifier
     * @return \TYPO3\Flow\Session\Session
     * @api
     */
    public function getSession($sessionIdentifier)
    {
        if ($this->currentSession !== null && $this->currentSession->isStarted() && $this->currentSession->getId() === $sessionIdentifier) {
            return $this->currentSession;
        }
        if (isset($this->remoteSessions[$sessionIdentifier])) {
            return $this->remoteSessions[$sessionIdentifier];
        }
        if ($this->metaDataCache->has($sessionIdentifier)) {
            $sessionInfo = $this->metaDataCache->get($sessionIdentifier);
            $this->remoteSessions[$sessionIdentifier] = new Session($sessionIdentifier, $sessionInfo['storageIdentifier'], $sessionInfo['lastActivityTimestamp'], $sessionInfo['tags']);
            return $this->remoteSessions[$sessionIdentifier];
        }
    }

    /**
     * Returns all active sessions, even remote ones.
     *
     * @return array<\TYPO3\Flow\Session\Session>
     * @api
     */
    public function getActiveSessions()
    {
        $activeSessions = array();
        foreach ($this->metaDataCache->getByTag('session') as $sessionIdentifier => $sessionInfo) {
            $session = new Session($sessionIdentifier, $sessionInfo['storageIdentifier'], $sessionInfo['lastActivityTimestamp'], $sessionInfo['tags']);
            $activeSessions[] = $session;
        }
        return $activeSessions;
    }

    /**
     * Returns all sessions which are tagged by the specified tag.
     *
     * @param string $tag A valid Cache Frontend tag
     * @return array A collection of Session objects or an empty array if tag did not match
     * @api
     */
    public function getSessionsByTag($tag)
    {
        $taggedSessions = array();
        foreach ($this->metaDataCache->getByTag(Session::TAG_PREFIX . $tag) as $sessionIdentifier => $sessionInfo) {
            $session = new Session($sessionIdentifier, $sessionInfo['storageIdentifier'], $sessionInfo['lastActivityTimestamp'], $sessionInfo['tags']);
            $taggedSessions[] = $session;
        }
        return $taggedSessions;
    }
}
