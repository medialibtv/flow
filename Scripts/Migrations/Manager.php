<?php
namespace TYPO3\Flow\Core\Migrations;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Utility\Files;

/**
 * The central hub of the code migration tool in Flow.
 */
class Manager
{
    const STATE_NOT_MIGRATED = 0;
    const STATE_MIGRATED = 1;

    /**
     * @var string
     */
    protected $packagesPath = FLOW_PATH_PACKAGES;

    /**
     * @var array
     */
    protected $packagesData = array();

    /**
     * @var array
     */
    protected $migrations = array();

    /**
     * Allows to set the packages path.
     *
     * The level directly inside is expected to consist of package "categories"
     * (Framework, Application, Plugins, ...).
     *
     * @param string $packagesPath
     * @return void
     */
    public function setPackagesPath($packagesPath)
    {
        $this->packagesPath = $packagesPath;
    }

    /**
     * Returns the migration status for all packages.
     *
     * @return array
     */
    public function getStatus()
    {
        $this->initialize();

        $status = array();
        foreach ($this->packagesData as $packageKey => $packageData) {
            $packageStatus = array();
            foreach ($this->migrations as $versionNumber => $versionInstance) {
                $migrationIdentifier = $versionInstance->getIdentifier();

                if (Git::hasMigrationApplied($packageData['path'], $migrationIdentifier)) {
                    $state = self::STATE_MIGRATED;
                } else {
                    $state = self::STATE_NOT_MIGRATED;
                }
                $packageStatus[$versionNumber] = array('source' => $migrationIdentifier, 'state' => $state);
            }
            $status[$packageKey] = $packageStatus;
        }
        return $status;
    }

    /**
     * This iterates over available migrations and applies them to
     * the existing packages if
     * - the package needs the migration
     * - is a clean git working copy
     *
     * @param string $packageKey
     * @return void
     * @throws \RuntimeException
     */
    public function migrate($packageKey = null)
    {
        $this->initialize();

        foreach ($this->migrations as $migrationInstance) {
            echo 'Applying ' . $migrationInstance->getIdentifier() . PHP_EOL;
            if ($packageKey !== null) {
                if (array_key_exists($packageKey, $this->packagesData)) {
                    $this->migratePackage($packageKey, $this->packagesData[$packageKey], $migrationInstance);
                } else {
                    echo '  Package "' . $packageKey . '" was not found.' . PHP_EOL;
                }
            } else {
                foreach ($this->packagesData as $key => $packageData) {
                    if ($packageData['category'] === 'Framework' || $packageData['category'] === 'Libraries') {
                        continue;
                    }
                    $this->migratePackage($key, $packageData, $migrationInstance);
                }
            }
            $migrationInstance->outputNotesAndWarnings();
            echo 'Done with ' . $migrationInstance->getIdentifier() . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Apply the given migration to the package and commit the result.
     *
     * @param string $packageKey
     * @param array $packageData
     * @param AbstractMigration $migration
     * @return void
     * @throws \RuntimeException
     */
    protected function migratePackage($packageKey, array $packageData, AbstractMigration $migration)
    {
        if (Git::isWorkingCopyClean($packageData['path'])) {
            if (Git::hasMigrationApplied($packageData['path'], $migration->getIdentifier())) {
                echo '  Skipping ' . $packageKey . ', the migration is already applied.' . PHP_EOL;
            } else {
                echo '  Migrating ' . $packageKey . PHP_EOL;
                try {
                    $migration->prepare($this->packagesData[$packageKey]);
                    $migration->up();
                    $migration->execute();
                    echo Git::commitMigration($packageData['path'], $migration->getIdentifier());
                } catch (\Exception $exception) {
                    throw new \RuntimeException('Applying migration "' .$migration->getIdentifier() . '" to "' . $packageKey . '" failed.', 0, $exception);
                }
            }
        } else {
            echo '  Skipping ' . $packageKey . ', the working copy is dirty.' . PHP_EOL;
        }
    }


    /**
     * Initialize the manager: read package information and register migrations.
     *
     * @return void
     */
    protected function initialize()
    {
        $this->packagesData = Tools::getPackagesData($this->packagesPath);

        $this->migrations = array();
        foreach ($this->packagesData as $packageKey => $packageData) {
            $this->registerMigrationFiles(Files::concatenatePaths(array($this->packagesPath, $packageData['category'], $packageKey)));
        }
    }

    /**
     * Look for code migration files in the given package path and register them
     * for further action.
     *
     * @param string $packagePath
     * @return void
     */
    protected function registerMigrationFiles($packagePath)
    {
        $packagePath = rtrim($packagePath, '/');
        $packageKey = substr($packagePath, strrpos($packagePath, '/') + 1);
        $migrationsDirectory = Files::concatenatePaths(array($packagePath, 'Migrations/Code'));
        if (!is_dir($migrationsDirectory)) {
            return;
        }

        $migrationFilenames = Files::readDirectoryRecursively($migrationsDirectory, '.php');
        foreach ($migrationFilenames as $filenameAndPath) {
            require_once($filenameAndPath);
            $baseFilename = basename($filenameAndPath, '.php');
            $version = substr($baseFilename, 7);
            $classname = 'TYPO3\Flow\Core\Migrations\\' . $baseFilename;
            $this->migrations[$version] = new $classname($this, $packageKey);
        }
        ksort($this->migrations);
    }
}
