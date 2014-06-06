<?php
/**
 * HistoryLog log retrieval helper
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * History logging helper class for access from other plugins
 *
 * @package HistoryLog\Helper
 */
class HistoryLog_Helper_Log
{
  /**
   *Retrieve log information for a given Omeka item
   *
   *@param int $itemID The ID of the omeka item whose logs to retrieve
   *@param int $max The maximum number of log entries to retrieve
   *@return array $logs An array representation of the log data
   */
  public static function GetItemLog($itemID,$max)
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
      $statement = $db->query($query);

      $logs = array();
      while($row = $statement->fetch()) {
	$logItem['type']=$row['type'];
	$logItem['time']=$row['time'];
	$logItem['user']=self::_getUsername($row['userID']);
	$logItem['value']=self::_getValue($row['type'],$row['value']);	
	$logs[]=$logItem;
      }
      return $logs;
  }

  /**
   * Retrieve username of an omeka user by user ID
   *
   *@param int $userID The ID of the Omeka user
   *@return string $username The username of the Omeka user
   */
  private static function _getUsername($userID)
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
  private static function _getValue($type,$dbValue)
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