<?php
/**
 * HistoryLog full item log show page
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * History log view helper
 *
 * @package HistoryLog\View\Helper
 */
class HistoryLog_View_Helper_Showlog extends Zend_View_Helper_Abstract
{
    protected $_table;

    /**
     * Load the hit table one time only.
     */
    public function __construct()
    {
        $this->_table = get_db()->getTable('HistoryLogEntry');
    }

    /**
     * Create html with log information for a given record.
     *
     * @param Record|array $record The record to retrieve info from.  It may be
     * deleted.
     * @param int $limit The maximum number of log entries to retrieve.
     * @return string An html table of requested log information.
     */
    public function showlog($record, $limit = 10)
    {
        $markup = '';
        $params = array();
        if (is_object($record)) {
            $params['record_type'] = get_class($record);
            $params['record_id'] = $record->id;
        }
        // Check array too.
        elseif (is_array($record) && isset($record['record_type']) && $record['record_id']) {
            $params['record_type'] = Inflector::classify($record['record_type']);
            $params['record_id'] = (integer) $record['record_id'];
        }
        // No record.
        else {
            return '';
        }

        // Reverse order because the most needed infos are recent ones.
        $params['sort_field'] = 'added';
        $params['sort_dir'] = 'd';

        $logEntries = $this->_table->findBy($params, $limit);
        if (!empty($logEntries)) {
            $markup = $this->view->partial('common/showlog.php', array(
                'record_type' => $params['record_type'],
                'record_id' => $params['record_id'],
                'limit' => $limit,
                'logEntries' => $logEntries,
            ));
        }

        return $markup;
    }
}
