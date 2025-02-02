<?php
namespace AOE\Crawler\Tests\Functional\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 AOE GmbH <dev@aoe.com>
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
use AOE\Crawler\Domain\Repository\QueueRepository;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class CrawlerControllerTest
 *
 * @package AOE\Crawler\Tests\Functional\Controller
 */
class CrawlerControllerTest extends FunctionalTestCase
{
    /**
     * @var array
     */
    //protected $coreExtensionsToLoad = ['cms', 'core', 'frontend', 'version', 'lang', 'extensionmanager', 'fluid'];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/crawler'];

    /**
     * @var CrawlerController
     */
    protected $subject;

    public function setUp()
    {
        parent::setUp();
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_crawler_queue.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_crawler_process.xml');
        $this->subject = $this->getAccessibleMock(CrawlerController::class, ['dummy']);
    }

    /**
     * @test
     *
     * @param $uid
     * @param $configurationHash
     * @param $expected
     *
     * @dataProvider noUnprocessedQueueEntriesForPageWithConfigurationHashExistDataProvider
     */
    public function noUnprocessedQueueEntriesForPageWithConfigurationHashExist($uid, $configurationHash, $expected)
    {
        self::assertSame(
            $expected,
            $this->subject->_call(
                'noUnprocessedQueueEntriesForPageWithConfigurationHashExist',
                $uid,
                $configurationHash
            )
        );
    }

    /**
     * @test
     *
     */
    public function cleanUpOldQueueEntries()
    {
        $this->markTestSkipped('This fails with PHP7 & TYPO3 7.6');

        $this->importDataSet(__DIR__ . '/Fixtures/tx_crawler_queue.xml');
        $queryRepository = new QueueRepository();

        $recordsFromFixture = 9;
        $expectedRemainingRecords = 2;
        // Add records to queue repository to ensure we always have records,
        // that will not be deleted with the cleanUpOldQueueEntries-function
        for ($i = 0; $i < $expectedRemainingRecords; $i++) {
            $this->getDatabaseConnection()->exec_INSERTquery(
                'tx_crawler_queue',
                [
                    'exec_time' => time() + (7 * 24 * 60 * 60),
                    'scheduled' => time() + (7 * 24 * 60 * 60)
                ]
            );
        }

        // Check total entries before cleanup
        self::assertEquals(
            $recordsFromFixture + $expectedRemainingRecords,
            $queryRepository->countAll()
        );

        $this->subject->_call('cleanUpOldQueueEntries');

        // Check total entries after cleanup
        self::assertEquals(
            $expectedRemainingRecords,
            $queryRepository->countAll()
        );
    }

    /**
     * @test
     *
     * @param $where
     * @param $expected
     *
     * @dataProvider flushQueueDataProvider
     */
    public function flushQueue($where, $expected)
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $queryRepository = $objectManager->get(QueueRepository::class);
        $this->subject->_call('flushQueue', $where);

        self::assertEquals(
            $expected,
            $queryRepository->countAll()
        );
    }

    /**
     * @test
     *
     * @param $id
     * @param $filter
     * @param $doFlush
     * @param $doFullFlush
     * @param $itemsPerPage
     * @param $expected
     *
     * @dataProvider getLogEntriesForPageIdDataProvider
     */
    public function getLogEntriesForPageId($id, $filter, $doFlush, $doFullFlush, $itemsPerPage, $expected)
    {
        self::assertEquals(
            $expected,
            $this->subject->getLogEntriesForPageId($id, $filter, $doFlush, $doFullFlush, $itemsPerPage)
        );
    }

    /**
     * @test
     *
     * @param $setId
     * @param $filter
     * @param $doFlush
     * @param $doFullFlush
     * @param $itemsPerPage
     * @param $expected
     *
     * @dataProvider getLogEntriesForSetIdDataProvider
     */
    public function getLogEntriesForSetId($setId, $filter, $doFlush, $doFullFlush, $itemsPerPage, $expected)
    {
        self::assertEquals(
            $expected,
            $this->subject->getLogEntriesForSetId($setId, $filter, $doFlush, $doFullFlush, $itemsPerPage)
        );
    }

    /**
     * @return array
     */
    public function getLogEntriesForSetIdDataProvider()
    {
        return [
            'Do Flush' => [
                'setId' => 456,
                'filter' => '',
                'doFlush' => true,
                'doFullFlush' => false,
                'itemsPerPage' => 5,
                'expected' => []
            ],
            'Do Full Flush' => [
                'setId' => 456,
                'filter' => '',
                'doFlush' => true,
                'doFullFlush' => true,
                'itemsPerPage' => 5,
                'expected' => []
            ],
            'Check that doFullFlush do not flush if doFlush is not true' => [
                'setId' => 456,
                'filter' => '',
                'doFlush' => false,
                'doFullFlush' => true,
                'itemsPerPage' => 5,
                'expected' => [[
                    'qid' => '8',
                    'page_id' => '0',
                    'parameters' => '',
                    'parameters_hash' => '',
                    'configuration_hash' => '',
                    'scheduled' => '0',
                    'exec_time' => '0',
                    'set_id' => '456',
                    'result_data' => '',
                    'process_scheduled' => '0',
                    'process_id' => '1007',
                    'process_id_completed' => 'asdfgh',
                    'configuration' => 'ThirdConfiguration'
                ]]
            ],
            'Get entries for set_id 456' => [
                'setId' => 456,
                'filter' => '',
                'doFlush' => false,
                'doFullFlush' => false,
                'itemsPerPage' => 1,
                'expected' => [[
                    'qid' => '8',
                    'page_id' => '0',
                    'parameters' => '',
                    'parameters_hash' => '',
                    'configuration_hash' => '',
                    'scheduled' => '0',
                    'exec_time' => '0',
                    'set_id' => '456',
                    'result_data' => '',
                    'process_scheduled' => '0',
                    'process_id' => '1007',
                    'process_id_completed' => 'asdfgh',
                    'configuration' => 'ThirdConfiguration'
                ]]
            ],
            'Do Flush Pending' => [
                'setId' => 456,
                'filter' => 'pending',
                'doFlush' => true,
                'doFullFlush' => false,
                'itemsPerPage' => 5,
                'expected' => []
            ],
            'Do Flush Finished' => [
                'setId' => 456,
                'filter' => 'finished',
                'doFlush' => true,
                'doFullFlush' => false,
                'itemsPerPage' => 5,
                'expected' => []
            ],
        ];
    }

    /**
     * @return array
     */
    public function getLogEntriesForPageIdDataProvider()
    {
        return [
            'Do Flush' => [
                'id' => 1002,
                'filter' => '',
                'doFlush' => true,
                'doFullFlush' => false,
                'itemsPerPage' => 5,
                'expected' => []
            ],
            'Do Full Flush' => [
                'id' => 1002,
                'filter' => '',
                'doFlush' => true,
                'doFullFlush' => true,
                'itemsPerPage' => 5,
                'expected' => []
            ],
            'Check that doFullFlush do not flush if doFlush is not true' => [
                'id' => 2001,
                'filter' => '',
                'doFlush' => false,
                'doFullFlush' => true,
                'itemsPerPage' => 5,
                'expected' => [[
                    'qid' => '6',
                    'page_id' => '2001',
                    'parameters' => '',
                    'parameters_hash' => '',
                    'configuration_hash' => '7b6919e533f334550b6f19034dfd2f81',
                    'scheduled' => '0',
                    'exec_time' => '0',
                    'set_id' => '123',
                    'result_data' => '',
                    'process_scheduled' => '0',
                    'process_id' => '1006',
                    'process_id_completed' => 'qwerty',
                    'configuration' => 'SecondConfiguration'
                ]]
            ],
            'Get entries for page_id 2001' => [
                'id' => 2001,
                'filter' => '',
                'doFlush' => false,
                'doFullFlush' => false,
                'itemsPerPage' => 1,
                'expected' => [[
                    'qid' => '6',
                    'page_id' => '2001',
                    'parameters' => '',
                    'parameters_hash' => '',
                    'configuration_hash' => '7b6919e533f334550b6f19034dfd2f81',
                    'scheduled' => '0',
                    'exec_time' => '0',
                    'set_id' => '123',
                    'result_data' => '',
                    'process_scheduled' => '0',
                    'process_id' => '1006',
                     'process_id_completed' => 'qwerty',
                    'configuration' => 'SecondConfiguration'
                ]]
            ],

        ];
    }

    /**
     * @return array
     */
    public function flushQueueDataProvider()
    {
        return [
            'Flush Entire Queue' => [
                'where' => '1=1',
                'expected' => 0
            ],
            'Flush Queue with specific configuration' => [
                'where' => 'configuration = \'SecondConfiguration\'',
                'expected' => 9
            ],
            'Flush Queue for specific process id' => [
                'where' => 'process_id = \'1007\'',
                'expected' => 9
            ]
        ];
    }

    /**
     * @return array
     */
    public function noUnprocessedQueueEntriesForPageWithConfigurationHashExistDataProvider()
    {
        return [
            'No record found, uid not present' => [
                'uid' => 3000,
                'configurationHash' => '7b6919e533f334550b6f19034dfd2f81',
                'expected' => true
            ],
            'No record found, configurationHash not present' => [
                'uid' => 2001,
                'configurationHash' => 'invalidConfigurationHash',
                'expected' => true
            ],
            'Record found - uid and configurationHash is present' => [
                'uid' => 2001,
                'configurationHash' => '7b6919e533f334550b6f19034dfd2f81',
                'expected' => false
            ],
        ];
    }
}
