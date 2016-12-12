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

        $recordType = Inflector::classify($recordType);
        $recordId = (integer) $recordId;

        $record = get_record_by_id($recordType, $recordId);

        $this->view->record = $record ?: array(
            'record_type' => Inflector::classify($recordType),
            'record_id' => (integer) $recordId,
        );
    }

    /**
     * Undelete a record when possible.
     *
     * @return void
     */
    public function undeleteAction()
    {
        $flashMessenger = $this->_helper->FlashMessenger;
        $recordType = $this->_getParam('type');
        $recordId = $this->_getParam('id');
        if (empty($recordType) || empty($recordId)) {
            $flashMessenger->addMessage(__('Record not selected.'), 'error');
        }

        $recordType = Inflector::classify($recordType);
        $recordId = (integer) $recordId;

        $record = get_record_by_id($recordType, $recordId);
        if (!empty($record)) {
            $flashMessenger->addMessage(__('This record exists and cannot be undeleted!'), 'error');
            return $this->redirect(Inflector::tableize($recordType) . '/show/' . $recordId);
        }

        // Check if it is loggable.
        if (!$this->_isLoggable($recordType)) {
            $flashMessenger->addMessage(__('This record "%s #%d" is not loggable and cannot be undeleted!', $recordType, $recordId), 'error');
            return $this->redirect('history-log');
        }

        // Try to undelete it.
        // Check if the last operation is a deletion.
        $logEntry = $this->_helper->_db->getTable('HistoryLogEntry')
            ->getLastEntryForRecord(array(
                    'record_type' => $recordType,
                    'record_id' => $recordId,
                ), HistoryLogEntry::OPERATION_DELETE);
        if (empty($logEntry)) {
            $flashMessenger->addMessage(__('The deletion of the record "%s #%d" has not been logged and cannot be undeleted!', $recordType, $recordId), 'error');
            $url = Inflector::tableize($recordType) . '/log/' . $recordId;
            return $this->redirect($url);
        }

        // Rebuild the item.
        $undeletedRecord = $logEntry->undeleteRecord();
        if (empty($undeletedRecord)) {
            $flashMessenger->addMessage(__('The undeletion of the record "%s #%d" failed.', $recordType, $recordId), 'error');
            $url = Inflector::tableize($recordType) . '/log/' . $recordId;
            return $this->redirect($url);
        }

        $msg = __('The record "%s #%d" is recovered (metadata only)!', $recordType, $recordId)
            . ' ' . __('See Omeka logs for possible notices.');
        $flashMessenger->addMessage($msg, 'success');

        return $this->redirect(Inflector::tableize($recordType) . '/show/' . $recordId);
    }

    /**
     * Quickly check if a record is loggable (item, collection, file).
     *
     * @param string $recordType
     * @return boolean
     */
    protected function _isLoggable($recordType)
    {
        return in_array($recordType, array('Item', 'Collection', 'File'));
    }

}
