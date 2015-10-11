<?php
/**
 * History Log
 *
 * This Omeka 2.0+ plugin logs curatorial actions such as adding, deleting, or
 * modifying items, collections and files.
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
        'define_routes',
        'before_save_record',
        'after_save_record',
        'before_delete_record',
        'export',
        'admin_items_show',
        'admin_collections_show',
        'admin_files_show',
        'admin_items_browse_detailed_each',
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
                `record_type` enum('Item', 'Collection', 'File') NOT NULL,
                `record_id` int(10) NOT NULL,
                `part_of` int(10) NOT NULL DEFAULT 0,
                `user_id` int(10) NOT NULL,
                `operation` enum('created', 'imported', 'updated', 'exported', 'deleted') NOT NULL,
                `change` text collate utf8_unicode_ci NOT NULL,
                `added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `title` text COLLATE utf8_unicode_ci,
                PRIMARY KEY (`id`),
                KEY `record_type_record_id` (`record_type`, `record_id`),
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
                    $msg = __('Updated %d / %d serialized history log entries.', $key + 1, count($result));
                    _log($msg);
                } catch (Exception $e) {
                    $msg = __('Updated %d / %d serialized history log entries.', $key, count($result));
                    throw new Exception($e->getMessage() . "\n" . $msg);
                }
            }
        }

        if (version_compare($oldVersion, '2.5', '<')) {
            // Allows each type of record to be logged.
            // "record_type" can't be null, but mysql takes the first of "enum".
            $sql = "
                ALTER TABLE `{$db->HistoryLogEntry}`
                ADD `record_type` enum('Item', 'Collection', 'File') NOT NULL AFTER `id`,
                CHANGE `item_id` `record_id` int(10) NOT NULL AFTER `record_type`,
                CHANGE `collection_id` `part_of` int(10) NOT NULL DEFAULT 0 AFTER `record_id`,
                CHANGE `action` `operation` enum('created', 'imported', 'updated', 'exported', 'deleted') NOT NULL AFTER `user_id`,
                DROP INDEX `item_id`,
                DROP INDEX `change`,
                DROP INDEX `added`,
                ADD INDEX `record_type_record_id` (`record_type`, `record_id`),
                ADD INDEX (`change` (50)),
                ADD INDEX (`added`)
            ";
            $db->query($sql);
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
     * Define routes.
     *
     * @param Zend_Controller_Router_Rewrite $router
     */
    public function hookDefineRoutes($args)
    {
        if (!is_admin_theme()) {
            return;
        }

        $args['router']->addRoute(
            'history_log_record_log',
            new Zend_Controller_Router_Route(
                ':type/log/:id',
                array(
                    'module' => 'history-log',
                    'controller' => 'log',
                    'action' => 'log',
                ),
                array(
                    'type' =>'items|collections|files',
                    'id' => '\d+',
        )));
    }

    /**
     * When a record is saved, determine whether it is a new record or a record
     * update. If it is an update, log the event. Otherwise, wait until after
     * the save.
     *
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookBeforeSaveRecord($args)
    {
        $record = $args['record'];
        if (!$this->_isLoggable($record)) {
            return;
        }

        // If it's not a new record, check for changes.
        if (empty($args['insert'])) {
            try {
                $changedElements = $this->_findChanges($record);

                // Log record update for each changed elements.
                if ($changedElements) {
                    $this->_logRecord($record, 'updated', $changedElements);
                } else {
                    //TODO still do updates here
                }
            } catch(Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * When an record is saved, determine whether it is a new record or an
     * update. If it is a new record, log the event.
     *
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookAfterSaveRecord($args)
    {
        $record = $args['record'];
        if (!$this->_isLoggable($record)) {
            return;
        }

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
            $source = __('background script');
        }

        // If it's a new record.
        if (isset($args['insert']) && $args['insert']) {
            try {
                // Log new record.
                $this->_logRecord($record, 'created', $source);
            } catch(Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * When an record is deleted, log the event.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookBeforeDeleteRecord($args)
    {
        $record = $args['record'];
        if (!$this->_isLoggable($record)) {
            return;
        }

        try {
            $this->_logRecord($record, 'deleted', null);
        } catch(Exception $e) {
            throw $e;
        }
    }

    public function hookExport($args)
    {
        $service = $args['service'];
        foreach ($args['records'] as $id => $value) {
            $this->_logRecord(
                array(
                    // TODO Manage other exports.
                    'record_type' => 'Item',
                    'record_id' => $id,
                ),
                'exported',
                $service);
        }
    }

    /**
     * Hook for items/show page.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookAdminItemsShow($args)
    {
        $args['record'] = $args['item'];
        unset($args['item']);
        $this->_adminRecordShow($args);
    }

    /**
     * Hook for collections/show page.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookAdminCollectionsShow($args)
    {
        $args['record'] = $args['collection'];
        unset($args['collection']);
        $this->_adminRecordShow($args);
    }

    /**
     * Hook for collections/show page.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookAdminFilesShow($args)
    {
        $args['record'] = $args['file'];
        unset($args['file']);
        $this->_adminRecordShow($args);
    }

    /**
     * Helper to show the 5 most recent events in the record's history on the
     * record's admin page.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    protected  function _adminRecordShow($args)
    {
        $record = $args['record'];
        $view = $args['view'];

        try {
            echo $view->showlog($record, 5);
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
     * Show details for each item.
     *
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookAdminItemsBrowseDetailedEach($args)
    {
        $record = $args['item'];
        $view = $args['view'];

        $logEntry = $this->_db->getTable('HistoryLogEntry')
            ->findBy(
                array(
                    'record' => $record,
                    'sort_field' => 'added',
                    'sort_dir' => 'd',
                ), 1);
        if ($logEntry) {
            $logEntry = reset($logEntry);
            echo '<div class="history-log">'
                . __('Last change on %s by %s.',
                    $logEntry->displayAdded(), $logEntry->displayUser())
                . '</div>';
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
            'label' => __('History Logs'),
            'uri' => url('history-log'),
            'resource' => 'HistoryLog_Index',
            'privilege' => 'index',
        );
        return $nav;
    }

    /**
     * Check if a record is loggable (item, collection, file).
     *
     * @param Record $record
     * @return boolean
     */
    protected function _isLoggable($record)
    {
        return in_array(get_class($record), array('Item', 'Collection', 'File'));
    }

    /**
     * Create a new history log entry.
     *
     * @param Object $record The Omeka record to log.
     * @param string $operation The type of event to log (e.g. "created"...).
     * @param string|array $change An extra piece of type specific data for the
     * log.
     * @return void
     */
    private function _logRecord($record, $operation, $change)
    {
        $currentUser = current_user();
        if (is_null($currentUser)) {
            throw new Exception(__('Could not retrieve user info.'));
        }

        $logEntry = new HistoryLogEntry();
        try {
            // This is a required field.
            $logEntry->setRecord($record);
            $logEntry->user_id = $currentUser->id;
            $logEntry->operation = $operation;
            $logEntry->setChange($change);
            $logEntry->save();
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
     * If a record  is being updated, find out which elements are being altered.
     *
     * @param Object $record The updated omeka record
     * @return array $changedElements An array of element IDs of altered elements
     */
    private function _findChanges($record)
    {
        if (!isset($record->Elements)) {
            return false;
        }
        $newElements = $record->Elements;

        $changedElements = array();
        try {
            $oldRecord = get_record_by_id(get_class($record), $record->id);
        } catch(Exception $e) {
            throw $e;
        }

        foreach ($newElements as $newElementID => $newElementTexts) {
            $flag = false;

            try {
                $element = get_record_by_id('Element', $newElementID);
                $oldElementTexts = $oldRecord->getElementTextsByRecord($element);
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
