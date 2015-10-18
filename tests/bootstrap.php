<?php
define('HISTORY_LOG_DIR', dirname(dirname(__FILE__)));
define('TEST_FILES_DIR', HISTORY_LOG_DIR
    . DIRECTORY_SEPARATOR . 'tests'
    . DIRECTORY_SEPARATOR . 'suite'
    . DIRECTORY_SEPARATOR . '_files');
require_once dirname(dirname(HISTORY_LOG_DIR)) . '/application/tests/bootstrap.php';
require_once 'HistoryLog_Test_AppTestCase.php';
