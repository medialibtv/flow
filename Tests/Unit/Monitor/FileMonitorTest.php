<?php
namespace TYPO3\Flow\Tests\Unit\Monitor;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Monitor\ChangeDetectionStrategy\ChangeDetectionStrategyInterface;
use org\bovigo\vfs\vfsStream;

/**
 * Testcase for the File Monitor class
 */
class FileMonitorTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @var string
     */
    protected $unixStylePath;

    /**
     * @var string
     */
    protected $unixStylePathAndFilename;

    /**
     * Setup
     *
     * @return void
     */
    public function setUp()
    {
        $this->unixStylePath = \TYPO3\Flow\Utility\Files::getUnixStylePath(__DIR__);
        $this->unixStylePathAndFilename = \TYPO3\Flow\Utility\Files::getUnixStylePath(__FILE__);

        vfsStream::setup('testDirectory');
    }

    /**
     * @test
     */
    public function fileMonitorCachesTheListOfKnownDirectoriesAndFiles()
    {
        $mockCache = $this->getMock('TYPO3\Flow\Cache\Frontend\StringFrontend', array(), array(), '', false);
        $mockCache->expects($this->once())->method('has')->with('Flow_Test_directoriesAndFiles')->will($this->returnValue(true));
        $mockCache->expects($this->once())->method('get')->with('Flow_Test_directoriesAndFiles')->will($this->returnValue(json_encode(array('foo' => 'bar'))));
        $mockCache->expects($this->once())->method('set')->with('Flow_Test_directoriesAndFiles', json_encode(array('baz' => 'quux'), true));

        $mockStrategy = $this->getMock('TYPO3\Flow\Monitor\ChangeDetectionStrategy\ChangeDetectionStrategyInterface');
        $mockStrategy->expects($this->once())->method('shutdownObject');

        $mockMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('dummy'), array('Flow_Test'), '', true, true);
        $mockMonitor->injectCache($mockCache);
        $mockMonitor->injectChangeDetectionStrategy($mockStrategy);
        $mockMonitor->initializeObject();

        $this->assertSame(array('foo' => 'bar'), $mockMonitor->_get('directoriesAndFiles'));

        $mockMonitor->_set('directoriesAndFiles', array('baz' => 'quux'));
        $mockMonitor->_set('directoriesChanged', true);

        $mockMonitor->shutdownObject();
    }

    /**
     * @test
     */
    public function monitorFileRegistersAFileForMonitoring()
    {
        $monitor = new \TYPO3\Flow\Monitor\FileMonitor('Flow_Test');
        $monitor->monitorFile(__FILE__);
        $this->assertSame(array($this->unixStylePathAndFilename), $monitor->getMonitoredFiles());
    }

    /**
     * @test
     */
    public function aFileAppearsOnlyOnceInTheListOfMonitoredFiles()
    {
        $monitor = new \TYPO3\Flow\Monitor\FileMonitor('Flow_Test');
        $monitor->monitorFile(__FILE__);
        $monitor->monitorFile(__FILE__);
        $this->assertSame(array($this->unixStylePathAndFilename), $monitor->getMonitoredFiles());
    }

    /**
     * @test
     */
    public function monitorDirectoryRegistersAWholeDirectoryForMonitoring()
    {
        $monitor = new \TYPO3\Flow\Monitor\FileMonitor('Flow_Test');
        $monitor->monitorDirectory(__DIR__);
        $this->assertSame(array($this->unixStylePath), $monitor->getMonitoredDirectories());
    }

    /**
     * @test
     */
    public function aDirectoryAppearsOnlyOnceInTheListOfMonitoredDirectories()
    {
        $monitor = new \TYPO3\Flow\Monitor\FileMonitor('Flow_Test');
        $monitor->monitorDirectory(__DIR__);
        $monitor->monitorDirectory(__DIR__ . '/');
        $this->assertSame(array($this->unixStylePath), $monitor->getMonitoredDirectories());
    }

    /**
     * @test
     */
    public function detectChangesDetectsChangesInMonitoredFiles()
    {
        $mockSystemLogger = $this->getMock('TYPO3\Flow\Log\SystemLoggerInterface');

        $mockMonitor = $this->getMock('TYPO3\Flow\Monitor\FileMonitor', array('detectChangedFiles'), array('Flow_Test'), '', true, true);
        $mockMonitor->expects($this->once())->method('detectChangedFiles')->with(array($this->unixStylePathAndFilename))->will($this->returnValue(array()));

        $mockMonitor->injectSystemLogger($mockSystemLogger);
        $mockMonitor->monitorFile(__FILE__);

        $mockMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangesEmitsFilesHaveChangedSignalIfFilesHaveChanged()
    {
        $mockSystemLogger = $this->getMock('TYPO3\Flow\Log\SystemLoggerInterface');

        $monitoredFiles = array(__FILE__ . '1', __FILE__ . '2', __FILE__ . '3');

        $expectedChangedFiles = array();
        $expectedChangedFiles[$this->unixStylePathAndFilename . '1'] = ChangeDetectionStrategyInterface::STATUS_CREATED;
        $expectedChangedFiles[$this->unixStylePathAndFilename . '3'] = ChangeDetectionStrategyInterface::STATUS_DELETED;

        $mockMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('detectChangedFiles', 'emitFilesHaveChanged'), array('Flow_Test'), '', true, true);
        $mockMonitor->expects($this->once())->method('detectChangedFiles')->with($monitoredFiles)->will($this->returnValue($expectedChangedFiles));
        $mockMonitor->expects($this->once())->method('emitFilesHaveChanged')->with('Flow_Test', $expectedChangedFiles);

        $mockMonitor->injectSystemLogger($mockSystemLogger);
        $mockMonitor->_set('monitoredFiles', $monitoredFiles);

        $mockMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangesDetectsChangesInFilesOfMonitoredDirectories()
    {
        $mockSystemLogger = $this->getMock('TYPO3\Flow\Log\SystemLoggerInterface');
        $testPath = vfsStream::url('testDirectory');

        $knownDirectoriesAndFiles = array(
            $testPath => array(
                $testPath . '/oldfile.txt',
                $testPath . '/newfile.txt'
            )
        );

        $expectedChangedFiles = array($testPath . '/newfile.txt');

        $mockMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('detectChangedFiles', 'emitFilesHaveChanged'), array('Flow_Test'), '', true, true);
        $mockMonitor->expects($this->at(0))->method('detectChangedFiles')->with(array())->will($this->returnValue(array()));
        $mockMonitor->expects($this->at(1))->method('detectChangedFiles')->with($knownDirectoriesAndFiles[$testPath])->will($this->returnValue($expectedChangedFiles));
        $mockMonitor->expects($this->once())->method('emitFilesHaveChanged')->with('Flow_Test', $expectedChangedFiles);

        $mockMonitor->injectSystemLogger($mockSystemLogger);
        $mockMonitor->_set('directoriesAndFiles', $knownDirectoriesAndFiles);

        $mockMonitor->monitorDirectory($testPath);
        $mockMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangesDetectsNewlyCreatedFilesInMonitoredDirectories()
    {
        $mockSystemLogger = $this->getMock('TYPO3\Flow\Log\SystemLoggerInterface');

        $testPath = vfsStream::url('testDirectory');
        file_put_contents($testPath . '/oldfile.txt', 'void');
        file_put_contents($testPath . '/newfile.txt', 'void');

        $knownDirectoriesAndFiles = array(
            $testPath => array($testPath . '/oldfile.txt')
        );

        $actualDirectoriesAndFiles = array(
            $testPath => array(
                $testPath . '/oldfile.txt',
                $testPath . '/newfile.txt'
            )
        );

        $expectedChangedFiles = array($testPath . '/newfile.txt');

        $mockMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('detectChangedFiles', 'emitFilesHaveChanged'), array('Flow_Test'), '', true, true);
        $mockMonitor->expects($this->at(0))->method('detectChangedFiles')->with(array())->will($this->returnValue(array()));
        $mockMonitor->expects($this->at(1))->method('detectChangedFiles')->with($actualDirectoriesAndFiles[$testPath])->will($this->returnValue($expectedChangedFiles));
        $mockMonitor->expects($this->once())->method('emitFilesHaveChanged')->with('Flow_Test', $expectedChangedFiles);

        $mockMonitor->injectSystemLogger($mockSystemLogger);
        $mockMonitor->_set('directoriesAndFiles', $knownDirectoriesAndFiles);

        $mockMonitor->monitorDirectory($testPath);
        $mockMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangesEmitsDirectoryChangedSignalAndMemorizesDirectoryIfDirectoryHasNotBeenMonitoredPreviously()
    {
        $mockSystemLogger = $this->getMock('TYPO3\Flow\Log\SystemLoggerInterface');

        $expectedChangedDirectories = array($this->unixStylePath => ChangeDetectionStrategyInterface::STATUS_CREATED);

        $mockMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('detectChangedFiles', 'emitDirectoriesHaveChanged'), array('Flow_Test'), '', true, true);
        $mockMonitor->expects($this->any())->method('detectChangedFiles')->will($this->returnValue(array()));
        $mockMonitor->expects($this->once())->method('emitDirectoriesHaveChanged')->with('Flow_Test', $expectedChangedDirectories);

        $mockMonitor->injectSystemLogger($mockSystemLogger);
        $mockMonitor->monitorDirectory(__DIR__);

        $mockMonitor->detectChanges();

        $directoriesAndFiles = $mockMonitor->_get('directoriesAndFiles');
        $this->assertTrue(array_search($this->unixStylePathAndFilename, $directoriesAndFiles[$this->unixStylePath]) !== false);
        $this->assertTrue($mockMonitor->_get('directoriesChanged'));
    }

    /**
     * @test
     */
    public function detectChangesEmitsDirectoryChangedSignalIfDirectoryHasBeenRemoved()
    {
        $mockSystemLogger = $this->getMock('TYPO3\Flow\Log\SystemLoggerInterface');

        $expectedChangedDirectories = array(vfsStream::url('testDirectory') . '/bar' => ChangeDetectionStrategyInterface::STATUS_DELETED);

        $mockMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('detectChangedFiles', 'emitDirectoriesHaveChanged'), array('Flow_Test'), '', true, true);
        $mockMonitor->expects($this->any())->method('detectChangedFiles')->will($this->returnValue(array()));
        $mockMonitor->expects($this->once())->method('emitDirectoriesHaveChanged')->with('Flow_Test', $expectedChangedDirectories);

        $mockMonitor->injectSystemLogger($mockSystemLogger);
        $mockMonitor->_set('directoriesAndFiles', array(vfsStream::url('testDirectory') . '/bar' => array()));

        $mockMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangedFilesFetchesTheStatusOfGivenFilesAndReturnsAListOfChangeFilesAndTheirStatus()
    {
        $mockStrategy = $this->getMock('TYPO3\Flow\Monitor\ChangeDetectionStrategy\ChangeDetectionStrategyInterface');
        $mockStrategy->expects($this->exactly(2))->method('getFileStatus')->will($this->onConsecutiveCalls(ChangeDetectionStrategyInterface::STATUS_CREATED, ChangeDetectionStrategyInterface::STATUS_UNCHANGED));

        $mockMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('dummy'), array('Flow_Test'), '', true, true);
        $mockMonitor->injectChangeDetectionStrategy($mockStrategy);
        $result = $mockMonitor->_call('detectChangedFiles', array(__FILE__ . '1', __FILE__ . '2'));

        $this->assertEquals(array(__FILE__ . '1' => ChangeDetectionStrategyInterface::STATUS_CREATED), $result);
    }

    /**
     * @test
     */
    public function detectChangesDetectsChangesInFilesOfMonitoredDirectoriesIfPatternIsMatched()
    {
        $testPath = vfsStream::url('testDirectory');

        // Initially known files per path
        $knownDirectoriesAndFiles = array(
            $testPath => array(
                $testPath . '/NodeTypes.foo.yaml'
            )
        );

        // Outcome of the change dection per file
        $changeDetectionResult = array(
            $testPath . '/NodeTypes.foo.yaml' => ChangeDetectionStrategyInterface::STATUS_CHANGED
        );

        // Expected emitted changes for files
        $expectedEmittedChanges = array(
            $testPath . '/NodeTypes.foo.yaml' => ChangeDetectionStrategyInterface::STATUS_CHANGED
        );

        $fileMonitor = $this->setUpFileMonitorForDetection($changeDetectionResult, $expectedEmittedChanges, $knownDirectoriesAndFiles);
        $fileMonitor->monitorDirectory($testPath, 'NodeTypes(\..+)?\.yaml');
        $fileMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangesDetectsCreatedFilesOfMonitoredDirectoriesOnlyIfPatternIsMatched()
    {
        $testPath = vfsStream::url('testDirectory');

        // Initially known files per path
        $knownDirectoriesAndFiles = array(
            $testPath => array(
                $testPath . '/NodeTypes.foo.yaml'
            )
        );

        // Create some new files
        file_put_contents($testPath . '/test.txt', '');
        file_put_contents($testPath . '/NodeTypes.yaml', '');

        // Outcome of the change dection per file
        $changeDetectionResult = array(
            $testPath . '/test.txt' => ChangeDetectionStrategyInterface::STATUS_CREATED,
            $testPath . '/NodeTypes.yaml' => ChangeDetectionStrategyInterface::STATUS_CREATED
        );

        // Expected emitted changes for files
        $expectedEmittedChanges = array(
            $testPath . '/NodeTypes.yaml' => ChangeDetectionStrategyInterface::STATUS_CREATED
        );

        $fileMonitor = $this->setUpFileMonitorForDetection($changeDetectionResult, $expectedEmittedChanges, $knownDirectoriesAndFiles);
        $fileMonitor->monitorDirectory($testPath, 'NodeTypes(\..+)?\.yaml');
        $fileMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangesDetectsDeletedFilesOfMonitoredDirectoriesIfPatternIsMatched()
    {
        $testPath = vfsStream::url('testDirectory');

        // Initially known files per path
        $knownDirectoriesAndFiles = array(
            $testPath => array(
                $testPath . '/NodeTypes.foo.yaml'
            )
        );

        // Outcome of the change dection per file
        $changeDetectionResult = array(
            $testPath . '/NodeTypes.foo.yaml' => ChangeDetectionStrategyInterface::STATUS_DELETED
        );

        // Expected emitted changes for files
        $expectedEmittedChanges = array(
            $testPath . '/NodeTypes.foo.yaml' => ChangeDetectionStrategyInterface::STATUS_DELETED
        );

        $fileMonitor = $this->setUpFileMonitorForDetection($changeDetectionResult, $expectedEmittedChanges, $knownDirectoriesAndFiles);
        $fileMonitor->monitorDirectory($testPath, 'NodeTypes(\..+)?\.yaml');
        $fileMonitor->detectChanges();
    }

    /**
     * @test
     */
    public function detectChangesAddsCreatedFilesOfMonitoredDirectoriesToStoredDirectories()
    {
        $testPath = vfsStream::url('testDirectory');

        // Initially known files per path
        $knownDirectoriesAndFiles = array(
            $testPath => array()
        );

        // Create a new file
        file_put_contents($testPath . '/test.txt', '');

        // Outcome of the change dection per file
        $changeDetectionResult = array(
            $testPath . '/test.txt' => ChangeDetectionStrategyInterface::STATUS_CREATED
        );

        // Expected emitted changes for files
        $expectedEmittedChanges = array(
            $testPath . '/test.txt' => ChangeDetectionStrategyInterface::STATUS_CREATED
        );

        $fileMonitor = $this->setUpFileMonitorForDetection($changeDetectionResult, $expectedEmittedChanges, $knownDirectoriesAndFiles);
        $fileMonitor->monitorDirectory($testPath);
        $fileMonitor->detectChanges();

        $this->assertEquals(array(
            $testPath => array($testPath . '/test.txt')
        ), $fileMonitor->_get('directoriesAndFiles'));
        $this->assertTrue($fileMonitor->_get('directoriesChanged'));
    }

    /**
     * @param array $changeDetectionResult
     * @param array $expectedEmittedChanges
     * @param array $knownDirectoriesAndFiles
     * @return \TYPO3\Flow\Monitor\FileMonitor
     */
    protected function setUpFileMonitorForDetection(array $changeDetectionResult, array $expectedEmittedChanges, array $knownDirectoriesAndFiles)
    {
        $mockChangeDetectionStrategy = $this->getMock('TYPO3\Flow\Monitor\ChangeDetectionStrategy\ChangeDetectionStrategyInterface');
        $mockChangeDetectionStrategy->expects($this->any())->method('getFileStatus')->will($this->returnCallback(function ($pathAndFilename) use ($changeDetectionResult) {
            if (isset($changeDetectionResult[$pathAndFilename])) {
                return $changeDetectionResult[$pathAndFilename];
            } else {
                return ChangeDetectionStrategyInterface::STATUS_UNCHANGED;
            }
        }));

        $fileMonitor = $this->getAccessibleMock('TYPO3\Flow\Monitor\FileMonitor', array('emitFilesHaveChanged', 'emitDirectoriesHaveChanged'), array('Flow_Test'), '', true, true);
        $this->inject($fileMonitor, 'changeDetectionStrategy', $mockChangeDetectionStrategy);

        $fileMonitor->expects($this->once())->method('emitFilesHaveChanged')->with('Flow_Test', $expectedEmittedChanges);

        $mockSystemLogger = $this->getMock('TYPO3\Flow\Log\SystemLoggerInterface');
        $fileMonitor->injectSystemLogger($mockSystemLogger);
        $fileMonitor->_set('directoriesAndFiles', $knownDirectoriesAndFiles);
        return $fileMonitor;
    }
}
