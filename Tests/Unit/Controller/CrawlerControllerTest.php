<?php
namespace AOE\Crawler\Tests\Unit\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use AOE\Crawler\Controller\CrawlerController;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use Psr\Log\NullLogger;

/**
 * Class CrawlerLibTest
 *
 * @package AOE\Crawler\Tests
 */
class CrawlerControllerTest extends UnitTestCase
{
    /**
     * @var CrawlerController
     */
    protected $crawlerController;

    /**
     * Creates the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        $this->crawlerController = $this->createPartialMock(
            CrawlerController::class,
            ['buildRequestHeaderArray', 'executeShellCommand', 'getFrontendBasePath']
        );
        $this->crawlerController->setLogger(new NullLogger());

        $configuration = [
            'sleepTime' => '1000',
            'sleepAfterFinish' => '10',
            'countInARun' => '100',
            'purgeQueueDays' => '14',
            'processLimit' => '1',
            'processMaxRunTime' => '300',
            'maxCompileUrls' => '10000',
            'processDebug' => '0',
            'processVerbose' => '0',
            'crawlHiddenPages' => '0',
            'phpPath' => '/usr/bin/php',
            'enableTimeslot' => '1',
            'makeDirectRequests' => '0',
            'frontendBasePath' => '/',
            'cleanUpOldQueueEntries' => '1',
            'cleanUpProcessedAge' => '2',
            'cleanUpScheduledAge' => '7',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['crawler'] = $configuration;
    }

    /**
     * Resets the test environment after the test.
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->crawlerController);
    }

    /**
     * @test
     */
    public function setAndGet()
    {
        $accessMode = 'cli';
        $this->crawlerController->setAccessMode($accessMode);

        self::assertEquals(
            $accessMode,
            $this->crawlerController->getAccessMode()
        );
    }

    /**
     * @test
     *
     * @dataProvider setAndGetDisabledDataProvider
     */
    public function setAndGetDisabled($disabled, $expected)
    {
        $filenameWithPath = tempnam('/tmp', 'test_foo');
        $this->crawlerController->setProcessFilename($filenameWithPath);

        if (null === $disabled) {
            $this->crawlerController->setDisabled();
        } else {
            $this->crawlerController->setDisabled($disabled);
        }
        self::assertEquals(
            $expected,
            $this->crawlerController->getDisabled()
        );
    }

    /**
     * @test
     */
    public function setAndGetProcessFilename()
    {
        $filenameWithPath = tempnam('/tmp', 'test_foo');
        $this->crawlerController->setProcessFilename($filenameWithPath);

        self::assertEquals(
            $filenameWithPath,
            $this->crawlerController->getProcessFilename()
        );
    }

    /**
     * @test
     *
     * @dataProvider drawURLs_PIfilterDataProvider
     */
    public function drawURLs_PIfilter($piString, $incomingProcInstructions, $expected)
    {
        self::assertEquals(
            $expected,
            $this->crawlerController->drawURLs_PIfilter($piString, $incomingProcInstructions)
        );
    }

    /**
     * @test
     *
     * @param $groupList
     * @param $accessList
     * @param $expected
     *
     * @dataProvider hasGroupAccessDataProvider
     */
    public function hasGroupAccess($groupList, $accessList, $expected)
    {
        self::assertEquals(
            $expected,
            $this->crawlerController->hasGroupAccess($groupList, $accessList)
        );
    }

    /**
     * @test
     *
     * @param $checkIfPageSkipped
     * @param $getUrlsForPages
     * @param $pageRow
     * @param $skipMessage
     * @param $expected
     *
     * @dataProvider getUrlsForPageRowDataProvider
     */
    public function getUrlsForPageRow($checkIfPageSkipped, $getUrlsForPages, $pageRow, $skipMessage, $expected)
    {
        /** @var CrawlerController $crawlerController */
        $crawlerController = $this->createPartialMock(CrawlerController::class, ['checkIfPageShouldBeSkipped', 'getUrlsForPageId']);
        $crawlerController->expects($this->any())->method('checkIfPageShouldBeSkipped')->will($this->returnValue($checkIfPageSkipped));
        $crawlerController->expects($this->any())->method('getUrlsForPageId')->will($this->returnValue($getUrlsForPages));

        self::assertEquals(
            $expected,
            $crawlerController->getUrlsForPageRow($pageRow, $skipMessage)
        );
    }

    /**
     * @return array
     */
    public function getUrlsForPageRowDataProvider()
    {
        return [
            'Message equals false, returns Urls from getUrlsForPages()' => [
                'checkIfPageSkipped' => false,
                'getUrlsForPages' => ['index.php?q=search&page=1', 'index.php?q=search&page=2'],
                'pageRow' => ['uid' => 2001],
                '$skipMessage' => 'Just variable placeholder, not used in tests as parsed as reference',
                'expected' => ['index.php?q=search&page=1', 'index.php?q=search&page=2']
            ],
            'Message string not empty, returns empty array' => [
                'checkIfPageSkipped' => 'Because page is hidden',
                'getUrlsForPages' => ['index.php?q=search&page=1', 'index.php?q=search&page=2'],
                'pageRow' => ['uid' => 2001],
                '$skipMessage' => 'Just variable placeholder, not used in tests as parsed as reference',
                'expected' => []
            ],
        ];
    }

    /**
     * @test
     *
     * @param $paramArray
     * @param $urls
     * @param $expected
     *
     * @dataProvider compileUrlsDataProvider
     */
    public function compileUrls($paramArray, $urls, $expected)
    {
        self::assertEquals(
            $expected,
            $this->crawlerController->compileUrls($paramArray, $urls)
        );
    }

    /**
     * @return array
     */
    public function compileUrlsDataProvider()
    {
        return [
            'Empty Params array' => [
                'paramArray' => [],
                'urls' => ['/home', '/search', '/about'],
                'expected' => ['/home', '/search', '/about']
            ],
            'Empty Urls array' => [
                'paramArray' => ['pagination' => [1, 2, 3, 4]],
                'urls' => [],
                'expected' => []
            ],
            'case' => [
                'paramArray' => ['pagination' => [1, 2, 3, 4]],
                'urls' => ['index.php?id=10', 'index.php?id=11'],
                'expected' => [
                    'index.php?id=10&pagination=1',
                    'index.php?id=10&pagination=2',
                    'index.php?id=10&pagination=3',
                    'index.php?id=10&pagination=4',
                    'index.php?id=11&pagination=1',
                    'index.php?id=11&pagination=2',
                    'index.php?id=11&pagination=3',
                    'index.php?id=11&pagination=4',
                ]
            ]
        ];
    }

    /**
     * @test
     *
     * @param $extensionSetting
     * @param $pageRow
     * @param $excludeDoktype
     * @param $expected
     *
     * @dataProvider checkIfPageShouldBeSkippedDataProvider
     */
    public function checkIfPageShouldBeSkipped($extensionSetting, $pageRow, $excludeDoktype, $expected)
    {
        $this->crawlerController->setExtensionSettings($extensionSetting);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['crawler']['excludeDoktype'] = $excludeDoktype;

        self::assertEquals(
            $expected,
            $this->crawlerController->checkIfPageShouldBeSkipped($pageRow)
        );
    }

    /**
     * @test
     */
    public function CLI_buildProcessIdIsSetReturnsValue()
    {
        $processId = '12297a261b';
        $crawlerController = $this->getAccessibleMock(CrawlerController::class, ['dummy'], [], '', false);
        $crawlerController->_set('processID', $processId);

        self::assertEquals(
            $processId,
            $crawlerController->_call('CLI_buildProcessId')
        );
    }

    /**
     * @test
     *
     * @param array $configuration
     * @param string $expected
     *
     * @dataProvider getConfigurationHasReturnsExpectedValueDataProvider
     */
    public function getConfigurationHasReturnsExpectedValue(array $configuration, $expected)
    {
        $crawlerLib = $this->getAccessibleMock(CrawlerController::class, ['dummy'], [], '', false);

        self::assertEquals(
            $expected,
            $crawlerLib->_call('getConfigurationHash', $configuration)
        );
    }

    /**
     * @return array
     */
    public function getConfigurationHasReturnsExpectedValueDataProvider()
    {
        return [
            'Configuration with either paramExpanded nor URLs set' => [
                'configuration' => [
                    'testKey' => 'testValue',
                    'paramExpanded' => '',
                    'URLs' => ''
                ],
                'expected' => 'a73d2e7035f7fa032237c8cf0eb5be22'
            ],
            'Configuration with only paramExpanded set' => [
                'configuration' => [
                    'testKey' => 'testValue',
                    'paramExpanded' => 'Value not important',
                    'URLs' => ''
                ],
                'expected' => 'a73d2e7035f7fa032237c8cf0eb5be22'
            ],
            'Configuration with only URLS set' => [
                'configuration' => [
                    'testKey' => 'testValue',
                    'paramExpanded' => '',
                    'URLs' => 'Value not important'
                ],
                'expected' => 'a73d2e7035f7fa032237c8cf0eb5be22'
            ],
            'Configuration with both paramExpanded and URLS set' => [
                'configuration' => [
                    'testKey' => 'testValue',
                    'paramExpanded' => 'Value not important',
                    'URLs' => 'Value not important'
                ],
                'expected' => 'a73d2e7035f7fa032237c8cf0eb5be22'
            ],
            'Configuration with both paramExpanded and URLS set, will return same hash' => [
                'configuration' => [
                    'testKey' => 'testValue',
                    'paramExpanded' => 'Value not important, but different than test case before',
                    'URLs' => 'Value not important, but different than test case before'
                ],
                'expected' => 'a73d2e7035f7fa032237c8cf0eb5be22'
            ],
        ];
    }

    /**
     * @return array
     */
    public function checkIfPageShouldBeSkippedDataProvider()
    {
        return [
            'Page of doktype 1 - Standand' => [
                'extensionSetting' => [],
                'pageRow' => [
                    'doktype' => 1,
                    'hidden' => 0
                ],
                'excludeDoktype' => [],
                'expected' => false
            ],
            'Extension Setting do not crawl hidden pages and page is hidden' => [
                'extensionSetting' => ['crawlHiddenPages' => false],
                'pageRow' => [
                    'doktype' => 1,
                    'hidden' => 1
                ],
                'excludeDoktype' => [],
                'expected' => 'Because page is hidden',
            ],
            'Page of doktype 3 - External Url' => [
                'extensionSettings' => [],
                'pageRow' => [
                    'doktype' => 3,
                    'hidden' => 0
                ],
                'excludeDoktype' => [],
                'expected' => 'Because doktype is not allowed'
            ],
            'Page of doktype 4 - Shortcut' => [
                'extensionSettings' => [],
                'pageRow' => [
                    'doktype' => 4,
                    'hidden' => 0
                ],
                'excludeDoktype' => [],
                'expected' => 'Because doktype is not allowed'
            ],
            'Page of doktype 155 - Custom' => [
                'extensionSettings' => [],
                'pageRow' => [
                    'doktype' => 155,
                    'hidden' => 0
                ],
                'excludeDoktype' => ['custom' => 155],
                'expected' => 'Doktype was excluded by "custom"'
            ],
            'Page of doktype 255 - Out of allowed range' => [
                'extensionSettings' => [],
                'pageRow' => [
                    'doktype' => 255,
                    'hidden' => 0
                ],
                'excludeDoktype' => [],
                'expected' => 'Because doktype is not allowed'
            ]
        ];
    }

    /**
     * @return array
     */
    public function getConfigurationKeysDataProvider()
    {
        return [
            'cliObject with no -conf' => [
                'config' => [(string)'-d' => 4, (string)'-o' => 'url'],
                'expected' => []
            ],
            'cliObject with one -conf' => [
                'config' => [(string)'-d' => 4, (string)'-o' => 'url', (string)'-conf' => 'default'],
                'expected' => ['default']
            ],
            'cliObject with two -conf' => [
                'config' => [(string)'-d' => 4, (string)'-o' => 'url', (string)'-conf' => 'default,news'],
                'expected' => ['default', 'news']
            ]
        ];
    }

    /**
     * @return array
     */
    public function setAndGetDisabledDataProvider()
    {
        return [
            'setDisabled with no param' => [
                'disabled' => null,
                'expected' => true
            ],
            'setDisabled with true param' => [
                'disabled' => true,
                'expected' => true
            ],
            'setDisabled with false param' => [
                'disabled' => false,
                'expected' => false
            ],
        ];
    }

    /**
     * @return array
     */
    public function drawURLs_PIfilterDataProvider()
    {
        return [
            'Not in list' => [
                'piString' => 'tx_indexedsearch_reindex,tx_esetcache_clean_main',
                'incomingProcInstructions' => [
                    'tx_unknown_extension_instruction'
                ],
                'expected' => false
            ],
            'In list' => [
                'piString' => 'tx_indexedsearch_reindex,tx_esetcache_clean_main',
                'incomingProcInstructions' => [
                    'tx_indexedsearch_reindex',
                ],
                'expected' => true
            ],
            'Twice in list' => [
                'piString' => 'tx_indexedsearch_reindex,tx_esetcache_clean_main',
                'incomingProcInstructions' => [
                    'tx_indexedsearch_reindex',
                    'tx_indexedsearch_reindex'
                ],
                'expected' => true
            ],
            'Empty incomingProcInstructions' => [
                'piString' => '',
                'incomingProcInstructions' => [],
                'expected' => true
            ],
            'In list CAPITALIZED' => [
                'piString' => 'tx_indexedsearch_reindex,tx_esetcache_clean_main',
                'incomingProcInstructions' => [
                    'TX_INDEXEDSEARCH_REINDES'
                ],
                'expected' => false
            ],
        ];
    }

    /**
     * @return array
     */
    public function hasGroupAccessDataProvider()
    {
        return [
            'Do not have access' => [
                'groupList' => '1,2,3',
                'accessList' => '4,5,6',
                'expected' => false
            ],
            'Do have access' => [
                'groupList' => '1,2,3,4',
                'accessList' => '4,5,6',
                'expected' => true
            ],
            'Access List empty' => [
                'groupList' => '1,2,3',
                'accessList' => '',
                'expected' => true
            ]
        ];
    }
}
