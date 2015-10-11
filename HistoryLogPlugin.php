<?php
/**
 * Item History Log
 *
 * This Omeka 2.0+ plugin logs curatorial actions such as adding, deleting, or
 * modifying items.
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
        'upgrade',
        'uninstall',
        'define_acl',
        'before_save_item',
        'after_save_item',
        'before_delete_item',
        'export',
        'admin_items_show',
        'admin_items_browse_simple_each',
        'admin_head',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_navigation_main',
    );

    /**
     * When the plugin installs, create the database tables to store the logs.
     *
     * @return void
     */
    public function hookInstall()
    {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS `{$this->_db->HistoryLogEntry}` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `item_id` int(10) NOT NULL,
                `collection_id` int(10) NOT NULL,
                `user_id` int(10) NOT NULL,
                `action` enum('created', 'imported', 'updated', 'exported', 'deleted') NOT NULL,
                `change` text collate utf8_unicode_ci NOT NULL,
                `added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `title` text COLLATE utf8_unicode_ci,
                PRIMARY KEY (`id`),
                KEY (`item_id`),
                KEY (`change` (50)),
                KEY (`added`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
            $this->_db->query($sql);
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
     * Upgrade the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];
        $db = $this->_db;

        if (version_compare($oldVersion, '2.4', '<')) {
            // TODO Check if the structure is already upgraded in case of a bug.
            // Reorder columns and change name of columns "type" to "action".,
            // "value" to "change" and "time" to "added".
            $sql = "
                ALTER TABLE `{$db->HistoryLogEntry}`
                CHANGE `itemID` `item_id` int(10) NOT NULL AFTER `id`,
                CHANGE `collectionID` `collection_id` int(10) NOT NULL AFTER `item_id`,
                CHANGE `userID` `user_id` int(10) NOT NULL AFTER `collection_id`,
                CHANGE `type` `action` enum('created', 'imported', 'updated', 'exported', 'deleted') NOT NULL AFTER `user_id`,
                CHANGE `value` `change` text collate utf8_unicode_ci NOT NULL AFTER `action`,
                CHANGE `time` `added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `change`,
                CHANGE `title` `title` text COLLATE utf8_unicode_ci AFTER `added`,
                ADD INDEX (`item_id`),
                ADD INDEX (`change` (50)),
                ADD INDEX (`added`),
                ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ";
            $db->query($sql);

            // Convert serialized values into standard fields.
            // Get current serialized values.
            $table = $db->getTable('HistoryLogEntry');
            $alias = $table->getTableAlias();
            $select = $table->getSelect();
            $select->reset(Zend_Db_Select::COLUMNS);
            $select->from(array(), array(
                $alias . '.id',
                $alias . '.change',
            ));
            $select->where($alias . '.change LIKE "a:%;}"');
            $result = $table->fetchAll($select);

            if ($result) {
                // Prepare the sql for update.
                $sql = "
                    UPDATE `{$db->HistoryLogEntry}`
                    SET `change` = ?
                    WHERE `id` = ?;
                ";
                try {
                    foreach ($result as $key => $value) {
                        $id = $value['id'];
                        $change = $value['change'];
                        // Check if "change" is empty or a string that isn't serialized.
                        // Should not go here.
                        if (empty($change) || @unserialize($change) === false) {
                            continue;
                        }
                        $change = unserialize($change);
                        $change = '[ ' . implode(' ', $change) . ' ]';
                        $db->query($sql, array($change, $id));
                    }
                    $msg = sprintf('Updated %d / %d serialized history log entries.', $key + 1, count($result));
                    _log($msg);
                } catch (Exception $e) {
                    $msg = sprintf('Updated %d / %d serialized history log entries.', $key, count($result));
                    throw new Exception($e->getMessage() . "\n" . $msg);
                }
            }
        }
    }

    /**
     * When the plugin uninstalls, delete the database tables which store the
     * logs.
     *
     * @return void
     */
    public function hookUninstall()
    {
        try {
            $sql = "DROP TABLE IF EXISTS `{$this->_db->HistoryLogEntry}`";
            $this->_db-> query($sql);
        } catch(Exception $e) {
            throw $e;
        }
    }

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
     * When an item is saved, determine whether it is a new item or an item
     * update. If it is an update, log the event. Otherwise, wait until after
     * the save.
     *
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookBeforeSaveItem($args)
    {
        $item = $args['record'];
        // If it's not a new item, check for changes.
        if (empty($args['insert'])) {
            try {
                $changedElements = $this->_findChanges($item);

                // Log item update for each changed elements.
                if ($changedElements) {
                    $this->_logItem($item, 'updated', $changedElements);
                } else {
                    //TODO still do updates here
                }
            } catch(Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * When an item is saved, determine whether it is a new item or an item
     * update. If it is a new item, log the event.
     *
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];

        $source = '';

        if ($request = Zend_Controller_Front::getInstance()->getRequest()) {
            if (strpos('nuxeo-link', current_url())) {
                $source = 'Nuxeo';
            }
            elseif (strpos('youtube', current_url())) {
                $source = 'YouTube';
            }
            elseif (strpos('flickr', current_url())) {
                $source = 'Flickr';
            }
        } else {
            $source = 'background script (flickr or nuxeo)';
        }

        // If it's a new item.
        if (isset($args['insert']) && $args['insert']) {
            try {
                // Log new item.
                $this->_logItem($item, 'created', $source);
            } catch(Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * When an item is deleted, log the event.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookBeforeDeleteItem($args)
    {
        $item = $args['record'];
        try {
            $this->_logItem($item, 'deleted', null);
        } catch(Exception $e) {
            throw $e;
        }
    }

    public function hookExport($args)
    {
        $service = $args['service'];
        foreach ($args['records'] as $id => $value) {
            $this->_logRecord($id, 'exported', $service);
        }
    }

    /**
     * Show the 5 most recent events in the item's history on the item's admin
     * page.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookAdminItemsShow($args)
    {
        $item = $args['item'];
        $view = $args['view'];

        if (plugin_is_active('ExhibitBuilder')) {
            $exhibits = get_db()->getTable('Exhibit')->findAll();
            foreach ($exhibits as $exhibit) {
                if ($exhibit->hasItem($args['item'])) {
                    echo '<h4 class="appears-in-exhibit">This item appears in the exhibit "' . $exhibit->title . '"</h4>';
                }
            }
        }
        try {
            echo $view->showlog($item, 5);
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
     * Show the Exhibits that each item is included in on the Item Browse page.
     *
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookAdminItemsBrowseSimpleEach($args)
    {
        $item = $args['item'];
        if (plugin_is_active('ExhibitBuilder')) {
            $exhibits = get_db()->getTable('Exhibit')->findAll();
            foreach ($exhibits as $exhibit) {
                if ($exhibit->hasItem($item)) {
                    echo '<p class="appears-in-exhibit">Appears in Exhibit: ' . $exhibit->title . '</p>';
                }
            }
        }
    }

    /**
     * Load the plugin javascript when admin section loads
     *
     * @return void
     */
    public function hookAdminHead()
    {
        queue_js_file('history-log');
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
            'privilege' => 'index',
        );
        return $nav;
    }

    /**
     * Create a new history log entry.
     *
     * @param Object|integer $item The Omeka item to log.
     * @param string $action The type of event to log (e.g. "created"...).
     * @param string|array $change An extra piece of type specific data for the
     * log.
     * @return void
     */
    private function _logItem($item, $action, $change)
    {
        if (is_numeric($item)) {
            $item = get_record_by_id('Item', $item);
        }

        $logEntry = new HistoryLogEntry();

        $currentUser = current_user();
        if (is_null($currentUser)) {
            throw new Exception('Could not retrieve user info.');
        }

        try {
            // This is a required field.
            $logEntry->item_id = $item->id;

            $collectionId = (integer) $item->collection_id;
            $title = $logEntry->displayCurrentTitle();

            $logEntry->collection_id = $collectionId;
            $logEntry->user_id = $currentUser->id;
            $logEntry->action = $action;
            $logEntry->change = $change;
            $logEntry->title = $title;

            $logEntry->save();
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
     * If an item is being updated, find out which elements are being altered.
     *
     * @param Object $item The updated omeka item record
     * @return array $changedElements An array of element IDs of altered elements
     */
    private function _findChanges($item)
    {
        if (!isset($item->Elements)) {
            return false;
        }
        $newElements = $item->Elements;

        $changedElements = array();
        try {
            $oldItem = get_record_by_id('Item', $item->id);
        } catch(Exception $e) {
            throw $e;
        }

        foreach ($newElements as $newElementID => $newElementTexts) {
            $flag = false;

            try {
                $element = get_record_by_id('Element', $newElementID);
                $oldElementTexts = $oldItem->getElementTextsByRecord($element);
            } catch(Exception $e) {
                throw $e;
            }

            $oldETextsArray = array();
            foreach ($oldElementTexts as $oldElementText) {
                $oldETextsArray[] = $oldElementText['text'];
            }

            $i = 0;
            foreach ($newElementTexts as $newElementText) {
                if ($newElementText['text'] !== '') {
                    $i++;

                    if (!in_array($newElementText['text'], $oldETextsArray)) {
                        $flag = true;
                    }
                }
            }

            if ($i !== count($oldETextsArray)) {
                $flag = true;
            }

            if ($flag) {
                $changedElements[] = $newElementID;
            }
        }

        return $changedElements;
    }
}
