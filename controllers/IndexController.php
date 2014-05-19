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
 
  public function reportsAction()
  {
    include_once(dirname(dirname(__FILE__))."/forms/CreateReport.php");
    $form = new HistoryLog_Form_Reports();

    if ($this->getRequest()->isPost()
	&& $form->isValid($this->getRequest()->getPost())) {

	  if($this->_is_download())
	    {
	      $this->getResponse()
		->setHeader('Content-Disposition', 'attachment; filename=OmekaLog'.date('Y-m-d').".csv")
		->setHeader('Content-type', 'application/x-pdf');
	      $this->view->report = HistoryLog_Form_Reports::ProcessPost('csv');
	    } else
	    {
	      $this->view->report = HistoryLog_Form_Reports::ProcessPost();
	    }

	}

	$this->view->form = $form;
  }

  private function _is_download()
  {
    if(isset($_REQUEST['submitdownload']))
      return(true);
    else
      return(false);
  }

}
  
