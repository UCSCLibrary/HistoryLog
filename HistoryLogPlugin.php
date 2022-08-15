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
        'after_save_record',
        'before_delete_record',
        'before_save_element_text',
        'before_delete_element_text',
        'before_delete_element',
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
        'history_log_display' => '[]',
    );

    /**
     * @var array
     *
     * Array of prepared log entries saved during the "before save" process for
     * updated record. It will be saved only if the process succeeds "after
     * save". It allows to simplify the check of updates when the normal hooks
     * are used.
     */
    private $_preparedLogEntries = array();

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

        $this->_installOptions();
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
        $sql = "DROP TABLE IF EXISTS `{$db->prefix}item_history_logs`";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS `{$db->HistoryLogEntry}`";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS `{$db->HistoryLogChange}`";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS `{$db->prefix}numerals`";
        $db->query($sql);

        $this->_uninstallOptions();
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
            if (in_array($optionKey, array(
                    'history_log_display',
                ))) {
               $post[$optionKey] = json_encode($post[$optionKey]) ?: json_encode(array());
            }
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
            // Prepare the log entry if none.
            if (!isset($this->_preparedLogEntries[get_class($record)][$record->id])) {
                $logEntry = new HistoryLogEntry();
                $logEntry->prepareNewEvent($record);
                $this->_preparedLogEntries[get_class($record)][$record->id] = $logEntry;
            }
            // Else, all elements texts are ready!?
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
        $elementText = $args['record'];

        // Prepare the log entry if none.
        if (!isset($this->_preparedLogEntries[$elementText->record_type][$elementText->record_id])) {
            $logEntry = new HistoryLogEntry();
            $record = get_record_by_id($elementText->record_type, $elementText->record_id);
            if (empty($record)) {
                return;
            }
            $logEntry->prepareNewEvent($record, 'element_text');
            $this->_preparedLogEntries[$elementText->record_type][$elementText->record_id] = $logEntry;
        }

        $logEntry = $this->_preparedLogEntries[$elementText->record_type][$elementText->record_id];

        // Save old values for an update.
        if (empty($args['insert'])) {
            // Return the old and unmodified text too to avoid an issue when
            // order of texts change, in particular when a text that is not the
            // last is removed.
            $db = $this->_db;
            $sql = "SELECT text FROM {$db->ElementText} WHERE id = " . (integer) $elementText->id;
            $oldText = $db->fetchOne($sql);
            $logEntry->prepareOneElementText($elementText->element_id, HistoryLogChange::TYPE_UPDATE, array(
                'old' => $oldText,
                'new' => $elementText->text,
            ), $elementText->id);
        }
        // Save a new value.
        else {
            $logEntry->prepareOneElementText($elementText->element_id, HistoryLogChange::TYPE_CREATE, $elementText->text);
        }
    }

    /**
     * When a record is deleted, log the event.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookBeforeDeleteElementText($args)
    {
        $elementText = $args['record'];

        // Prepare the log entry if none.
        if (!isset($this->_preparedLogEntries[$elementText->record_type][$elementText->record_id])) {
            $logEntry = new HistoryLogEntry();
            $record = get_record_by_id($elementText->record_type, $elementText->record_id);
            if (empty($record)) {
                return;
            }
            $logEntry->prepareNewEvent($record, 'element_text');
            $this->_preparedLogEntries[$elementText->record_type][$elementText->record_id] = $logEntry;
        }

        $logEntry = $this->_preparedLogEntries[$elementText->record_type][$elementText->record_id];

        $logEntry->prepareOneElementText($elementText->element_id, HistoryLogChange::TYPE_DELETE, $elementText->text, $elementText->id);
    }

    /**
     * When an record is deleted, log the event.
     *
     * @param array $args An array of parameters passed by the hook.
     * @return void
     */
    public function hookBeforeDeleteElement($args)
    {
        $record = $args['record'];

        // Search all records with content of this element in order to log them.

        //A direct sql is used because the process may be heavy
        $user = current_user();
        if (is_null($user)) {
            throw new Exception(__('Could not retrieve user info before removing of element %d.', $record->id));
        }

        $db = $this->_db;

        // To avoid a transaction with Zend, the date of Entries is set in the
        // future, then the ids are recalled for Changes, and finally are reset
        // to true time.
        $added = $db->quote(date('Y-m-d H:i:s', strtotime('+1 year')));

        $sql = "
            INSERT INTO `{$db->HistoryLogEntry}` (`record_type`, `record_id`, `user_id`, `operation`, `added`)
            SELECT 'Collection', `record_id`, $user->id, '" . HistoryLogEntry::OPERATION_UPDATE . "', $added
            FROM `{$db->ElementText}`
            WHERE `record_type` = 'Collection'
                AND `element_id` = {$record->id}
        ;";
        $db->query($sql);

        $sql = "
            INSERT INTO `{$db->HistoryLogEntry}` (`record_type`, `record_id`, `part_of`, `user_id`, `operation`, `added`)
            SELECT 'Item', `record_id`, `collection_id`, $user->id, '" . HistoryLogEntry::OPERATION_UPDATE . "', $added
            FROM `{$db->ElementText}` AS `element_texts`
                JOIN `{$db->Item}` AS `items`
                    ON `element_texts`.`record_type` = 'Item'
                        AND `items`.`id` = `element_texts`.`record_id`
            WHERE `element_texts`.`record_type` = 'Item'
                AND `element_texts`.`element_id` = {$record->id}
        ;";
        $db->query($sql);

        $sql = "
            INSERT INTO `{$db->HistoryLogEntry}` (`record_type`, `record_id`, `part_of`, `user_id`, `operation`, `added`)
            SELECT 'Item', `record_id`, `item_id`, $user->id, '" . HistoryLogEntry::OPERATION_UPDATE . "', $added
            FROM `{$db->ElementText}` AS `element_texts`
                JOIN `{$db->File}` AS `files`
                    ON `element_texts`.`record_type` = 'File'
                        AND `files`.`id` = `element_texts`.`record_id`
            WHERE `element_texts`.`record_type` = 'File'
                AND `element_texts`.`element_id` = {$record->id}
        ;";
        $db->query($sql);

        $sql = "
            INSERT INTO `{$db->HistoryLogChange}` (`entry_id`, `element_id`, `type`, `text`)
            SELECT  `entries`.`id`, {$record->id}, '" . HistoryLogChange::TYPE_DELETE . "', `element_texts`.`text`
            FROM `{$db->HistoryLogEntry}` AS `entries`
                JOIN `{$db->ElementText}` AS `element_texts`
                    ON `element_texts`.`record_type` = `entries`.`record_type`
                        AND `element_texts`.`record_id` = `entries`.`record_id`
                        AND `entries`.`added` = $added
                        AND `element_texts`.`element_id` = {$record->id}
        ;";
        $db->query($sql);

        $sql = "
            UPDATE `{$db->HistoryLogEntry}`
            SET `added` = NOW()
            WHERE `added` = $added
        ;";
        $db->query($sql);

        // For integrity purposes, the deletion of element texts is done via one
        // sql too.
        $sql = "
            DELETE FROM `{$db->ElementText}`
            WHERE `element_id` = {$record->id}
        ;";
        $db->query($sql);

        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $msg = __('The element "%s" (%d) in set "%s" has been removed.', $record->name, $record->id, $record->set_name);
        _log('[HistoryLog] ' . $msg, Zend_Log::WARN);
        $flash->addMessage($msg, 'success');
        $msg = __('The Omeka base should be reindexed.');
        _log('[HistoryLog] ' . $msg, Zend_Log::WARN);
        $flash->addMessage($msg, 'success');
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
        $this->_adminRecordShow($args, 'items/show');
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
        $this->_adminRecordShow($args, 'collections/show');
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
        $this->_adminRecordShow($args, 'files/show');
    }

    /**
     * Helper to show the 5 most recent events in the record's history on the
     * record's admin page.
     *
     * @param array $args An array of parameters passed by the hook.
     * @param string $page The current type of the page
     * @return void
     */
    protected  function _adminRecordShow($args, $page)
    {
        $record = $args['record'];
        $view = $args['view'];

        $currentPages = json_decode(get_option('history_log_display'), true) ?: array();
        if (!in_array($page, $currentPages)) {
            return;
        }

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

        $currentPages = json_decode(get_option('history_log_display'), true) ?: array();
        if (!in_array('items/browse', $currentPages)) {
            return;
        }

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
        $requestParams = Zend_Controller_Front::getInstance()->getRequest()->getParams();
        $module = isset($requestParams['module']) ? $requestParams['module'] : 'default';
        $controller = isset($requestParams['controller']) ? $requestParams['controller'] : 'index';
        $action = isset($requestParams['action']) ? $requestParams['action'] : 'index';
        if ($module == 'history-log' && $controller == 'index' && $action == 'search') {
            queue_js_file('history-log');
        }
        if ($module == 'history-log') {
            queue_css_file('fix-omeka-3-0-3-admin');
        }
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
     * @param string|array $changes An extra piece of type specific data for the
     * log.
     * @return void
     */
    private function _logEvent($record, $operation, $changes = null)
    {
        $user = current_user();
        if (is_null($user)) {
            // Some contribution plugins like Scripto allow anonymous users to
            // edit an element of a record.
            $user = new User;
            // Only the user id is set in HistoryLog entries.
            $user->id = 0;
            // The username should be unique. It can be the ip, like the wiki
            // used by Scripto.
            $user->username = 'anonymous';
            $user->role = 'anonymous';
            $user->name = 'anonymous';
            // throw new Exception(__('Could not retrieve user info.'));
        }

        // If the operation is an update, get the saved data.
        if ($operation == HistoryLogEntry::OPERATION_UPDATE
                && isset($this->_preparedLogEntries[get_class($record)][$record->id])
            ) {
            $logEntry = $this->_preparedLogEntries[get_class($record)][$record->id];
        }

        // Normal save.
        else {
            $logEntry = new HistoryLogEntry();
        }

        try {
            // Prepare the log entry.
            $result = $logEntry->logEvent($record, $operation, $user, $changes);
            // May avoid a double update.
            unset($this->_preparedLogEntries[get_class($record)][$record->id]);
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
}
