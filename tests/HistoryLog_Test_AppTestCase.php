<?php
/**
 * @copyright Daniel Berthereau, 2015
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 * @package HistoryLog
 */

/**
 * Base class for HistoryLog tests.
 */
class HistoryLog_Test_AppTestCase extends Omeka_Test_AppTestCase
{
    const PLUGIN_NAME = 'HistoryLog';

    public function setUp()
    {
        parent::setUp();

        // Authenticate and set the current user
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);

        $pluginHelper = new Omeka_Test_Helper_Plugin;
        $pluginHelper->setUp(self::PLUGIN_NAME);
        Omeka_Test_Resource_Db::$runInstaller = true;
    }

    public function assertPreConditions()
    {
        $entries = $this->db->getTable('HistoryLogEntry')->findAll();
        $this->assertEquals(0, count($entries), 'There should be no entries.');
        $entries = $this->db->getTable('HistoryLogChange')->findAll();
        $this->assertEquals(0, count($entries), 'There should be no changes.');
    }

    protected function _createOne($index = null)
    {
        // Omeka adds one item by default.
        $this->assertEquals(1, total_records('Item'));

        $metadata = array();
        $elementTexts = array();
        $elementTexts['Dublin Core']['Title'][] = array('text' => 'title 1', 'html' => false);
        $elementTexts['Dublin Core']['Creator'][] = array('text' => 'creator #1', 'html' => false);
        $elementTexts['Dublin Core']['Date'][] = array('text' => 2001, 'html' => false);
        return insert_item($metadata, $elementTexts);
    }
}
