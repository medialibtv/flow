<?php
namespace TYPO3\Flow\Command;

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
use TYPO3\Flow\Aop\JoinPoint;
use TYPO3\Flow\Cache\CacheManager;
use TYPO3\Flow\Cache\Frontend\VariableFrontend;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Security\Authorization\AccessDecisionVoterManager;
use TYPO3\Flow\Security\Cryptography\RsaWalletServicePhp;
use TYPO3\Flow\Security\DummyContext;
use TYPO3\Flow\Security\Exception\AccessDeniedException;
use TYPO3\Flow\Security\Policy\PolicyService;

/**
 * Command controller for tasks related to security
 *
 * @Flow\Scope("singleton")
 */
class SecurityCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    /**
     * @Flow\Inject
     * @var RsaWalletServicePhp
     */
    protected $rsaWalletService;

    /**
     * @Flow\Inject
     * @var PolicyService
     */
    protected $policyService;

    /**
     * @var VariableFrontend
     */
    protected $policyCache;

    /**
     * Injects the Cache Manager because we cannot inject an automatically factored cache during compile time.
     *
     * @param CacheManager $cacheManager
     * @return void
     */
    public function injectCacheManager(CacheManager $cacheManager)
    {
        $this->policyCache = $cacheManager->getCache('Flow_Security_Policy');
    }

    /**
     * Import a public key
     *
     * Read a PEM formatted public key from stdin and import it into the
     * RSAWalletService.
     *
     * @return void
     * @see typo3.flow:security:importprivatekey
     */
    public function importPublicKeyCommand()
    {
        $keyData = '';
        // no file_get_contents here because it does not work on php://stdin
        $fp = fopen('php://stdin', 'rb');
        while (!feof($fp)) {
            $keyData .= fgets($fp, 4096);
        }
        fclose($fp);

        $fingerprint = $this->rsaWalletService->registerPublicKeyFromString($keyData);

        $this->outputLine('The public key has been successfully imported. Use the following fingerprint to refer to it in the RSAWalletService: ' . PHP_EOL . PHP_EOL . $fingerprint . PHP_EOL);
    }

    /**
     * Import a private key
     *
     * Read a PEM formatted private key from stdin and import it into the
     * RSAWalletService. The public key will be automatically extracted and stored
     * together with the private key as a key pair.
     *
     * You can generate the same fingerprint returned from this using these commands:
     *
     *  ssh-keygen -yf my-key.pem > my-key.pub
     *  ssh-keygen -lf my-key.pub
     *
     * To create a private key to import using this method, you can use:
     *
     *  ssh-keygen -t rsa -f my-key
     *  ./flow security:importprivatekey < my-key
     *
     * Again, the fingerprint can also be generated using:
     *
     *  ssh-keygen -lf my-key.pub
     *
     * @param boolean $usedForPasswords If the private key should be used for passwords
     * @return void
     * @see typo3.flow:security:importpublickey
     */
    public function importPrivateKeyCommand($usedForPasswords = false)
    {
        $keyData = '';
        // no file_get_contents here because it does not work on php://stdin
        $fp = fopen('php://stdin', 'rb');
        while (!feof($fp)) {
            $keyData .= fgets($fp, 4096);
        }
        fclose($fp);

        $fingerprint = $this->rsaWalletService->registerKeyPairFromPrivateKeyString($keyData, $usedForPasswords);

        $this->outputLine('The keypair has been successfully imported. Use the following fingerprint to refer to it in the RSAWalletService: ' . PHP_EOL . PHP_EOL . $fingerprint . PHP_EOL);
    }

    /**
     * Shows the effective policy rules currently active in the system
     *
     * @param boolean $grantsOnly Only list methods effectively granted to the given roles
     * @return void
     */
    public function showEffectivePolicyCommand($grantsOnly = false)
    {
        $roles = array();
        $roleIdentifiers = $this->request->getExceedingArguments();

        if (empty($roleIdentifiers) === true) {
            $this->outputLine('Please specify at leas one role, to calculate the effective privileges for!');
            $this->quit(1);
        }

        foreach ($roleIdentifiers as $roleIdentifier) {
            if ($this->policyService->hasRole($roleIdentifier)) {
                $currentRole = $this->policyService->getRole($roleIdentifier);
                $roles[$roleIdentifier] = $currentRole;
                foreach ($this->policyService->getAllParentRoles($currentRole) as $parentRoleIdentifier => $parentRole) {
                    if (!isset($roles[$parentRoleIdentifier])) {
                        $roles[$parentRoleIdentifier] = $parentRole;
                    }
                }
            }
        }

        if (count($roles) === 0) {
            $this->outputLine('The specified role(s) do not exist.');
            $this->quit(1);
        }

        $this->outputLine(PHP_EOL . 'The following roles will be used for calculating the effective privileges (retrieved from the configured roles hierarchy):' . PHP_EOL);
        foreach ($roles as $roleIdentifier => $role) {
            $this->outputLine($roleIdentifier);
        }

        $dummySecurityContext = new DummyContext();
        $dummySecurityContext->setRoles($roles);
        $accessDecisionManager = new AccessDecisionVoterManager($this->objectManager, $dummySecurityContext);

        if ($this->policyCache->has('acls')) {
            $classes = array();
            $acls = $this->policyCache->get('acls');
            foreach ($acls as $classAndMethodName => $aclEntry) {
                if (strpos($classAndMethodName, '->') === false) {
                    continue;
                }
                list($className, $methodName) = explode('->', $classAndMethodName);
                $className = $this->objectManager->getCaseSensitiveObjectName($className);
                $reflectionClass = new \ReflectionClass($className);
                foreach ($reflectionClass->getMethods() as $casSensitiveMethodName) {
                    if ($methodName === strtolower($casSensitiveMethodName->getName())) {
                        $methodName = $casSensitiveMethodName->getName();
                        break;
                    }
                }
                $runtimeEvaluationsInPlace = false;
                foreach ($aclEntry as $role => $resources) {
                    if (in_array($role, $roles) === false) {
                        continue;
                    }

                    if (!isset($classes[$className])) {
                        $classes[$className] = array();
                    }
                    if (!isset($classes[$className][$methodName])) {
                        $classes[$className][$methodName] = array();
                        $classes[$className][$methodName]['resources'] = array();
                    }

                    foreach ($resources as $resourceName => $privilege) {
                        $classes[$className][$methodName]['resources'][$resourceName] = $privilege;
                        if ($privilege['runtimeEvaluationsClosureCode'] !== false) {
                            $runtimeEvaluationsInPlace = true;
                        }
                    }
                }

                if ($runtimeEvaluationsInPlace === false) {
                    try {
                        $accessDecisionManager->decideOnJoinPoint(new JoinPoint(null, $className, $methodName, array()));
                    } catch (AccessDeniedException $e) {
                        $classes[$className][$methodName]['effectivePrivilege'] = $e->getMessage();
                    }
                    if (!isset($classes[$className][$methodName]['effectivePrivilege'])) {
                        $classes[$className][$methodName]['effectivePrivilege'] = 'Access granted';
                    }
                } else {
                    $classes[$className][$methodName]['effectivePrivilege'] = 'Could not be calculated. Runtime evaluations in place!';
                }
            }

            foreach ($classes as $className => $methods) {
                $classNamePrinted = false;
                foreach ($methods as $methodName => $resources) {
                    if ($grantsOnly === true && $resources['effectivePrivilege'] !== 'Access granted') {
                        continue;
                    }
                    if ($classNamePrinted === false) {
                        $this->outputLine(PHP_EOL . PHP_EOL . ' <b>' . $className . '</b>');
                        $classNamePrinted = true;
                    }

                    $this->outputLine(PHP_EOL . '  ' . $methodName);
                    if (isset($resources['resources']) === true && is_array($resources['resources']) === true) {
                        foreach ($resources['resources'] as $resourceName => $privilege) {
                            switch ($privilege['privilege']) {
                                case PolicyService::PRIVILEGE_GRANT:
                                    $this->outputLine('   Resource "<i>' . $resourceName . '</i>": Access granted');
                                    break;
                                case PolicyService::PRIVILEGE_DENY:
                                    $this->outputLine('   Resource "<i>' . $resourceName . '</i>": Access denied');
                                    break;
                                case PolicyService::PRIVILEGE_ABSTAIN:
                                    $this->outputLine('   Resource "<i>' . $resourceName . '</i>": Vote abstained (no acl entry for given roles)');
                                    break;
                            }
                        }
                    }
                    $this->outputLine('   <b>Effective privilege for given roles: ' . $resources['effectivePrivilege'] . '</b>');
                }
            }
        } else {
            $this->outputLine('Could not find any policy entries, please warmup caches...');
        }
    }

    /**
     * Lists all public controller actions not covered by the active security policy
     *
     * @return void
     */
    public function showUnprotectedActionsCommand()
    {
        $controllerClassNames = $this->reflectionService->getAllSubClassNamesForClass('TYPO3\Flow\Mvc\Controller\AbstractController');

        $allActionsAreProtected = true;
        foreach ($controllerClassNames as $controllerClassName) {
            if ($this->reflectionService->isClassAbstract($controllerClassName)) {
                continue;
            }

            $methodNames = get_class_methods($controllerClassName);
            if (!is_array($methodNames)) {
                continue;
            }

            $foundUnprotectedAction = false;
            foreach ($methodNames as $methodName) {
                if (preg_match('/.*Action$/', $methodName) === 0 || $this->reflectionService->isMethodPublic($controllerClassName, $methodName) === false) {
                    continue;
                }

                if ($this->policyService->hasPolicyEntryForMethod($controllerClassName, $methodName) === false) {
                    if ($foundUnprotectedAction === false) {
                        $this->outputLine(PHP_EOL . '<b>' . $controllerClassName . '</b>');
                        $foundUnprotectedAction = true;
                        $allActionsAreProtected = false;
                    }
                    $this->outputLine('  ' . $methodName);
                }
            }
        }

        if ($allActionsAreProtected === true) {
            $this->outputLine('All public controller actions are covered by your security policy. Good job!');
        }
    }

    /**
     * Shows the methods represented by the given security resource
     *
     * @param string $resourceName The name of the resource as stated in the policy
     * @return void
     */
    public function showMethodsForResourceCommand($resourceName)
    {
        if ($this->policyCache->has('acls')) {
            $classes = array();
            $acls = $this->policyCache->get('acls');
            foreach ($acls as $classAndMethodName => $aclEntry) {
                if (strpos($classAndMethodName, '->') === false) {
                    continue;
                }
                list($className, $methodName) = explode('->', $classAndMethodName);
                $className = $this->objectManager->getCaseSensitiveObjectName($className);
                $reflectionClass = new \ReflectionClass($className);
                foreach ($reflectionClass->getMethods() as $casSensitiveMethodName) {
                    if ($methodName === strtolower($casSensitiveMethodName->getName())) {
                        $methodName = $casSensitiveMethodName->getName();
                        break;
                    }
                }
                foreach ($aclEntry as $resources) {
                    if (array_key_exists($resourceName, $resources)) {
                        $classes[$className][$methodName] = $methodName;
                        break;
                    }
                }
            }

            if (count($classes) === 0) {
                $this->outputLine('The given Resource did not match any method or is unknown.');
                $this->quit(1);
            }

            foreach ($classes as $className => $methods) {
                $this->outputLine(PHP_EOL . $className);
                foreach ($methods as $methodName) {
                    $this->outputLine('  ' . $methodName);
                }
            }
        } else {
            $this->outputLine('Could not find any policy entries, please warmup caches!');
        }
    }
}
