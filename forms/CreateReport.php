<?php

/**
 * @package     HistoryLog
 * @copyright   2014 UCSC Library Digital Initiatives
 * @license     
 */

class HistoryLog_Form_Reports extends Omeka_Form
{



    /**
     * Construct the report generation form.
     */
    public function init()
    {
        parent::init();
        $this->_registerElements();
	
    }

    /**
     * Define the form elements.
     */
    private function _registerElements()
    {

        // Collection:
        $this->addElement('select', 'collection', array(
            'label'         => __('Collection'),
            'description'   => __('The collection whose items\' log information will be retrieved (default: all)'),
            'value'         => '0',
	    'order'         => 1,
            'required'      => true,
	    'multiOptions'       => $this->_getCollectionOptions()
							)
			  );

        // User(s):
        $this->addElement('select', 'user', array(
						       'label'         => __('User(s)'),
						       'description'   => __('All administrator users whose edits will be retrieved (default: all)'),
						       'value'         => '0',
						       'order'         => 2,
						       'required'      => true,
						       'multiOptions'       => $this->_getUserOptions()
						       )
	  );

	// Actions:
        $this->addElement('select', 'action', array(
							  'label'         => __('Action'),
							  'description'   => __('Logged curatorial actions to retrieve in this report (default: all)'),
							  'value'         => '0',

							  'order'         => 3,
							  'required'      => true,
							  'multiOptions'  => $this->_getActionOptions()
							  )
			  );

	// Dates:
        $this->addElement('text', 'date-start', array(
							  'label'         => __('Start Date:'),
							  'description'   => __('The earliest date from which to retrieve logs'),
							  'value'         => 'YYYY-MM-DD',
							  'order'         => 4,
							  'style'          => '    max-width: 120px;',
							  'required'      => false
							  )
			  );
	$this->addElement('text', 'date-end', array(
						      'label'         => __('End Date:'),
						      'description'   => __('The latest date from which to retrieve logs'),
						      'value'         => 'yyyy-mm-dd',
						      'order'         => 5,'style'          => '    max-width: 120px;',
						      'required'      => false
						      )
			  );

        // Submit:
        $this->addElement('submit', 'submit-view', array(
            'label' => __('View Log')
        ));
        $this->addElement('submit', 'submit-download', array(
            'label' => __('Download Log')
        ));

	//Display Groups:
        $this->addDisplayGroup(
			       array(
				     'collection',
				     'user',
				     'actions',
				     'date-start',
				     'date-end'
				     ),
			       'fields'
			       );

        $this->addDisplayGroup(
			       array(
				     'submit-view',
				     'submit-download'
				     ), 
			       'submit_buttons',
			       array(
				     'style'=>'clear:left;'
				     )
			       );

    }

    public static function ProcessPost($style="html")
    {
      $dB = get_db();

      //$itemID = $_POST[''];
      $log="";
      $action = '%';
      $userID = '%';
      $timeStart = '1900-00-00';
      $timeEnd = '2100-00-00';

      if(isset($_REQUEST['action']))
	{

	  $itemID = '%';
	  if(!empty($_REQUEST['action']))
	    $action = $_REQUEST['action'];
	  if(!empty($_REQUEST['user']))
	    $userID = $_REQUEST['user'];
	  if(!empty($_REQUEST['datestart']) && $_REQUEST['datestart'] != "yyyy-mm-dd")
	    $timeStart = $_REQUEST['datestart'];
	  if(!empty($_REQUEST['dateend']) && $_REQUEST['dateend'] != "yyyy-mm-dd")
	    $timeEnd = $_REQUEST['dateend'];

	 
	  $query = 'SELECT id,title,itemID,userID,type,value,time FROM omeka_item_history_logs WHERE itemID LIKE "'.$itemID.'" AND type LIKE "'.$action.'" AND userID LIKE "'.$userID.'" AND time > "'.$timeStart.'" AND time < "'.$timeEnd.'";';

	  $result = $dB->query($query);
	  $rows = $result->fetchAll();

	  if($style == 'html')
	    {
	      $logStart = "<table><tr style=\"font-weight:bold\"><td>Item Title</td><td>User</td><td>Action</td><td>Details</td><td>Date</td></tr>";
	      $rowStart = "<tr><td>";
	      $colSep = "</td><td>";
	      $rowEnd = "</td></tr>";
	      $logEnd = "</table>";
	    } else if ($style == "csv")
	    {
	      $logStart = "";
	      $rowStart = "";
	      $colSep = ",";
	      $rowEnd = PHP_EOL;
	      $logEnd = "";
	    }

	  $log .= $logStart;
	  foreach($rows as $row)
	    {
	      $log.= $rowStart;
	      //$log.=self::_getItem($row['itemID']);
	      $log.=$row['title'];
	      $log.=$colSep;
	      $log.=self::_getUser($row['userID']);
	      $log.=$colSep;
	      $log.=self::_getAction($row['type']);
	      $log.=$colSep;
	      $log.=self::_getValue($row['value'],$row['type']);
	      $log.=$colSep;
	      $log.=self::_getDate($row['time']);
	      $log.=$rowEnd;

	    }
	  $log.=$logEnd;

	}
      return($log);
    }

    private function _getActionOptions()
    {
      return( 
	     array(
		   0=>'All Actions',
		   'created'=>'Create Item',
		   'updated'=>'Modify Item',
		   'exported'=>'Export Item',
		   'deleted'=>'Delete Item'
		   )
	      );
    }

    private function _getCollectionOptions()
    {
      $collections = get_records('Collection',array(),'0');
      $options = array('0'=>'All Collections');
      foreach ($collections as $collection)
	{
	  $titles = $collection->getElementTexts('Dublin Core','Title');
	  if(isset($titles[0]))
	    $title = $titles[0];
	  $options[$collection->id]=$title;
	}

      return $options;
    }

    private function _getUserOptions()
    {
      $options = array('0'=>'All Users');

      $users = get_records('User',array('role'=>'super'),'0');
      foreach($users as $user)
	{
	  $options[$user->id]=$user->name." (super user)";
	}
      return($options);

      $users = get_records('User',array('role'=>'admin'),'0');
      foreach($users as $user)
	{
	  $options[$user->id]=$user->name." (administrator)";
	}
      return($options);

      $users = get_records('User',array('role'=>'contributor'),'0');
      foreach($users as $user)
	{
	  $options[$user->id]=$user->name." (contributor)";
	}
      return($options);
      
    }

    private static function _getItem($itemID)
    {
      $item = get_record_by_id('Item',$itemID);
      if(empty($item))
	return('Item #'.$itemID.' not found');
      $titles = $item->getElementTextsByRecord($item->getElement('Dublin Core','Title'));
      if(!empty($titles))
	$title = $titles[0];
      else
	$title  = "Untitled";

      return $title;
    }

    private static function _getUser($userID)
    {
      $user = get_record_by_id('User',$userID);
      if(empty($user))
	return('cannot find user');
      return $user->name;
    }

    private static function _getAction($actionSlug)
    {
      switch($actionSlug)
	{
	case 'deleted':
	  return('Item Deleted');
	case 'created':
	  return('Item Created');
	case 'updated':
	  return('Item Modified');
	case 'exported':
	  return('Item Exported');
	default:
	  return($actionSlug);
	}
    }

    private static function _getValue($encodedValue,$actionSlug)
    {
      switch($actionSlug)
	{
	case 'deleted':
	case 'created':
	  return null;
	case 'updated':
	  $rv = 'Metadata elements modified: ';
	  $flag = false;
	  foreach(unserialize($encodedValue) as $elementID)
	    {
	      if($flag)
		$rv.=", ";
	      else
		$flag = true;
	      $element = get_record_by_id('Element',$elementID);
	      $rv.=$element->name;
	    }
	  return $rv;
	case 'exported':
	  return('Exported to '.$encodedValue);
	}
      
    }

    private static function _getDate($dateTime)
    {
      return $dateTime;
    }

}
