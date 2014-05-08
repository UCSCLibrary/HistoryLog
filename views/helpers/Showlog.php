<?php
/**
 * HistoryLog
 *
 */

/**
 * @package HistoryLog\View\Helper
 */

class HistoryLog_View_Helper_Showlog extends Zend_View_Helper_Abstract
{
  public function showlog($itemID,$max=5)
    {
      $db = get_db();

      $query = "
                 SELECT userID,type,value,time 
                 FROM $db->ItemHistoryLog 
                 WHERE itemID = \"$itemID\"
                 ORDER BY id DESC ";


      if($max>0)
	$query .= "LIMIT $max";

      $query .= ";";

      //die($query);

      $statement = $db->query($query);
      $rows = $statement->fetchAll();

      //print_r($result);
      //die();
      
      $markup = "";

      /*
	$this->view->partial('scripts/itemLog.php',array(
					 'showElementSetHeadings'=>true,
					 'setName' => 'Logs',
					 'elementName' => 'Item curation history',
					 'rows' => $rows
					 ));
      */

      $showElementSetHeadings=true;
      $setName = 'Logs';
      $elementName = 'Item curation history';
      if(!empty($rows))
	  {
	    ob_start();
    ?>  
<div class="element-set">

  <?php if ($showElementSetHeadings): ?>
    <h2><?php echo html_escape(__($setName)); ?></h2>
  <?php endif; ?>


    <div id="<?php echo text_to_id(html_escape("$setName $elementName")); ?>" class="element">
    <h3><?php echo html_escape(__($elementName)); ?></h3>

    <div class="element-text">

      <table>
         <tr>
            <td><strong>Time</strong></td>
            <td><strong>User</strong></td>
            <td><strong>Action</strong></td>
            <td><strong>Details</strong></td>
          </tr>						       
        <?php foreach($rows as $row):?>

 	  <?php 
            $username = $this->_getUsername($row['userID']);
	    $value = $this->_getValue($row['type'],$row['value']);
	  ?>

          <tr>
            <td><?php echo($row['time']) ?></td>
            <td><?php echo($username) ?></td>
            <td><?php echo($row['type']) ?></td>
            <td><?php echo($value) ?></td>
          </tr>
        <?php endforeach; 
          if($max>0 && count($rows) >= $max)
	    {
	      ?><tr><td>
	      <a href="<?php echo($this->view->url('history-log/log/show',array('item'=>$itemID)));?>">
	        <strong>See more</strong>
	      </a>
	      </td></tr><?php
	    }


         ?>

							
      </table

    </div><!-- end element-text -->
  </div><!-- end element -->
</div><!-- end element-set -->
</div><!-- end content area -->
<?php
        $markup .= ob_get_clean();
      }

      return $markup;
    }

    private function _getUsername($userID)
      {
	$user = get_record_by_id('User',$userID);
	return( $user->name ." (".$user->username.")");
      }

    private function _getValue($type,$dbValue)
      {
        switch($type)
	  {
	  case 'created':
	    if(isset($dbValue) && !empty($dbValue))
	      return('Imported from '.$dbValue);
	    else
	      return('Created manually by user');
	    break;

	  case 'updated':
	    $rv = "Elements altered: ";

	    $flag = false;
	    foreach(unserialize($dbValue) as $elementID)
	      {
	        if($flag)
	          $rv.=", ";
	        $flag=true;
	        $element = get_record_by_id('Element',$elementID);
	        $rv.=$element->name;
	      }
	    return($rv);
	    break;

	  case 'exported':
	    if(isset($dbValue) && !empty($dbValue))
	      return('Exported to: '.$dbValue);
	    else 
	      return('');
	    break;

	  case 'deleted':
	    return('');
	    break;
	  }
	    
      }

}