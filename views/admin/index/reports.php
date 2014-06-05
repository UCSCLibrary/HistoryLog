<?php

if(isset($_REQUEST['submitdownload']))
  {
    echo $report; 
  } else {

    $head = array('bodyclass' => 'history-log primary', 
		'title' => html_escape(__('History Log | View Log Report')));
    echo head($head);
    echo flash(); 
    echo $form; 
    if(isset($report))
      echo $report; 

      echo foot(); 
    }
?>