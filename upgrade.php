
<?php
// Manage all upgrade processes (make main file lighter).

if (version_compare($oldVersion, '2.4', '<')) {
    // TODO Check if the structure is already upgraded in case of a bug.
    // Reorder columns and change name of columns "type" to "action".,
    // "value" to "change" and "time" to "added".
 
    // First, remove all null values that could be present in columns
    // which are being changed to NOT NULL
    $null_columns = array('itemID',
                          'collectionID',
                          'userID',
                          'type',
                          'value');
    foreach ($null_columns as $column) {
        $sql = "UPDATE `{$db->HistoryLogEntry}` SET `$column`=\"\" where `$column` IS NULL";
        $db->query($sql);
    }

    // Then alter the table, knowing that no null values can cause errors
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

if (version_compare($oldVersion, '2.5.1', '<')) {
    // Simplify the name of operations and reorder them to group
    // "import" and "export" at the end of the list, because they are
    // different (not crud).
    // Set the default value to empty string for text fields to avoid null.
    try {
        $sql = "
            ALTER TABLE `{$db->HistoryLogEntry}`
            CHANGE `record_id` `record_id` int(10) unsigned NOT NULL AFTER `record_type`,
            CHANGE `part_of` `part_of` int(10) unsigned NOT NULL DEFAULT 0 AFTER `record_id`,
            CHANGE `user_id` `user_id` int(10) unsigned NOT NULL AFTER `part_of`,
            CHANGE `operation` `operation` enum('created', 'imported', 'updated', 'exported', 'deleted', 'create', 'update', 'delete', 'import', 'export') NOT NULL AFTER `user_id`,
            CHANGE `change` `change` text collate utf8_unicode_ci NOT NULL AFTER `operation`,
            CHANGE `added` `added` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `change`,
            CHANGE `title` `title` mediumtext COLLATE utf8_unicode_ci NOT NULL AFTER `added`
        ";
        $db->query($sql);
        $msg = __('Success of upgrade of the table (%s).', '2.5.1');
        _log($msg);
    } catch (Exception $e) {
        $msg = __('Fail during upgrade of the table (%s).', '2.5.1');
        throw new Exception($e->getMessage() . "\n" . $msg);
    }

    try {
        // Second step to simplify the name of operations.
        $sql = "
            UPDATE `{$db->HistoryLogEntry}`
            SET `operation` =
                CASE
                    WHEN operation = 'created' THEN 'create'
                    WHEN operation = 'updated' THEN 'update'
                    WHEN operation = 'deleted' THEN 'delete'
                    WHEN operation = 'imported' THEN 'import'
                    WHEN operation = 'exported' THEN 'export'
                END
        ";
        $db->query($sql);
        $msg = __('Success of the second step to rename the operations.');
        _log($msg);

        // End of the simplification of the name of operations.
        $sql = "
            ALTER TABLE `{$db->HistoryLogEntry}`
            CHANGE `operation` `operation` enum('create', 'update', 'delete', 'import', 'export') NOT NULL AFTER `user_id`
        ";
        $db->query($sql);
        $msg = __('Success of the last step to rename the operations.');
        _log($msg);
    } catch (Exception $e) {
        $msg = __('Fail during the rename of the operations.');
        throw new Exception($e->getMessage() . "\n" . $msg);
    }

    // Separate creation and import in order to log elements set during
    // creation. Old import won't have info about creation of elements.
    $sql = "
        UPDATE `{$db->HistoryLogEntry}`
        SET `operation` = 'import'
        WHERE `operation` = 'create'
            AND `change` IS NOT NULL
            AND `change` != ''
    ";
    $db->query($sql);

    // Import will have a log for creation, but with only one change for
    // the title, if any.
    $titleElement = $db->getTable('Element')->findByElementSetNameAndElementName('Dublin Core', 'Title');
    $titleElementId = $titleElement->id;
    $checkTitle = "IF(title = '', '', IF(title = 'untitled / title unknown', '',  '[ $titleElementId ]'))";
    $sql = "
        INSERT INTO `{$db->HistoryLogEntry}` (`record_type`, `record_id`, `part_of`, `user_id`, `operation`, `change`, `added`, `title`)
        SELECT record_type, record_id, part_of, user_id, 'create', $checkTitle, added, title
        FROM {$db->HistoryLogEntry}
        WHERE `operation` = 'import'
    ";
    $db->query($sql);
}

if (version_compare($oldVersion, '2.6', '<')) {
    // Add a new table to log changes.
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

    // Insert values in the new tableElement from the table Entry.
    // Get current serialized values.
    $table = $db->getTable('HistoryLogEntry');
    $alias = $table->getTableAlias();
    $select = $table->getSelect();
    $select->reset(Zend_Db_Select::COLUMNS);
    $select->from(array(), array(
        $alias . '.id',
        $alias . '.operation',
        $alias . '.change',
        $alias . '.title',
    ));
    $select->where($alias . ".change != ''");
    $result = $table->fetchAll($select);

    if ($result) {
        // Get the element Title to keep it.
        $titleElement = $db->getTable('Element')
            ->findByElementSetNameAndElementName('Dublin Core', 'Title');
        $titleElementId = $titleElement->id;

        // Prepare the sql for insert.
        $sql = "INSERT INTO `{$db->HistoryLogChange}` (`entry_id`, `element_id`, `type`, `text`)
            VALUES %s";
        try {
            foreach ($result as $key => $value) {
                $entryId = $value['id'];
                $operation = $value['operation'];
                $change = $value['change'];
                $title = $value['title'];

                switch ($operation) {
                    case HistoryLogEntry::OPERATION_CREATE:
                    case HistoryLogEntry::OPERATION_UPDATE:
                        $changes = explode(' ', trim($change, '[ ]'));
                        if (empty($changes)) {
                            continue;
                        }
                        $values = array();
                        foreach ($changes as $elementId) {
                            $newValues = array(
                                $entryId,
                                $elementId,
                                $db->quote($operation),
                                $elementId == $titleElementId
                                    // Keep the initial title if any.
                                    ? $db->quote($title)
                                    // The log is lost for other elements.
                                    : $db->quote('# empty / lost old value #'),
                            );
                            $values[] = implode(',', $newValues);
                        }
                        $values = '(' . implode('), (', $values) . ')';
                        break;

                    case HistoryLogEntry::OPERATION_DELETE:
                        // Normally, can't be here.
                        break;
                    case HistoryLogEntry::OPERATION_IMPORT:
                    case HistoryLogEntry::OPERATION_EXPORT:
                    default:
                        $newValues = array(
                            $entryId,
                            // No element id for imported, exported or deleted.
                            0,
                            $db->quote('none'),
                            // The source or the service is set in text.
                            $db->quote($change),
                        );
                        $values = '(' . implode(',', $newValues) . ')';
                        break;
                }

                $query = sprintf($sql, $values);
                $db->query($query);
            }

            $msg = __('Exported %d / %d "change" from table Entries to table Elements.', $key + 1, count($result));
            _log($msg);
        } catch (Exception $e) {
            $msg = __('Exported %d / %d "change" from table Entries to table Elements.', $key, count($result));
            throw new Exception($e->getMessage() . "\n" . $msg);
        }
    }

    // Remove columns "change" and "title" from the table entry.
    $sql = "
        ALTER TABLE `{$db->HistoryLogEntry}`
        DROP COLUMN `change`,
        DROP COLUMN `title`
    ";
    $db->query($sql);
}
