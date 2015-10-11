<?php
/**
 * HistoryLog
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The HistoryLog log controller class.
 *
 * @package HistoryLog
 */
class HistoryLog_LogController extends Omeka_Controller_AbstractActionController
{
    /**
     * Set up the view for full record reports.
     *
     * @return void
     */
    public function logAction()
    {
        $flashMessenger = $this->_helper->FlashMessenger;
        $recordType = $this->_getParam('type');
        $recordId = $this->_getParam('id');
        if (empty($recordType) || empty($recordId)) {
            $flashMessenger->addMessage(__('Record not selected.'), 'error');
        }
        $this->view->record = array(
            'record_type' => Inflector::classify($recordType),
            'record_id' => (integer) $recordId,
        );
    }
}
