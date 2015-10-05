<?php
/**
 * Item History Log
 *
 * This Omeka 2.0+ plugin logs curatorial actions such as adding,
 * deleting, or modifying items.
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 *
 * @package HistoryLog
 */


/**
 * History Log plugin class
 *
 * @package HistoryLog
 */
class HistoryLogPlugin extends Omeka_Plugin_AbstractPlugin
{
    
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
	'install',
	'uninstall',
	'after_save_item',
	'before_save_item',
	'define_acl',
	'before_delete_item',
	'admin_items_show',
	'admin_head',
	'export',
    );
    
    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array('admin_navigation_main');

    /**
     * Define the plugin's access control list.
     *
     * @param array $args Parameters supplied by the hook
     * @return void
     */
    public function hookDefineAcl($args)
    {
        $args['acl']->addResource('HistoryLog_Index');
    }

    /**
     * Load the plugin javascript when admin section loads
     *
     * @return void
     */
    public function hookAdminHead()
    {
        queue_js_file('HistoryLog');
    }

    /**
     * Add the History Log link to the admin main navigation.
     * 
     * @param array $nav Navigation array.
     * @return array $filteredNav Filtered navigation array.
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

    /**
     * When the plugin installs, create the database tables 
     *to store the logs
     * 
     * @return void
     */
    public function hookInstall()
    {

        try{
	    $sql = "
            CREATE TABLE IF NOT EXISTS `{$this->_db->HistoryLogEntry}` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `title` text,
                `itemID` int(10) NOT NULL,
                `collectionID` int(10) NOT NULL,
                `userID` int(10) NOT NULL,
                `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `type` text,
                `value` text,
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
	    $this->_db->query($sql);
        }catch(Exception $e) {
	    throw $e; 
        }

    }

    /**
     * When the plugin uninstalls, delete the database tables 
     *which store the logs
     * 
     * @return void
     */
    public function hookUninstall()
    {
        try{
	    $db = get_db();
	    $sql = "DROP TABLE IF EXISTS `$db->HistoryLogEntry` ";
	    $db->query($sql);
        }catch(Exception $e) {
	    throw $e;	
        }
    }

    /**
     * When an item is saved, determine whether it is a new item
     * or an item update. If it is an update, log the event.
     * Otherwise, wait until after the save.
     * 
     *@param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookBeforeSaveItem($args)
    {
        $item = $args['record'];
        //if it's not a new item, check for changes
        if( empty($args['insert']) )
	{
	    try{
	        $changedElements = $this->_findChanges($item);

	        //log item update for each changed elements
	        if($this->_findChanges($item)) {
	            $this->_logItem($item,'updated',serialize($changedElements));
	        } else {
	            //TODO still do updates here
	        }
	    }catch(Exception $e) {
	        throw $e;
	    }
	}
    }

    /**
     * When an item is saved, determine whether it is a new item
     * or an item update. If it is a new item, log the event.
     * 
     *@param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];

        $source = "";
        
        if($request = Zend_Controller_Front::getInstance()->getRequest()) {
            if(strpos('nuxeo-link',current_url()))
                $source = "Nuxeo";
            else if(strpos('youtube',current_url()))
                $source = "YouTube";
            else if(strpos('flickr',current_url()))
                $source = "Flickr";
        } else {
            $source = "background script (flickr or nuxeo)";
        }

        //if it's a new item
        if( isset($args['insert']) && $args['insert'] )
	{
	    try{
	        //log new item
	        $this->_logItem($item,'created',$source);
	    }catch(Exception $e) {
	        throw $e;
	    }
	} 
    }

    public function hookExport($args)
    {
        $service = $args['service'];
        foreach($args['records'] as $id=>$value){
	    $item = get_record_by_id('Item',$id);
	    $this->_logItem($item,'exported',$service);
	}
    }

    /**
     * When an item is deleted, log the event.
     * 
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookBeforeDeleteItem($args)
    {
        $item = $args['record'];
        try{
	    $this->_logItem($item,'deleted',null);
        }catch(Exception $e) {
	    throw $e; 
        }
    }


    /**
     * Show the 5 most recent events in the item's history on the
     *item's admin page
     * 
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookAdminItemsShow($args)
    {
        $item = $args['item'];
        $view = $args['view'];

        if(plugin_is_active('ExhibitBuilder')){
            $exhibits = get_db()->getTable('Exhibit')->findAll();
            foreach($exhibits as $exhibit){
                if($exhibit->hasItem($args['item']))
                    echo('<h4 class="appears-in-exhibit">This item appears in the exhibit "'.$exhibit->title.'"</h4>');
            } 
        }
        try{
	    echo($view->showlog($item->id,5));
        }catch(Exception $e) {
	    throw $e; 
        }
    }

    /**
     * Create a new log entry 
     * 
     * @param Object $item The Omeka item to log
     * @param string $type The type of event to log (e.g. "create", "update")
     * @param string $value An extra piece of type specific data for the log
     * @return void
     */
    private function _logItem($item,$type,$value)
    {
        if(is_numeric($item))
            $item = get_record_by_id('Item',$item);
        require_once(dirname(__FILE__).'/models/HistoryLogEntry.php');
        //die('test');
        $logEntry = new HistoryLogEntry();

        $currentUser = current_user();
        
        $collectionID = $item->collection_id;
        if(!isset($collectionID))
	    $collectionID = 0;

        if(is_null($currentUser))
	    throw new Exception('Could not retrieve user info');

        $title="Untitled";
        try{
	    $title=$this->_getTitle($item->id);
        }catch(Exception $e) {
	    throw $e;
        }
        
        $logEntry->itemID = $item->id;
        $logEntry->title = $title;;
        $logEntry->collectionID = $collectionID;
        $logEntry->userID = $currentUser->id;
        $logEntry->type = $type;
        $logEntry->value = $value;

        /*      $values = array (
	   'itemID'=>$item->id,
	   'title'=>$title,
	   'collectionID'=>$collectionID,
	   'userID' => $currentUser->id,
	   'type' => $type,
	   'value' => $value
	   );*/
        try{
            $logEntry->save();
            //$db = get_db();
            //$db->insert('ItemHistoryLog',$values);
        }catch(Exception $e) {
	    throw $e;
        }
        
    }

    /**
     * If an item is being updated, find out which elements are being altered 
     * 
     * @param Object $item The updated omeka item record
     * @return array $changedElements An array of element IDs of altered elements
     */
    private function _findChanges($item)
    {
        if(!isset($item->Elements))
	    return false;
        $newElements = $item->Elements;
        
        $changedElements = array();
        try{
	    $oldItem = get_record_by_id('Item',$item->id);
        }catch(Exception $e) {
	    throw $e;
        }
        

        foreach ($newElements as $newElementID => $newElementTexts)
	{
	    $flag=false;
	    
	    try{
	        $element = get_record_by_id('Element',$newElementID);
	        $oldElementTexts =  $oldItem->getElementTextsByRecord($element);
	    }catch(Exception $e) {
	        throw $e; 	
	    }	  	  	  

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

        try{
	    $item = get_record_by_id('Item',$itemID);
	    $titles = $item->getElementTexts("Dublin Core","Title");
        }catch(Exception $e) {
	    throw $e;
        }

        if(isset($titles[0]))
	    $title = $titles[0];
        else
	    $title = "untitled / title unknown";

        return $title;
    }


}
