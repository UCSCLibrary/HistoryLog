<?php
/**
 * The HistoryLog admin controller class.
 *
 * @todo Divide sql queries in multiple sub-jobs.
 *
 * @package HistoryLog
 */
class HistoryLog_AdminController extends Omeka_Controller_AbstractActionController
{
    protected $_limit = 100;

    public function init()
    {
        $this->_helper->db->setDefaultModelName('HistoryLogEntry');
    }

    public function checkAction()
    {
        $db = $this->_helper->db;
        $limit = $this->_limit;
        $flashMessenger = $this->_helper->FlashMessenger;

        $recordTypes = array('Collection', 'Item', 'File');

        $result = array();
        $totalRecords = array();
        foreach ($recordTypes as $recordType) {
            $totalRecords[$recordType] = total_records($recordType);
            foreach (array(
                    HistoryLogEntry::OPERATION_CREATE,
                    HistoryLogEntry::OPERATION_DELETE,
                ) as $operation) {
                $missingRecordsIds = $this->_getLogsForRecordType($recordType, $operation);
                $result[$recordType][$operation] = $missingRecordsIds;
            }
        }

        $this->view->result = $result;
        $this->view->totalRecords= $totalRecords;
    }

    /**
     * Check logs and rebuild part of them.
     */
    protected function rebuildAction()
    {
        $flashMessenger = $this->_helper->FlashMessenger;

        $recordType = ucfirst($this->getParam('type'));
        if (!in_array($recordType, array('Collection', 'Item', 'File'))) {
            $flashMessenger->addMessage(__('The record type should be "Collection", "Item" or "File".'));
            return $this->redirect('history-log/admin/check');
        }

        $operation = strtolower($this->getParam('operation'));
        if (!in_array($operation, array(
                HistoryLogEntry::OPERATION_CREATE,
                HistoryLogEntry::OPERATION_UPDATE,
                HistoryLogEntry::OPERATION_DELETE,
            ))) {
            $flashMessenger->addMessage(__('The operation should be "create" or "delete".'));
            return $this->redirect('history-log/admin/check');
        }

        // Create a log entry for current records without logs.
        $options = array(
            'recordType' => $recordType,
            'operation' => $operation,
            'limit' => $this->_limit,
        );

        $jobDispatcher = Zend_Registry::get('bootstrap')->getResource('jobs');
        $jobDispatcher->setQueueName(HistoryLog_Job_CheckLogs::QUEUE_NAME);
        $jobDispatcher->sendLongRunning('HistoryLog_Job_CheckLogs', $options);
        $message = __('Entries logs "%s" for "%s" are being created (%d max).', $operation, $recordType, $this->_limit)
            . ' ' . __('This may take a while.');
        $flashMessenger->addMessage($message, 'success');
        $flashMessenger->addMessage(__('See Omeka logs to check the end of process.'));

        return $this->redirect('history-log/admin/check');
    }

    /**
     * Get missing logs for a record type.
     *
     * @param string $recordType
     * @param string $operation
     * @return array List of missing ids for the record type.
     */
    protected function _getLogsForRecordType($recordType, $operation, $limit = null)
    {
        $db = get_db();

        switch ($operation) {
            case HistoryLogEntry::OPERATION_CREATE:
            case HistoryLogEntry::OPERATION_UPDATE:
                // TODO This query doesn't check if the operation "create" is
                // the first.
                $sql = "
                    SELECT `records`.`id`
                    FROM  `{$db->$recordType}` AS `records`
                    LEFT JOIN `{$db->HistoryLogEntry}` AS `history_log_entries`
                        ON `history_log_entries`.`record_id` = `records`.`id`
                            AND `history_log_entries`.`record_type` = '$recordType'
                            AND `history_log_entries`.`operation` = '$operation'
                    WHERE `history_log_entries`.`record_id` IS NULL
                    ORDER BY `records`.`id` ASC
                ";
                break;

            case HistoryLogEntry::OPERATION_DELETE:
                // TODO This query doesn't check if the operation "delete" is
                // the last.
                $sql = "
                    SELECT `history_log_entries`.`record_id`
                    FROM `{$db->HistoryLogEntry}` AS `history_log_entries`
                    WHERE `history_log_entries`.`record_type` = '$recordType'
                        AND `history_log_entries`.`record_id` NOT IN (
                            SELECT `records`.`id`
                            FROM `{$db->$recordType}` AS `records`
                        )
                    GROUP BY `history_log_entries`.`record_id`
                    ORDER BY `history_log_entries`.`record_id` ASC;
                ";
                break;

            default:
                return;
        }

        if ($limit) {
            $sql .= " LIMIT $limit;";
        }
        $result = get_db()->fetchCol($sql);

        return $result;
    }
}
