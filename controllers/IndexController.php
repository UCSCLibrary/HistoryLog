<?php
/**
 * HistoryLog
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The HistoryLog index controller class.
 *
 * @package HistoryLog
 */
class HistoryLog_IndexController extends Omeka_Controller_AbstractActionController
{
    protected $_browseRecordsPerPage = 100;
    protected $_autoCsrfProtection = true;

    public function init()
    {
        $this->_helper->db->setDefaultModelName('HistoryLogEntry');
    }

    /**
     * The browse action.
     */
    public function browseAction()
    {
        if (!$this->_getParam('sort_field')) {
            $this->_setParam('sort_field', 'added');
            $this->_setParam('sort_dir', 'd');
        }

        // Request for downloading.
        $isCsv = $this->_getParam('csvdownload');
        if ($isCsv) {
            // TODO Add pagination for csv.
            // Return all results without pagination ("00" removes it).
            $this->setParam('per_page', '00');
        }

        parent::browseAction();

        // Request for downloading.
        if ($isCsv) {
            // Prepare the download.
            $response = $this->getResponse();
            $response
                ->setHeader('Content-Disposition',
                    'attachment; filename=Omeka_History_Log_' . date('Ymd-His') . '.csv')
                ->setHeader('Content-type', 'text/csv');

            // The response is rendered via the "browse-csv" script.
            $this->render('browse-csv');
        }
    }

    /**
     * This shows the search form for records by going to the correct URI.
     *
     * @return void
     */
    public function searchAction()
    {
        include_once dirname(dirname(__FILE__))
            . DIRECTORY_SEPARATOR . 'forms'
            . DIRECTORY_SEPARATOR . 'Search.php';
        $form = new HistoryLog_Form_Search();

        // Prepare the form to return result in the browse view with pagination.
        $form->setAction(url(array(
            'module' => 'history-log',
            'controller' => 'index',
            'action' =>'browse',
        )));
        // The browse method requires "get" to process the query.
        $form->setMethod('get');

        $this->view->form = $form;
    }
}
