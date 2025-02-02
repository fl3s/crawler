<?php
namespace AOEPeople\Crawler\Worker;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use AOE\Crawler\Controller\CrawlerController;
use AOEPeople\Crawler\Hooks\IndexedSearchCrawlerFilesHook;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\IndexedSearch\Worker\WorkerInterface;

class CrawlerWorker implements WorkerInterface
{

    /**
     * CrawlerWorker constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param object $caller Method caller
     * @param array $conf Indexed search configuration
     * @param string $file Relative Filename, relative to public web path. It can also be an absolute path as long as it is inside the lockRootPath (validated with \TYPO3\CMS\Core\Utility\GeneralUtility::isAbsPath()). Finally, if $contentTmpFile is set, this value can be anything, most likely a URL
     * @param string $contentTmpFile Temporary file with the content to read it from (instead of $file). Used when the $file is a URL.
     * @param string $fileExtension File extension for temporary file.
     * @return mixed
     */
    public function index(object $caller, array $conf, string $file, string $contentTmpFile = '', $fileExtension = '')
    {
        $crawler = GeneralUtility::makeInstance(CrawlerController::class);

        $params = [
            'document' => $contentTmpFile,
            'alturl' => $file,
            'conf' => $conf
        ];

        unset($params['conf']['content']);

        $crawler->addQueueEntry_callBack(0, $params, IndexedSearchCrawlerFilesHook::class, $conf['id']);

        GeneralUtility::makeInstance(TimeTracker::class)->setTSlogMessage('media "' . $params['document'] . '" added to "crawler" queue.', 1);
    }
}
