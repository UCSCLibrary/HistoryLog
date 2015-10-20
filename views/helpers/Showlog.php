<?php
/**
 * HistoryLog full item log show page
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * History log view helper
 * @package HistoryLog\View\Helper
 */

class HistoryLog_View_Helper_Showlog extends Zend_View_Helper_Abstract
{
  /**
     * Create html with log information for a given item
     * 
     * @param int $itemID The ID of the item to retrieve info from.
     * @param int $max The maximum number of log entries to retrieve
     * @return string $html An html table of requested log information
     */
    public function showlog($itemID,$limit=5)
    {
      $params = array(
          'itemID'=>$itemID
      );

      $logEntries = get_db()->getTable('HistoryLogEntry')->findBy($params,$limit);
      $markup = "";

      $showElementSetHeadings=true;
      $elementName = 'Log';
      $setName = 'Item Curation History';
      //$elementName = 'Item '.$itemID.": ".$this->_getTitle($itemID);

      if(!empty($logEntries))
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
        <?php foreach($logEntries as $logEntry):?>

 	  <?php 
            $username = $this->_getUsername($logEntry->userID);
	    $value = $this->_getValue($logEntry->type,$logEntry->value);
	  ?>

          <tr>
            <td><?php echo($logEntry->time) ?></td>
            <td><?php echo($username) ?></td>
            <td><?php echo($logEntry->type) ?></td>
            <td><?php echo($value) ?></td>
          </tr>
        <?php endforeach; 
          if($limit>0 && count($logEntry) >= $limit)
	    {
	      ?><tr><td>
	      <a href="<?php 
echo($this->view->url('history-log/log/show/item/'.$itemID));
?>">
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

    /**
     * Retrieve username of an omeka user by user ID
     *
     *@param int $userID The ID of the Omeka user
     *@return string $username The username of the Omeka user
     */
    private function _getUsername($userID)
      {
	$user = get_record_by_id('User',$userID);
	if(empty($user))
	  return('cannot find user');
	return( $user->name ." (".$user->username.")");
      }

    /**
     * Retrieve "value" parameter in user displayable form
     *
     *@param string $type the slug of the type of action
     *associated with this value parameter
     *@param string $dbValue The "value" parameter 
     *directly from the database
     *@return string $value The value is human readable form.
     */
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
		$update = unserialize($dbValue);
		if(empty($update)) {
			$rv = "File upload/edit";
			return($rv);
		}
	    $rv = "Elements altered: ";

	    $flag = false;
	    foreach($update as $elementID)
	      {
	        if($flag)
	          $rv.=", ";
	        $flag=true;
	        $element = get_record_by_id('Element',$elementID);
	        $rv.=$element->name;
	      }
	    return($rv);
	   

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

    /**
     * Retrieves the title of an item by itemID
     * 
     * @param int $itemID The id of the item to log
     * @return string $title The Dublin Core title of the item.
     */
    private function _getTitle($itemID)
    {
      if(!is_numeric($itemID))
	throw new Exception('Could not retrieve Item ID');
      $item = get_record_by_id('Item',$itemID);
      $titles = $item->getElementTexts("Dublin Core","Title");
      if(isset($titles[0]))
	$title = $titles[0];
      else
	$title = "untitled / title unknown";

      return $title;
    }

}
