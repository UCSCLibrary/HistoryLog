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
     * Create html with log information for a given item.
     *
     * @param Item|int $item The item or its id to retrieve info from. It may be
     * deleted.
     * @param int $limit The maximum number of log entries to retrieve.
     * @return string An html table of requested log information.
     */
    public function showlog($item, $limit = 5)
    {
        $itemId = is_object($item) ? $item->id : $item;

        $params = array(
            'item_id' => $itemId,
        );

        $markup = '';

        $logEntries = $this->_table->findBy($params, $limit);
        if (!empty($logEntries)) {
            $markup = $this->view->partial('common/showlog.php', array(
                'itemId' => $itemId,
                'limit' => $limit,
                'logEntries' => $logEntries,
            ));
        }

        return $markup;
    }
}
