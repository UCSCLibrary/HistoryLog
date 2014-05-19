<?php
/**
 * History Log
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


/**
 * History Log plugin.
 */
class HistoryLogPlugin extends Omeka_Plugin_AbstractPlugin
{
  private $_changedElements;

    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
			      'install',
			      'uninstall',
			      'after_save_item',
			      'before_save_item',
			      'define_acl',
			      'after_delete_item',
			      'admin_items_show',
			      'admin_head',
			      'initialize'
			      );
  
    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array('admin_navigation_main');

    public function hookAdminHead()
    {
      queue_js_file('HistoryLog');
      queue_css_file('HistoryLog');
    }

    /**
     * Define the plugin's access control list.
     */
    public function hookDefineAcl($args)
    {
      $args['acl']->addResource('HistoryLog_Index');
    }

    /**
     * Add the History Log link to the admin main navigation.
     * 
     * @param array Navigation array.
     * @return array Filtered navigation array.
     */
    public function filterAdminNavigationMain($nav)
    {
      $nav[] = array(
		     'label' => __('Item History Logs'),
		     'uri' => url('history-log/index/reports'),
		     'resource' => 'HistoryLog_Index',
		     'privilege' => 'index'
		     );
      return $nav;
    }

    
    public function hookInstall()
    {

        $db = get_db();

        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->ItemHistoryLog` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `title` text,
                `itemID` int(10) NOT NULL,
                `userID` int(10) NOT NULL,
                `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `type` text,
                `value` text,
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        $db->query($sql);
    }

    public function hookUninstall()
    {
      $db = get_db();
      $sql = "DROP TABLE IF EXISTS `$db->ItemHistoryLog` ";
      $db->query($sql);

    }

    public function hookInitialize()
    {
      //get_view()->addHelperPath(dirname(__FILE__) . '/views/helpers', 'HistoryLog_View_Helper_');
    }

    public function hookBeforeSaveItem($args)
    {
      $item = $args['record'];
      //if it's not a new item, check for changes
      if( !isset($args['insert']) || !$args['insert'] )
	{
	  $changedElements = $this->_findChanges($item);

	  //log item update for each changed elements
	  $this->_logItemUpdate($item->id,$changedElements);
	}
    }

    public function hookAfterSaveItem($args)
    {
      $item = $args['record'];
      if( isset($args['insert']) && $args['insert'] )
	{
	  //log new item
	  $this->_logItemCreation($item->id);
	} 
    }

    public function hookAfterDeleteItem($args)
    {
      $item = $args['record'];
      $this->_logItemDeletion($item->id);
    }

    public function hookAdminItemsShow($args)
    {
      
      $item = $args['item'];
      $view = $args['view'];
      echo($view->showlog($item->id,5));
    }
    
    private function _logItemCreation($itemID,$source="")
    {
      $this->_logItem($itemID,'created',$source);
    }

    private function _logItemUpdate($itemID,$elements)
    {
      $this->_logItem($itemID,'updated',serialize($elements));
    }

    private function _logItemDeletion($itemID)
    {
      $this->_logItem($itemID,'deleted',NULL);
    }

    private function _logItemExport($itemID,$context)
    {
      $this->_logItem($itemID,'exported',$context);
    }

    private function _logItem($itemID,$type,$value)
    {
      $currentUser = current_user();

      if(is_null($currentUser))
	die('ERROR');

      $values = array (
		       'itemID'=>$itemID,
		       'title'=>$this->_getTitle($itemID),
		       'userID' => $currentUser->id,
		       'type' => $type,
		       'value' => $value
		       );
      $db = get_db();
      $db->insert('ItemHistoryLog',$values);
    }

    private function _findChanges($item)
    {
      $newElements = $item->Elements;
      $changedElements = array();
      $oldItem = get_record_by_id('Item',$item->id);

      foreach ($newElements as $newElementID => $newElementTexts)
	{
	  $flag=false;

	  $element = get_record_by_id('Element',$newElementID);
	  $oldElementTexts =  $oldItem->getElementTextsByRecord($element);
	  
	  $oldETextsArray = array();
	  foreach($oldElementTexts as $oldElementText)
	    {
	      $oldETextsArray[] = $oldElementText['text'];
	    }
	  
	  $i = 0;
	  foreach ($newElementTexts as $newElementText)
	    {
	      if($newElementText['text'] !== "")
		{
		  $i++;
		  
		  if(!in_array($newElementText['text'],$oldETextsArray))
		    $flag=true;
		}
	    }
	  if($i !== count($oldETextsArray))
	    $flag=true;

	  if($flag)
	    $changedElements[]=$newElementID;
	  
	}
      
      return $changedElements;
    }

    private function _getTitle($itemID)
    {
      $item = get_record_by_id('Item',$itemID);
      $titles = $item->getElementTexts("Dublin Core","Title");
      if(isset($titles[0]))
	$title = $titles[0];
      else
	$title = "untitled / title unknown";

      return $title;
    }


}
