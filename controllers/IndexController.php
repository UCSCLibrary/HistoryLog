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
        $export = $this->_getParam('export');

        if ($export) {
            // Return all results without pagination ("00" removes it).
            $this->setParam('per_page', '00');
        }

        parent::browseAction();

        $this->view->params = $this->getAllParams();

        if ($export) {
            if (empty($this->view->total_results)) {
                $flashMessenger = $this->_helper->FlashMessenger;
                $flashMessenger->addMessage(__('Your request returns no result.'), 'error');
                $flashMessenger->addMessage(__('The form has been reset.'), 'success');
                $this->_helper->redirector('search');
                return;
            }

            $response = $this->getResponse();

            $headers = array();
            $headers[] = __('Date');
            $headers[] = __('Type');
            $headers[] = __('Id');
            $headers[] = __('Title');
            $headers[] = __('Part Of');
            $headers[] = __('User');
            $headers[] = __('Action');
            $headers[] = __('Details');
            $this->view->headers = $headers;

            $this->view->generator = $this->_getGenerator();

            // Prepare the export if needed.
            switch ($export) {
                case 'csv':
                    $response
                        ->setHeader('Content-Disposition',
                            'attachment; filename=Omeka_History_Log_' . date('Ymd-His') . '.csv')
                        ->setHeader('Content-type', 'text/csv');
                    $this->render('browse-csv');
                    break;

                case 'fods':
                    $response
                        ->setHeader('Content-Disposition',
                            'attachment; filename=Omeka_History_Log_' . date('Ymd-His') . '.fods')
                        ->setHeader('Content-type', 'text/xml');
                    $this->render('browse-fods');
                    break;
            }
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

    /**
     * Return the generator of the OpenDocument.
     *
     * @return string
     */
    protected function _getGenerator()
    {
        $iniReader = new Omeka_Plugin_Ini(PLUGIN_DIR);
        $path = basename(dirname(dirname(__FILE__)));
        $generator = sprintf('Omeka/%s - %s/%s [%s] (%s)',
            OMEKA_VERSION,
            $iniReader->getPluginIniValue($path, 'name'),
            $iniReader->getPluginIniValue($path, 'version'),
            $iniReader->getPluginIniValue($path, 'author'),
            $iniReader->getPluginIniValue($path, 'link'));
        return $generator;
    }
}
