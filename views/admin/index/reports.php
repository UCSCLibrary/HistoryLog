<?php

if(isset($_REQUEST['submitdownload']))
  {
    echo $report; 
  } else {

    $head = array('bodyclass' => 'history-log primary', 
		'title' => html_escape(__('History Log | Create Log Report')));
    echo head($head);
    echo flash(); 
    echo $form; 
    if(isset($report))
      echo $report; 
    ?>
  <script>
    jQuery(document).ready(function() {
	jQuery( "#datestart" ).datepicker();
	jQuery( "#datestart" ).datepicker("option", "dateFormat","yy-mm-dd");
	jQuery( "#dateend" ).datepicker();
	jQuery( "#dateend" ).datepicker("option", "dateFormat","yy-mm-dd");
      });
  </script>
      <?php 

      echo foot(); 
    }
?>