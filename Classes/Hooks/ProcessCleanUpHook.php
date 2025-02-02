<?php
namespace AOE\Crawler\Hooks;

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
use AOE\Crawler\Domain\Repository\ProcessRepository;
use AOE\Crawler\Domain\Repository\QueueRepository;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class ProcessCleanUpHook
 * @package AOE\Crawler\Hooks
 */
class ProcessCleanUpHook
{
    /**
     * @var CrawlerController
     */
    private $crawlerController;

    /**
     * @var array
     */
    private $extensionSettings;

    /**
     * @var ProcessRepository
     */
    protected $processRepository;

    /**
     * @var QueueRepository
     */
    protected $queueRepository;

    public function __construct()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->processRepository = $objectManager->get(ProcessRepository::class);
        $this->queueRepository = $objectManager->get(QueueRepository::class);
    }

    /**
     * Main function of process CleanUp Hook.
     *
     * @param CrawlerController $crawlerController Crawler Lib class
     *
     * @return void
     */
    public function crawler_init(CrawlerController $crawlerController)
    {
        $this->crawlerController = $crawlerController;
        $this->extensionSettings = $this->crawlerController->extensionSettings;

        // Clean Up
        $this->removeActiveOrphanProcesses();
        $this->removeActiveProcessesOlderThanOneHour();
    }

    /**
     * Remove active processes older than one hour
     *
     * @return void
     */
    private function removeActiveProcessesOlderThanOneHour()
    {
        $results = $this->processRepository->getActiveProcessesOlderThanOneHour();

        if (!is_array($results)) {
            return;
        }
        foreach ($results as $result) {
            $systemProcessId = (int)$result['system_process_id'];
            $processId = $result['process_id'];
            if ($systemProcessId > 1) {
                if ($this->doProcessStillExists($systemProcessId)) {
                    $this->killProcess($systemProcessId);
                }
                $this->removeProcessFromProcesslist($processId);
            }
        }
    }

    /**
     * Removes active orphan processes from process list
     *
     * @return void
     */
    private function removeActiveOrphanProcesses()
    {
        $results = $this->processRepository->getActiveOrphanProcesses();

        if (!is_array($results)) {
            return;
        }
        foreach ($results as $result) {
            $processExists = false;
            $systemProcessId = (int)$result['system_process_id'];
            $processId = $result['process_id'];
            if ($systemProcessId > 1) {
                $dispatcherProcesses = $this->findDispatcherProcesses();
                if (!is_array($dispatcherProcesses) || empty($dispatcherProcesses)) {
                    $this->removeProcessFromProcesslist($processId);
                    return;
                }
                foreach ($dispatcherProcesses as $process) {
                    $responseArray = $this->createResponseArray($process);
                    if ($systemProcessId === (int)$responseArray[1]) {
                        $processExists = true;
                    };
                }
                if (!$processExists) {
                    $this->removeProcessFromProcesslist($processId);
                }
            }
        }
    }

    /**
     * Remove a process from processlist
     *
     * @param string $processId Unique process Id.
     *
     * @return void
     */
    private function removeProcessFromProcesslist($processId)
    {
        $this->processRepository->removeByProcessId($processId);
        $this->queueRepository->unsetQueueProcessId($processId);
    }

    /**
     * Create response array
     * Convert string to array with space character as delimiter,
     * removes all empty records to have a cleaner array
     *
     * @param string $string String to create array from
     *
     * @return array
     *
     */
    private function createResponseArray($string)
    {
        $responseArray = GeneralUtility::trimExplode(' ', $string, true);
        $responseArray = array_values($responseArray);
        return $responseArray;
    }

    /**
     * Check if the process still exists
     *
     * @param int $pid Process id to be checked.
     *
     * @return bool
     * @codeCoverageIgnore
     */
    private function doProcessStillExists($pid)
    {
        $doProcessStillExists = false;
        if (!Environment::isWindows()) {
            // Not windows
            if (file_exists('/proc/' . $pid)) {
                $doProcessStillExists = true;
            }
        } else {
            // Windows
            exec('tasklist | find "' . $pid . '"', $returnArray, $returnValue);
            if (count($returnArray) > 0 && preg_match('/php/i', $returnValue[0])) {
                $doProcessStillExists = true;
            }
        }
        return $doProcessStillExists;
    }

    /**
     * Kills a process
     *
     * @param int $pid Process id to kill
     *
     * @return void
     * @codeCoverageIgnore
     */
    private function killProcess($pid)
    {
        if (!Environment::isWindows()) {
            // Not windows
            posix_kill($pid, 9);
        } else {
            // Windows
            exec('taskkill /PID ' . $pid);
        }
    }

    /**
     * Find dispatcher processes
     *
     * @return array
     * @codeCoverageIgnore
     */
    private function findDispatcherProcesses()
    {
        $returnArray = [];
        if (!Environment::isWindows()) {
            // Not windows
            exec('ps aux | grep \'cli_dispatcher\'', $returnArray, $returnValue);
        } else {
            // Windows
            exec('tasklist | find \'cli_dispatcher\'', $returnArray, $returnValue);
        }
        return $returnArray;
    }
}
