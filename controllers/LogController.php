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
     * Set up the view for full item reports
     *
     * @return void
     */
    public function showAction()
    {
        $flashMessenger = $this->_helper->FlashMessenger;
        $item = $this->_getParam('item');
        if (empty($item)) {
            $flashMessenger->addMessage('Item not selected.', 'error');
        }
        $this->view->itemId = $item;
    }
}
