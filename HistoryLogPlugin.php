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
        'uninstall_message',
        'config_form',
        'config',
        'define_acl',
        'define_routes',
        'before_save_record',
        'before_save_element_text',
        'after_save_record',
        'before_delete_record',
        'before_delete_element_text',
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
     * @var array Options and their default values.
     */
    protected $_options = array(
    );

    /**
     * @var array
     *
     * Array of old element texts, that are used to update.
     * These events are saved only if the process succeeds ("after save").
     */
    private $_oldTexts = array();

    /**
     * @var array
     *
     * Array of element texts before a change, by record and type of change.
     *
     * When an automatic process is done via a Builder (Item or Collection), the
     * element texts are pre-saved and old content texts are not available in
     * the hook before_save. The content of a record is emptied after the record
     * is saved.
     */
    private $_texts = array();

    /**
     * When the plugin installs, create the database tables to store the logs.
     *
     * @return void
     */
    public function hookInstall()
    {
        $db = $this->_db;

        // Main table to log event.
        $sql = "
        CREATE TABLE IF NOT EXISTS `{$db->HistoryLogEntry}` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `record_type` enum('Item', 'Collection', 'File') NOT NULL,
            `record_id` int(10) unsigned NOT NULL,
            `part_of` int(10) unsigned NOT NULL DEFAULT 0,
            `user_id` int(10) unsigned NOT NULL,
            `operation` enum('create', 'update', 'delete', 'import', 'export') NOT NULL,
            `added` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `record_type_record_id` (`record_type`, `record_id`),
            INDEX (`added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        $db->query($sql);

        // Associated table to log changes of each element.
        $sql = "
        CREATE TABLE IF NOT EXISTS `{$db->HistoryLogChange}` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `entry_id` int(10) unsigned NOT NULL,
            `element_id` int(10) unsigned NOT NULL,
            `type` enum('none', 'create', 'update', 'delete') NOT NULL,
            `text` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            INDEX (`entry_id`),
            INDEX `entry_id_element_id` (`entry_id`, `element_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        $db->query($sql);

        // Add a new table to simplify complex queries with calendar requests.
        $sql = "
            CREATE TABLE IF NOT EXISTS `{$db->prefix}numerals` (
                `i` TINYINT unsigned NOT NULL,
                PRIMARY KEY (`i`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        $db->query($sql);
        $sql = "INSERT INTO `{$db->prefix}numerals` (`i`) VALUES (0), (1), (2), (3), (4), (5), (6), (7), (8), (9);";
        $db->query($sql);
    }

    /**
     * Upgrade the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];
        $db = $this->_db;

        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'upgrade.php';
    }

    /**
     * When the plugin uninstalls, delete the database tables which store the
     * logs.
     *
     * @return void
     */
    public function hookUninstall()
    {
        $db = $this->_db;
        $sql = "DROP TABLE IF EXISTS `{$db->HistoryLogEntry}`";
        $db-> query($sql);
        $sql = "DROP TABLE IF EXISTS `{$db->HistoryLogChange}`";
        $db-> query($sql);
        $sql = "DROP TABLE IF EXISTS `{$db->prefix}numerals`";
        $db-> query($sql);
    }

    /**
     * Add a message to the confirm form for uninstallation of the plugin.
     */
    public function hookUninstallMessage()
    {
        echo __('%sWarning%s: All the history log entries will be deleted.', '<strong>', '</strong>');
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'plugins/history-log-config-form.php'
        );
    }

    /**
     * Handle a submitted config form.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                set_option($optionKey, $post[$optionKey]);
            }
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

        $args['router']->addRoute(
            'history_log_undelete',
            new Zend_Controller_Router_Route(
                ':type/undelete/:id',
                array(
                    'module' => 'history-log',
                    'controller' => 'log',
                    'action' => 'undelete',
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
            $logEntry = new HistoryLogEntry();
            $oldTexts = $logEntry->prepareEventForUpdate($record);
            if ($oldTexts !== false) {
                $this->_oldTexts[get_class($record)][$record->id] = $oldTexts;
            }
        }
    }

    /**
     * Hook used before save an element text.
     *
     * The fonction "update_item" uses Builder_Item, that saves element texts
     * before the record, so the old element texts are lost when it is used. So
     * a check is done to save them in case of an update.
     *
     * @param array $args An array of parameters passed by the hook
     * @return void
     */
    public function hookBeforeSaveElementText($args)
    {
        // Save old values for an update.
        if (empty($args['insert'])) {
            // Return the old and unmodified text too to avoid an issue when
            // order of texts change, in particular when a text that is not the
            // last is removed.
            $record = $args['record'];
            $db = $this->_db;
            $sql = "SELECT text FROM {$db->ElementText} WHERE id = " . (integer) $record->id;
            $oldText = $db->fetchOne($sql);
            $this->_texts[$record->record_type][$record->record_id][$record->element_id][HistoryLogChange::TYPE_UPDATE][$record->id] = array(
                'old' => $oldText,
                'new' => $record->text,
            );
        }
        // Save a new value.
        else {
            $record = $args['record'];
            $this->_texts[$record->record_type][$record->record_id][$record->element_id][HistoryLogChange::TYPE_CREATE][] = $record->text;
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

        // It's an update of a record.
        if (empty($args['insert'])) {
            $this->_logEvent($record, HistoryLogEntry::OPERATION_UPDATE);
            // Normally useless but may avoid a double update and reduce memory.
            unset($this->_oldTexts[get_class($record)][$record->id]);
            unset($this->_texts[get_class($record)][$record->id]);
        }

        // This is a new record, imported or manually created.
        else {
            $imported = $this->_isImported();
            if ($imported) {
                $this->_logEvent($record, HistoryLogEntry::OPERATION_IMPORT, $imported);
            }
            $this->_logEvent($record, HistoryLogEntry::OPERATION_CREATE);
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

        $this->_logEvent($record, HistoryLogEntry::OPERATION_DELETE);
    }

    /**
     * When an record is deleted, log the event.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookBeforeDeleteElementText($args)
    {
        $record = $args['record'];
        $this->_texts[$record->record_type][$record->record_id][$record->element_id][HistoryLogChange::TYPE_DELETE][$record->id] = $record->text;
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
                HistoryLogEntry::OPERATION_EXPORT,
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
            ->getLastEntryForRecord($record);
        if ($logEntry) {
            $html = '<div class="history-log">';
            switch ($logEntry->operation) {
                case HistoryLogEntry::OPERATION_CREATE:
                    $html .= __('Created on %s by %s.',
                        $logEntry->displayAdded(), $logEntry->displayUser());
                    break;
                case HistoryLogEntry::OPERATION_UPDATE:
                    $html .= __('Updated on %s by %s.',
                        $logEntry->displayAdded(), $logEntry->displayUser());
                    break;
                case HistoryLogEntry::OPERATION_DELETE:
                    $html .= __('Deleted on %s by %s.',
                        $logEntry->displayAdded(), $logEntry->displayUser());
                    break;
                case HistoryLogEntry::OPERATION_IMPORT:
                    $html .= __('Imported on %s by %s.',
                        $logEntry->displayAdded(), $logEntry->displayUser());
                    break;
                case HistoryLogEntry::OPERATION_EXPORT:
                    $html .= __('Exported on %s by %s.',
                        $logEntry->displayAdded(), $logEntry->displayUser());
                    break;
            }
            $html .= '</div>';
            echo $html;
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
     * Quickly check if a record is loggable (item, collection, file).
     *
     * @param Record $record
     * @return boolean
     */
    protected function _isLoggable($record)
    {
        return in_array(get_class($record), array('Item', 'Collection', 'File'));
    }

    /**
     * Check if a record is imported.
     *
     * @return string Origin of the import, else, if empty, created manually.
     */
    protected function _isImported()
    {
        $imported = '';
        $request = Zend_Controller_Front::getInstance()->getRequest();
        if ($request) {
            $url = current_url();
            if ($url) {
                if (strpos('nuxeo-link', $url)) {
                    $imported = 'Nuxeo';
                }
                elseif (strpos('youtube', $url)) {
                    $imported = 'YouTube';
                }
                elseif (strpos('flickr', $url)) {
                    $imported = 'Flickr';
                }
            }
            // Else manually created.
        }
        // Else background script.
        else {
            $imported = __('Background script');
        }
        return $imported;
    }

    /**
     * Create a new history log entry.
     *
     * @uses HistoryLogEntry::logEvent()
     *
     * @param Object|array $record The Omeka record to log. It should be an
     * object for a "create" or an "update".
     * @param string $operation The type of event to log (e.g. "create"...).
     * @param string|array $change An extra piece of type specific data for the
     * log.
     * @return void
     */
    private function _logEvent($record, $operation, $change = null)
    {
        $user = current_user();
        if (is_null($user)) {
            throw new Exception(__('Could not retrieve user info.'));
        }

        // If the operation is an update, the method should be selected.
        $updateType = null;
        if ($operation == HistoryLogEntry::OPERATION_UPDATE) {
            $updateType = $this->_checkUpdateType($record);
            $change = $updateType == 'element_texts'
                ? $this->_texts[get_class($record)][$record->id]
                : $this->_oldTexts[get_class($record)][$record->id];
        }

        $logEntry = new HistoryLogEntry();

        try {
            // Prepare the log entry.
            $result = $logEntry->logEvent($record, $user, $operation, $change, $updateType);
            // Quick check if the record is loggable.
            if (!$result) {
                throw new Exception(__('This event is not loggable.'));
            }

            // Only save if this is useful.
            $result = $logEntry->saveIfChanged();
            if ($result === false) {
                throw new Exception(__('Could not log info.'));
            }
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
     * Check the update type.
     *
     * @internal In Omeka, an update is different when manual or automatic: the
     * mixin for element texts uses only post data (manual insert). So, to
     * simplify the logging, the update is cached here for any type of update.
     * This alllows to log the changes only when are really saved.
     * Furthemore, a second way, via the hook before_save_element_texts, is used
     * to get old values, because some methods process the save in two steps:
     * "create" then "update", even if there is no change. So if the main method
     * isn't used or values are empty, but the other one as elements, the latter
     * is used.
     *
     * @param Record $record
     * @return string The type of update : "record" or "element_texts".
     */
    protected function _checkUpdateType($record)
    {
        $updateType = null;

        if (!isset($this->_texts[get_class($record)][$record->id])
                && !isset($this->_oldTexts[get_class($record)][$record->id])
            ) {
            $updateType = 'record';
            $this->_oldTexts[get_class($record)][$record->id] = array();
            return $updateType;
        }

        if (!isset($this->_texts[get_class($record)][$record->id])) {
            $updateType = 'record';
            return $updateType;
        }

        if (!isset($this->_oldTexts[get_class($record)][$record->id])) {
            $updateType = 'element_texts';
            return $updateType;
        }

        // Define the method to process the save.
        if (empty($this->_oldTexts[get_class($record)][$record->id])) {
            $updateType = 'element_texts';
            $change = isset($this->_texts[get_class($record)][$record->id])
                // In that case the list of element texts is fine.
                ? $this->_texts[get_class($record)][$record->id]
                // The process is done nevertheless for external changes
                // (collection...).
                : array();
        }

        // Record way.
        elseif (empty($this->_texts[get_class($record)][$record->id])) {
            $updateType = 'record';
            $change = $this->_oldTexts[get_class($record)][$record->id];
        }

        // Complex choice: the two are available, but the normal one may be
        // without old values, and the one via element texts  may have false
        // creation and update of elements.
        else {
            // Check if the old elements are really old ones: if they are
            // the same as current ones, they aren't older.
            $newElements = $this->_getCurrentElements($record);
            $updateType = 'element_texts';
            if (!is_null($newElements)) {
                $oldElements = $this->_oldTexts[get_class($record)][$record->id];
                // Compare old and new elements.
                foreach ($newElements as $elementId => $newTexts) {
                    if ($oldElements[$elementId] != $newTexts) {
                        $updateType = 'record';
                        break;
                    }
                }
            }

            // Nevertheless, if all element_texts are the same, there is no
            // change, so use the record way.
            if ($updateType == 'element_texts') {
                $updateType = 'record';
                $flag = true;
                // Needed to prepare the next check.
                $createTexts = array();
                // Quick loop for changes.
                foreach ($this->_texts[get_class($record)][$record->id] as $elementId => $changeTypes) {
                    if (!empty($changeTypes[HistoryLogChange::TYPE_DELETE])){
                        $updateType = 'element_texts';
                        $flag = false;
                        break;
                    }
                    if (!empty($changeTypes[HistoryLogChange::TYPE_CREATE])) {
                        $updateType = 'element_texts';
                        $createTexts[$elementId] = $changeTypes[HistoryLogChange::TYPE_CREATE];
                    }
                    if (!empty($changeTypes[HistoryLogChange::TYPE_UPDATE])) {
                        $flag = false;
                        foreach ($changeTypes[HistoryLogChange::TYPE_UPDATE] as $elementTextId => $oldNewTerm) {
                            if ($oldNewTerm['new'] !== $oldNewTerm['old']) {
                                $updateType = 'element_texts';
                                break 2;
                            }
                        }
                    }
                }
            }

            // A last check: if all element texts are "create" and there is no
            // other process, and all element texts are the same than the record
            // texts, this is a wrong update.
            if (!empty($flag) && $updateType == 'element_texts') {
                $old = $this->_oldTexts[get_class($record)][$record->id];
                ksort($old);
                ksort($createTexts);
                if ($old == $createTexts) {
                    $updateType = 'record';
                }
            }
        }

        return $updateType;
    }

    /**
     * Helper to get current elements of a record.
     *
     * @param Record $record
     * @return array|null
     */
    protected function _getCurrentElements($record)
    {
        // Get the current list of elements.
        $newElements = array();

        // If there are elements, the record is created via post (manually).
        $viaPost = isset($record->Elements);
        // Manual update.
        if ($viaPost) {
            foreach ($record->Elements as $elementId => $elementTexts) {
                foreach ($elementTexts as $elementText) {
                    // strlen() is used to allow values like "0".
                    if (strlen($elementText['text']) > 0) {
                        $newElements[$elementId][] = $elementText['text'];
                    }
                }
            }
        }

        // Automatic update.
        else {
            $elementTexts = get_records(
                'ElementText',
                array(
                    'record_type' => get_class($record),
                    'record_id' => $record->id),
                0);

            if (is_null($elementTexts)) {
                // TODO Throw an error? Normally, never here.
                return;
            }

            foreach ($elementTexts as $elementText) {
                $newElements[$elementText->element_id][] = $elementText->text;
            }
        }

        return $newElements;
    }
}
