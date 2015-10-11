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
     * The browse collections action.
     *
     */
    public function browseAction()
    {
        if (!$this->_getParam('sort_field')) {
            $this->_setParam('sort_field', 'added');
            $this->_setParam('sort_dir', 'd');
        }

        parent::browseAction();
    }

    /**
     * Display the main log report form, process it, and initiate downloads if
     * necessary.
     *
     * @return void
     */
    public function reportsAction()
    {
        $flashMessenger = $this->_helper->FlashMessenger;

        include_once(dirname(dirname(__FILE__)) . '/forms/CreateReport.php');
        try {
            $form = new HistoryLog_Form_Reports();
        } catch (Exception $e) {
            $flashMessenger->addMessage(__('Error rendering log report form.'), 'error');
        }

        // If valid form submitted.
        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            try {
                // If we're downloading.
                if ($this->_isDownload()) {
                    $this->getResponse()
                        ->setHeader('Content-Disposition',
                            'attachment; filename=OmekaLog' . date('Y-m-d') . '.csv')
                        ->setHeader('Content-type', 'application/x-pdf');
                    $this->view->download = true;
                    $this->view->report = HistoryLog_Form_Reports::ProcessPost('csv');

                }
                // If we're displaying.
                else {
                    $this->view->download = false;
                    $this->view->report = HistoryLog_Form_Reports::ProcessPost();
                }
            } catch (Exception $e) {
                $flashMessenger->addMessage(__('Error processing form data.') . ' ' . $e->getMessage(), 'error');
            }
        }

        $this->view->form = $form;
    }

    /**
     * Checks whether user requested a downloaded log file.
     *
     * @return bool Automatic download if true, html display if false.
     */
    private function _isDownload()
    {
        return isset($_REQUEST['csvdownload']) && $_REQUEST['csvdownload'];
    }
}
