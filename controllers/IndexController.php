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
 
  /**
   *Display the main log report form, process it, 
   *and initiate downloads if necessary.
   *
   *@return void
   */
  public function reportsAction()
  {
    $flashMessenger = $this->_helper->FlashMessenger;

    include_once(dirname(dirname(__FILE__))."/forms/CreateReport.php");
    try{
      $form = new HistoryLog_Form_Reports();
      }catch(Exception $e) {
	$flashMessenger->addMessage("Error rendering log report form","error");  
      }  //end try-catch

    //if valid form submitted
    if ($this->getRequest()->isPost()
	&& $form->isValid($this->getRequest()->getPost())) {

      try{

	//if we're downloading
	if($this->_is_download())
	  {
	    $this->getResponse()
	      ->setHeader('Content-Disposition', 'attachment; filename=OmekaLog'.date('Y-m-d').".csv")
	      ->setHeader('Content-type', 'application/x-pdf');
	    $this->view->download=true;
	    $this->view->report = HistoryLog_Form_Reports::ProcessPost('csv');

	  //if we're displaying
	  } else
	  {
	    $this->view->download=false;
	    $this->view->report = HistoryLog_Form_Reports::ProcessPost();
	  }

      }catch(Exception $e) {
	$flashMessenger->addMessage("Error processing form data. ".$e->getMessage(),"error");  
      }  //end try-catch

    } //end if valid form was submitted
    
    $this->view->form = $form;
  }

  /**
   *Checks whether user requested a downloaded log file
   *
   *@return bool Automatic download if true, html display if false
   */
  private function _is_download()
  {
    if(isset($_REQUEST['csvdownload']) && $_REQUEST['csvdownload'])
      return(true);
    else
      return(false);
  }

}
  
