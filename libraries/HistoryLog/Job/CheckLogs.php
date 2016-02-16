<?php
/**
 * Check and create missing logs for current records.
 *
 * @todo Divide sql queries in multiple sub-jobs.
 * @todo Check deleted record without log for deletion.
 */
class HistoryLog_Job_CheckLogs extends Omeka_Job_AbstractJob
{
    const QUEUE_NAME = 'history_log_check_logs';

    protected $_db;
    protected $_recordType;
    protected $_operation;
    protected $_limit = 100;

    public function perform()
    {
        $this->_db = get_db();

        $this->_log(__('Launch creation of log entries "%s" for "%s" (max: %d, memory: %d).',
            ucfirst($this->_operation), $this->_recordType, $this->_limit, memory_get_usage()));

        $this->_checkLogs();

        $this->_log(__('End of creation of log entries "%s" for "%s" (max: %d, memory: %d).',
            ucfirst($this->_operation), $this->_recordType, $this->_limit, memory_get_usage()));
    }

    /**
     * Check logs and launch creation when needed.
     */
    protected function _checkLogs()
    {
        $db = $this->_db;
        $recordType = $this->_recordType;
        $operation = $this->_operation;
        $limit = $this->_limit;

        $missingRecordsIds = $this->_getLogsForRecordType($recordType, $operation, $limit);
        if (empty($missingRecordsIds)) {
            $this->_log(__('All records "%s" are logged.', $recordType));
            return;
        }

        $this->_log(__('Launch process for %d records "%s".', count($missingRecordsIds), $recordType));
        foreach ($missingRecordsIds as $key => $recordId) {
            $logEntry = new HistoryLogEntry();
            $result = $logEntry->rebuildEntry(array('record_type' => $recordType, 'record_id' => $recordId), $operation);
            if ($result) {
                $logEntry->save();
                $this->_log(__('Entry "%s" for record "%s #%d" has been created (%d/%d).',
                    $operation, $recordType, $recordId, $key + 1, count($missingRecordsIds)));
            }
            // Error.
            else {
                $this->_log(__('Entry failed for record "%s #%d" (%d/%d).',
                    $recordType, $recordId, $key + 1, count($missingRecordsIds)));
            }
            release_object($logEntry);
        }
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
        $db = $this->_db;

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
        $result = $this->_db->fetchCol($sql);

        return $result;
    }

    public function setRecordType($recordType)
    {
        $this->_recordType = (string) $recordType;
    }

    public function setOperation($operation)
    {
        $this->_operation = (string) $operation;
    }

    public function setLimit($limit)
    {
        $this->_limit = (integer) $limit;
    }

    /**
     * Log a message with generic info.
     *
     * @param string $msg The message to log
     * @param int $priority The priority of the message
     */
    protected function _log($msg, $priority = Zend_Log::INFO)
    {
        $prefix = "[HistoryLog][CheckLogs]";
        _log("$prefix $msg", $priority);
    }
}
