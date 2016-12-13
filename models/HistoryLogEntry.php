<?php
/**
 * A History Log entry
 *
 * @package Historylog
 *
 */
class HistoryLogEntry extends Omeka_Record_AbstractRecord
{
    const OPERATION_CREATE = 'create';
    const OPERATION_UPDATE = 'update';
    const OPERATION_DELETE = 'delete';
    const OPERATION_IMPORT = 'import';
    const OPERATION_EXPORT = 'export';

    private $_validRecordTypes = array(
        'Item',
        'Collection',
        'File',
    );

    /**
     * @var int The record ID.
     */
    public $id;

    /**
     * @var string The type of the record associated with this log entry.
     */
    public $record_type;

    /**
     * @var int The id of the record associated with this log entry.
     */
    public $record_id;

    /**
     * @var int The id of the Collection record in which the associated Item
     * record was stored at the time of log entry, or the id of the Item record
     * in which the associated File record was stored.
     */
    public $part_of;

    /**
     * @var int The id of the User record who performed the logged action.
     */
    public $user_id;

    /**
     * @var string The limited list of type of operation being logged:
     * "create", "update", "delete", "import", "export".
     * @internal Because of Zend, the reserved word "action" cannot be used.
     */
    public $operation;

    /**
     * @var string The UTF formatted date and time when the log took place.
     */
    public $added;

    /**
     * Records related to an Item.
     *
     * @var array
     */
    protected $_related = array(
        'Record' => 'getRecord',
        'Changes' => 'getChanges',
    );

    /**
     * Set of non-persistent Change objects attached to the event.
     *
     * @var array
     * @see HistoryLogEntry::_saveChanges()
     * @see HistoryLogEntry::_getChanges()
     */
    private $_changes = array();

    /**
     * The changes related to the event before saving.
     *
     * @var array
     */
    private $_changesToLog;

    /**
     * A record before save. In fact, it may be an old or a new one.
     *
     * @var Record
     */
    private $_tempRecord;

    /**
     * Set the type of update (this is the first hook used).
     *
     * @var Record
     */
    private $_updateType;

    /**
     * List of old texts used to get changes for an update. If null during an
     * update, all element texts will be saved.
     *
     * @var array
     */
    private $_oldTexts;

    /**
     * Old "part of", to check when there is an update. Only useful  to log the
     * change of a collection of an item.     *
     *
     * @var integer
     */
    private $_oldPartOf;

    /**
     * Array of element texts before a change, by record and type of change.
     *
     * When an automatic process is done via a Builder (Item or Collection), the
     * element texts are pre-saved and old content texts are not available in
     * the hook before_save. The content of a record is emptied after the record
     * is saved.
     *
     * @var array
     */
    private $_changedTexts = array();

    /**
     * New elements via post or database.
     *
     * @var array
     */
    private $_newElements;

    /**
     * Initialize the mixins for a record.
     */
    protected function _initializeMixins()
    {
        // TODO The acl resource interface is useless?
        $this->_mixins[] = new Mixin_Owner($this, 'user_id');
        $this->_mixins[] = new Mixin_Timestamp($this, 'added', null);
    }

    /**
     * Prepare a new event.
     *
     * @param Object|array $record The Omeka record to log. It should exist at
     * the time of logging.
     * @param string Set what is the first hook (BuilderItem save element texts
     * first). Can be "record" (default") or "element_text".
     * @return boolean
      */
    public function prepareNewEvent($record, $updateType = 'record')
    {
        $result = $this->_logRecord($record);
        $this->_tempRecord = $record;
        $this->_updateType = $updateType;
        $this->_cacheOldRecord();
        return !empty($result);
    }

    /**
     * Prepare the log of update on a record: store old metadata and "part of".
     *
     * This is the recommended method to log an update.
     * The entry should be prepared via prepareNewEvent().
     *
     * @param Object $record
     * @return boolean.
      */
    protected function _cacheOldRecord()
    {
        if (empty($this->record_type) || empty($this->record_id)) {
            return false;
        }

        $db = get_db();

                // Save the current "part of" for item (useless for other record types).
        if ($this->record_type == 'Item') {
            $sql = "
                SELECT `collection_id`
                FROM `{$db->Item}`
                WHERE `id` = " . (integer) $this->record_id;
            $this->_oldPartOf = (integer) $db->fetchOne($sql);
        }

        $sql = "
            SELECT `id`, `element_id`, `text`
            FROM `{$db->ElementText}`
            WHERE `record_type` = " . $db->quote($this->record_type)
                . ' AND `record_id` = ' . (integer) $this->record_id;
        $elementTexts = $db->fetchAll($sql);
        $oldTexts = array();
        foreach ($elementTexts as $elementText) {
            $oldTexts[$elementText['element_id']][] = $elementText['text'];
        }
        $this->_oldTexts = $oldTexts;

        return true;
    }

    /**
     * Prepare data about one element text being created, updated or deleted.
     *
     * @param integer $elementId
     * @param string $type
     * @param array $value
     * @param integer $elementTextId
     * @return boolean.
      */
    public function prepareOneElementText($elementId, $type, $value, $elementTextId = 0)
    {
        if (empty($this->record_type) || empty($this->record_id)) {
            return false;
        }

        // Update or deletion of an element text.
        if ($elementTextId) {
            $this->_changedTexts[$elementId][$type][$elementTextId] = $value;
        }
        // New element text.
        else {
            $this->_changedTexts[$elementId][$type][] = $value;
        }

        return true;
    }

    /**
     * Log an operation on a record and set associated values.
     *
     * The entry may be prepared via prepareNewEvent().
     * This is the recommended method to log an event. When an operation is an
     * update, it's recommended to call this method a first time before saving
     * the record in order to keep old values.
     *
     * @internal Checks are done here and during validation.
     *
     * @param Object|array $record The Omeka record to log. It should exist at
     * the time of logging.
     * @param string $operation The type of event to log (e.g. "create"...).
     * @param User|integer $user
     * @param string|array $change An extra piece of type specific data for the
     * log. When the operation is "create", the change of elements is
     * automatically set. For "update", the change should be filled with an
     * array of altered texts ordered by element id. If not, the process will
     * try to determine them if old values are still available. For "delete",
     * there is no change. For "import" and "export", this is an external
     * content that can't be determined inside the history log entry.
     * @return boolean False if an error occur, else true.
      */
    public function logEvent($record, $operation, $user, $change = null)
    {
        if (empty($this->record_type) || empty($this->record_id)) {
            $result = $this->_logRecord($record);
            if (empty($result)) {
                return false;
            }
        }

        $this->setOperation($operation);
        if (empty($this->operation)) {
            return false;
        }

        $this->_setPartOf($record);

        $userId = is_object($user) ? $user->id : $user;
        $this->setUserId($userId);

        // Set change according to the operation.
        switch ($this->operation) {
            case HistoryLogEntry::OPERATION_CREATE:
                $changes = $this->_findAlteredElementsForCreatedRecord($record);
                $this->_setChangesToLog($changes);
                break;
            case HistoryLogEntry::OPERATION_UPDATE:
                // The "viaPost" check allows to manage the deletion of an
                // element text before the last element text of an element.
                $viaPost = isset($record->Elements);
                $changes = $viaPost || $this->_updateType == 'element_text'
                    // Via the saved element texts.
                    ? $this->_prepareAlteredElementsForUpdate($record)
                    // The normal way.
                    : $this->_findAlteredElementsForUpdatedRecord($record);
                $this->_setChangesToLog($changes);
                break;
            case HistoryLogEntry::OPERATION_DELETE:
                $changes = $this->_findElementsForDeletedRecord($record);
                $this->_setChangesToLog($changes);
                break;
            case HistoryLogEntry::OPERATION_IMPORT:
            case HistoryLogEntry::OPERATION_EXPORT:
                $this->_setChangesToLog((string) $change);
                break;
        }

        return true;
    }

    /**
     * Prepare the log an operation on a record and set associated values.
     *
     * @param Object|array $record The Omeka record to log. It should exist at
     * the time of logging. If the operation is "update", it must be an object.
     * @return boolean False if an error occur, else true.
      */
    protected function _logRecord($record)
    {
        // Get the record object if it is an array.
        $record = $this->getRecord($record);
        if (empty($record)) {
            return false;
        }

        if (!$this->isLoggable(get_class($record), $record->id)) {
            return false;
        }

        $this->setRecordType(get_class($record));
        $this->setRecordId($record->id);

        return true;
    }

    /**
     * Rebuild an log entry "create" or "delete" for a record, if missing.
     *
     * @param array $record
     * @param string $operation
     * @return boolean Success or not. False may mean it's useless.
     */
    public function rebuildEntry($record, $operation)
    {
        switch ($this->operation) {
            case HistoryLogEntry::OPERATION_CREATE:
                return $this->_rebuildFirstEntry($record);
            case HistoryLogEntry::OPERATION_UPDATE:
                return $this->_rebuildUpdateEntry($record);
            case HistoryLogEntry::OPERATION_DELETE:
                return $this->_rebuildLastEntry($record);
        }
        return false;
    }

    /**
     * Rebuild the first entry ("create") for a record, if missing.
     *
     * @param array $record
     * @return boolean Success or not.
     */
    protected function _rebuildFirstEntry($record)
    {
        // Check if the entry need to be rebuild. The record may be deleted.
        $record = $this->getRecord($record);
        if (empty($record)) {
            return false;
        }

        // Check if this entry need to be recreated.
        $entry = $this->_db->getTable('HistoryLogEntry')
            ->getFirstEntryForRecord($record, HistoryLogEntry::OPERATION_CREATE);
        if ($entry) {
            return false;
        }

        // Normalize the current element texts.
        $elementTexts = $record->getAllElementTexts();
        $texts = array();
        foreach ($elementTexts as $elementText) {
            $texts[$elementText->element_id][] = $elementText->text;
        }

        // Get all entries, from the last.
        $entries = $this->_db->getTable('HistoryLogEntry')
            ->findBy(array(
                'record' => $record,
                // Only the update operation is useful.
                'operation' => HistoryLogEntry::OPERATION_UPDATE,
                Omeka_Db_Table::SORT_PARAM => 'added',
                Omeka_Db_Table::SORT_DIR_PARAM => 'd',
            ));

        // Revert each change of each entry.
        foreach ($entries as $entry) {
            // A count of each update by element is needed, because the update
            // operation is done in the natural order.
            $textsUpdates = array();

            $changes = $entry->getChanges();
            foreach ($changes as $change) {
                switch ($change->type) {
                    case HistoryLogChange::TYPE_CREATE:
                        if (isset($texts[$change->element_id])) {
                            $key = array_search($change->text, $texts[$change->element_id]);
                            if ($key !== false) {
                                unset($texts[$change->element_id][$key]);
                            }
                        }
                        break;

                    case HistoryLogChange::TYPE_UPDATE:
                        // TODO Get the first update of the field, that will be
                        // the oldest known value, even if not the first one.
                        break;

                    case HistoryLogChange::TYPE_DELETE:
                        if (strlen($change->text)) {
                            $texts[$change->element_id][] = $change->text;
                        }
                        break;
                }
            }
        }

        // Set the texts.
        $this->_setChangesToLog($texts);

        // Finalize the entry.
        $this->setRecordType(get_class($record));
        $this->setRecordId($record->id);
        switch (get_class($record)) {
            case 'Item':
                $this->setPartOf($record->collection_id);
                break;
            case 'File':
                $this->setPartOf($record->item_id);
                break;
            case 'Collection':
            default:
                $this->setPartOf(0);
        }
        // Some plugins like Scripto allow anonymous users.
        $user = current_user() ?: new User;
        $this->setUserId($user->id);
        $this->operaiton = HistoryLogEntry::OPERATION_CREATE;
        $this->added = $record->added;

        // TODO Remove the return "false" used for testing purpose.
        return false;
    }

    /**
     * Rebuild a full entry ("update") for a record, if "create" is missing.
     *
     * This doesn't create the first entry, but a entry with all current values
     * of the record.
     *
     * @todo Create the full entry for current records without the log "create".
     *
     * @param array $record
     * @return boolean Success or not.
     */
    protected function _rebuildUpdateEntry($record)
    {
       // TODO Remove the return "false" used for testing purpose.
        return false;
     }

    /**
     * Rebuild the last entry ("delete") for a deleted record, if missing.
     *
     * @todo Create last entry for deleted records without the log "delete".
     *
     * @param array $record
     * @return boolean Success or not.
     */
    protected function _rebuildLastEntry($record)
    {
        // Check if the last entry is missing: if there are logs, but the last
        // one is not a deletion one. The record should be deleted.
        $record = $this->getRecord($record);
        if (!empty($record)) {
            return false;
        }

        // Check if this entry need to be recreated.
        $entry = $this->_db->getTable('HistoryLogEntry')
            ->getFirstEntryForRecord($record, HistoryLogEntry::OPERATION_DELETE);
        if ($entry) {
            return false;
        }

        // Get all changes from the first.

        // Revert each change from the first.

        // TODO Remove the return "false" used for testing purpose.
        return false;
    }

    /**
     * Undelete the record.
     *
     * @return Record|boolean The record if the undeletion succeed, else false.
     */
    public function undeleteRecord()
    {
        // Get the last entry for "delete" in case the current one is not the
        // last.one.
        $logEntry = $this->isRecordUndeletable();
        if (empty($logEntry)) {
            return false;
        }

        $metadata = array();
        $elementTexts = array();

        // Get the oldest entry of the record to fill the "added" date.
        $added = null;
        $logEntryCreate = $this->_db->getTable('HistoryLogEntry')
            ->getFirstEntryForRecord(array(
                    'record_type' => $logEntry->record_type,
                    'record_id' => $logEntry->record_id,
                ), HistoryLogEntry::OPERATION_CREATE);
        if ($logEntryCreate) {
            $added = $logEntryCreate->added;
        }

        $currentUser = current_user() ?: new User;

        $changes = $logEntry->getChanges();
        foreach ($changes as $change) {
            $element = $change->getElement();
            if (empty($element)) {
                _log(__("Element #%d doesn't exist any more and can't be refilled.",
                    $change->element_id), Zend_Log::NOTICE);
                continue;
            }
            $elementSet = $element->getElementSet();
            if (empty($element)) {
                _log(__('Element Set #%d for element #%d does not exist.',
                    $element->element_set_id, $change->element_id), Zend_Log::NOTICE);
                continue;
            }
            $elementTexts[$elementSet->name][$element->name][] = array(
                'text' => $change->text,
                'html' => false,
            );
        }

        switch ($this->record_type) {
            case 'Item':
                $record = new Item();
                $record->id = $logEntry->record_id;
                $record->user = $logEntry->user_id ?: (integer) $currentUser->id;
                $record->added = $added;
                if ($logEntry->part_of) {
                    // Check if the collection still exists.
                    $collection = get_record_by_id('Collection', $logEntry->part_of);
                    if ($collection) {
                        $record->collection_id = $collection->id;
                    }
                }
                $record = update_item($record, $metadata, $elementTexts);
                break;

            case 'Collection':
                $record = new Collection();
                $record->id = $logEntry->record_id;
                $record->user = $logEntry->user_id ?: (integer) $currentUser->id;
                $record->added = $added;
                $record = update_collection($record, $metadata, $elementTexts);
                break;

            default:
                return false;
        }

        if ($record) {
            _log(__('The %s #%d has been recreated (metadata only).',
                $this->record_type, $this->record_id), Zend_Log::NOTICE);
            return $record;
        }

        return false;
    }

    /**
     * Helper to save the entry only if something is changed.
     *
     * This function can be used only during  creation.
     * This is the recommended way to save an event and to avoid empty changes.
     *
     * @return boolean|null Return null if the entry is already saved or when
     * there is no change.
     */
    public function saveIfChanged()
    {
        if ($this->_isChanged()) {
            return $this->save();
        }
        // Return null if no change.
    }

    /**
     * Helper to check if something has changed in the record.
     *
     * @return boolean
     */
    protected function _isChanged()
    {
        // Don't update a log event.
        if (!empty($this->id)) {
            return false;
        }

        // Update if there is data to log.
        if (!empty($this->_changesToLog)) {
            return true;
        }

        // There is no data to log. Nevertheless, log the operation, except for
        // update.
        if ($this->operation != HistoryLogEntry::OPERATION_UPDATE) {
            return true;
        }

        // This is an update without change, so this an internal update. For a
        // file, it may be new derivatives. For an item, it may be an update of
        // the status public or featured, a change of collection, files added,
        // reordered or removed, etc. Currently, only the change of a collection
        // of an item is logged. Data about files are saved separately.

        // Check if the collection of the item changed.
        if ($this->record_type == 'Item') {
            if (!is_null($this->_oldPartOf) || $this->_isPrepared) {
                return $this->_oldPartOf != $this->part_of;
            }

            // Get the old record to check it.
            try {
                $oldRecord = get_record_by_id('Item', $this->record_id);
            } catch(Exception $e) {
                return true;
            }

            return $oldRecord->collection_id != $this->part_of;
        }

        // For all other cases, there is no change.
        return false;
    }

    /**
     * Sets the record type.
     *
     * @internal Check is done during validation.
     *
     * @param int $type The record type.
     */
    public function setRecordType($type)
    {
        $this->record_type = $type;
    }

    /**
     * Sets the record id.
     *
     * @param int $id The record id.
     */
    public function setRecordId($id)
    {
        $this->record_id = (integer) $id;
    }

    /**
     * Sets the part of id.
     *
     * @param int $part_of The part of.
     */
    public function setPartOf($partOf)
    {
        $this->part_of = (integer) $partOf;
    }

    /**
     * Determine and set the "part_of".
     *
     * The "record_type" should be set before.
     *
     * @param Record $record
     * @return void
     */
    protected function _setPartOf($record)
    {
        switch ($this->record_type) {
            case 'Item':
                $this->setPartOf($record->collection_id);
                break;
            case 'File':
                $this->setPartOf($record->item_id);
                break;
            case 'Collection':
            default:
                $this->setPartOf(0);
        }
    }

    /**
     * Sets the user id.
     *
     * @param int $id The user id.
     */
    public function setUserId($id)
    {
        $this->user_id = (integer) $id;
    }

    /**
     * Set the operation.
     *
     * @param string $operation
     */
    public function setOperation($operation)
    {
        if ($this->_isOperationValid($operation)) {
            $this->operation = $operation;
        }
    }

    /**
     * Get the current record object or the specified one from an array.
     *
     * @internal The record of an entry may be deleted. No check is done.
     *
     * @param array $record The record with record type and record id.
     * @return Record|null The record, else null if deleted.
     */
    public function getRecord($record = null)
    {
        if (is_null($record)) {
            $recordType = $this->record_type;
            $recordId = $this->record_id;
        }
        elseif (is_object($record)) {
            return $record;
        }
        elseif (is_array($record)) {
            // Normal array.
            if (isset($record['record_type']) && isset($record['record_id'])) {
                $recordType = $record['record_type'];
                $recordId = $record['record_id'];
            }
            elseif (isset($record['type']) && isset($record['id'])) {
                $recordType = $record['type'];
                $recordId = $record['id'];
            }
            // One row in the array.
            elseif (count($record) == 1) {
                $recordId = reset($record);
                $recordType = key($record);
            }
            // Two rows in the array.
            elseif (count($record) == 2) {
                $recordType = array_shift($record);
                $recordId = array_shift($record);
            }
            // Record not determinable.
            else {
                return;
            }
        }
        // No record.
        else {
            return;
        }

        // Manage the case where record type has been removed.
        if (class_exists($recordType)) {
            return $this->getTable($recordType)->find($recordId);
        }
    }

    /**
     * Return the associated record of the record saved in the current entry.
     *
     * @return Record|array|null The record if it exists, an array if it was
     * deleted or null if the record is not a part.
     */
    public function getPartOfRecord()
    {
        if (empty($this->part_of)) {
            return;
        }

        switch ($this->record_type) {
            case 'Item':
                $record = get_record_by_id('Collection', $this->part_of);
                return $record ?: array(
                    'record_type' => 'Collection',
                    'record_id' => $this->part_of,
                );

            case 'File':
                $record = get_record_by_id('Item', $this->part_of);
                return $record ?: array(
                    'record_type' => 'Item',
                    'record_id' => $this->part_of,
                );
        }
    }

    /**
     * Returns the list of Change objects related to this entry.
     *
     * @return HistoryLogChange Array of Change objects related to the entry.
     */
    public function getChanges()
    {
        if (empty($this->_changes)) {
            $this->_changes = $this->getTable('HistoryLogChange')
                ->findByEntry($this->id);
        }
        return $this->_changes;
    }

    /**
     * Returns the list of changed element ids related to this entry.
     *
     * @return array List of element ids. 0 is not returned, because it's not an
     * element id.
     */
    public function getElementIds()
    {
        return $this->getTable('HistoryLogChange')
            ->getElementIds($this);
    }

    /**
     * Returns the list of changed element ids related to the record.
     *
     * @return array List of element ids. 0 is not returned, because it's not an
     * element id.
     */
    public function getElementIdsByRecord()
    {
        $record = array(
            'record_type' => $this->record_type,
            'record_id' => $this->record_id,
        );
        return $this->getTable('HistoryLogEntry')
            ->getElementIdsForRecord($record);
    }

    /**
     * Check if the current record or any other one is loggable (item,
     * collection, file).
     *
     * @param string $recordType
     * @param integer $recordId
     * @return boolean
     */
    public function isLoggable($recordType = null, $recordId = null)
    {
        if (is_null($recordType)) {
            $recordType = $this->record_type;
        }
        if (is_null($recordId)) {
            $recordId = $this->record_id;
        }
        $recordId = (integer) $recordId;
        return !empty($recordId)
            && in_array($recordType, $this->_validRecordTypes);
    }

    /**
     * Check if the record is undeletable with this log entry.
     *
     * @return boolean True if this is the entry to undelete the record.
     */
    public function isEntryToUndelete()
    {
        if (!in_array($this->record_type, array('Item', 'Collection'))) {
            return false;
        }

        if ($this->operation != HistoryLogEntry::OPERATION_DELETE) {
            return false;
        }

        $record = $this->getRecord();
        if ($record) {
            return false;
        }

        // Check if the last operation is a deletion.
        $logEntry = $this->_db->getTable('HistoryLogEntry')
            ->getLastEntryForRecord(array(
                    'record_type' => $this->record_type,
                    'record_id' => $this->record_id,
                ), HistoryLogEntry::OPERATION_DELETE);

        if (empty($logEntry)) {
            return false;
        }

        return $logEntry->id == $this->id;
    }

    /**
     * Check if the record is undeletable and return the log entry.
     *
     * @return HistoryLogEntry|null The last entry for undelete, else null.
     */
    public function isRecordUndeletable()
    {
        if (!in_array($this->record_type, array('Item', 'Collection'))) {
            return false;
        }

        $record = $this->getRecord();
        if ($record) {
            return false;
        }

        // Check if the last operation is a deletion.
        $logEntry = $this->_db->getTable('HistoryLogEntry')
            ->getLastEntryForRecord(array(
                    'record_type' => $this->record_type,
                    'record_id' => $this->record_id,
                ), HistoryLogEntry::OPERATION_DELETE);

        return $logEntry;
    }

    /**
     * Executes after the record is saved.
     *
     * @internal See Mixin_ElementText::beforeSaveElements() for a fully secured
     * way to save changes. This is useless here, because changes are set here.
     *
     * @param array $args
     */
    protected function afterSave($args)
    {
        $this->_saveChanges();
    }

    /**
     * Add one or multiple changes.
     *
     * @param string|array $changes
     */
    protected function _setChangesToLog($changes)
    {
        $this->_changesToLog = $changes;
    }

    /**
     * Save changes.
     *
     * Entries are not designed to be updated, so the current changes are kept
     * and can't be removed by normal ways.
     */
    protected function _saveChanges()
    {
        $changes = $this->_changesToLog;
        if (empty($changes)) {
            return;
        }

        // Simplify the process for strings.
        if (!is_array($changes)) {
            $changes = array(
                // This is not an element id, so "0".
                0 => array(
                    // There is no process, only a text.
                    array(HistoryLogChange::TYPE_NONE => (string) $changes),
            ));
        }

        foreach ($changes as $elementId => $texts) {
            foreach ($texts as $process) {
                $change = new HistoryLogChange();
                $change->entry_id = $this->id;
                $change->element_id = $elementId;
                $change->type = key($process);
                $change->text = reset($process);
                $change->save();
            }
        }

        // Reset the changes in order to get old and new ones.
        // Normally, there is no old change.
        $this->_changes = null;
        $this->getChanges();
    }

    /**
     * Helper to find out altered elements of a created record.
     *
     * Notes:
     * - Each text of repeatable field is returned.
     * - Checks are done according to the natural order.
     *
     * @param Record $record Record must be an object.
     * @return array|null Associative array of element ids and array of texts of
     * created elements.
     */
    protected function _findAlteredElementsForCreatedRecord($record)
    {
        // Get the current list of elements.
        $newElements = array();

        // If there are elements, the record is created via post (manually).
        $viaPost = isset($record->Elements);
        // Manual insert.
        if ($viaPost) {
            foreach ($record->Elements as $elementId => $elementTexts) {
                foreach ($elementTexts as $elementText) {
                    // strlen() is used to allow values like "0".
                    // But Omeka uses a simple empty() check.
                    if (strlen($elementText['text']) > 0) {
                        $newElements[$elementId][] = array(
                            HistoryLogChange::TYPE_CREATE => $elementText['text'],
                        );
                    }
                }
            }
        }

        // Else this is an automatic creation, without post.
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
                $newElements[$elementText->element_id][] = array(
                    HistoryLogChange::TYPE_CREATE => $elementText['text'],
                );
            }
        }

        return $newElements;
    }

    /**
     * Helper to prepare altered elements of an updated  record.
     *
     * Notes:
     * - Each text of repeatable field is returned.
     * - Checks are done according to the natural order.
     *
     * @param Record $record Record must be an object.
     * @return array|null Associative array of element ids and array of texts of
     * altered elements.
     */
    protected function _prepareAlteredElementsForUpdate($record)
    {
        $result = array();

        $changedTexts = $this->_changedTexts;

        foreach ($changedTexts as $elementId => $changeTypes) {
            // Types should be processed in the order "create", "update" and
            // "delete".
            $keys = array();
            // Process created terms.
            if (isset($changeTypes[HistoryLogChange::TYPE_CREATE])) {
                foreach ($changeTypes[HistoryLogChange::TYPE_CREATE] as $term) {
                    // If there is a false update, this is a false create.
                    $result[$elementId][] = array(
                        HistoryLogChange::TYPE_CREATE => $term,
                    );
                }
            }

            // Process updated terms and update deleted if needed.
            // This process check if a previous element texts is removed, so
            // the next ones are not really updated.
            if (isset($changeTypes[HistoryLogChange::TYPE_UPDATE])) {
                foreach ($changeTypes[HistoryLogChange::TYPE_UPDATE] as $oldNewTerm) {
                    // All texts should be strings.
                    if (((string) $oldNewTerm['new']) == ((string) $oldNewTerm['old'])) {
                        continue;
                    }

                    $key = isset($changedTexts[$elementId][HistoryLogChange::TYPE_DELETE])
                        ? array_search($oldNewTerm['new'], $changedTexts[$elementId][HistoryLogChange::TYPE_DELETE], true)
                        : false;
                    // Remove of a non last text.
                    if ($key !== false) {
                        $result[$elementId][] = array(
                            HistoryLogChange::TYPE_DELETE => $oldNewTerm['old'],
                        );
                        // Unset the current array may be unprevisible.
                        $keys[$key] = true;
                    }
                    // Normal update.
                    else {
                        $result[$elementId][] = array(
                            HistoryLogChange::TYPE_UPDATE => $oldNewTerm['new'],
                        );
                    }
                }
            }

            // Process updated terms and update deleted if needed.
            if (isset($changeTypes[HistoryLogChange::TYPE_DELETE])) {
                foreach ($changeTypes[HistoryLogChange::TYPE_DELETE] as $key => $term) {
                    if (!isset($keys[$key])) {
                        $result[$elementId][] = array(
                            HistoryLogChange::TYPE_DELETE => $term,
                        );
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Helper to find out altered elements of an updated  record.
     *
     * Notes:
     * - Each text of repeatable field is returned.
     * - Checks are done according to the natural order.
     *
     * @param Record $record Record must be an object.
     * @return array|null Associative array of element ids and array of texts of
     * altered elements.
     */
    protected function _findAlteredElementsForUpdatedRecord($record)
    {
        // The operation is an update. The old record and the new one are
        // compared to check if there are altered (added, updated, removed)
        // element texts.

        $oldElements = $this->_oldTexts;
        $newElements = $this->_getNewElements($record);

        // Updated elements are the ones that have been added, updated or
        // deleted.
        $updatedElements = array();
        foreach ($oldElements as $elementId => $oldTexts) {
            // Updated element.
            if (isset($newElements[$elementId])) {
                $newTexts = $newElements[$elementId];
                foreach ($oldTexts as $key => $oldText) {
                    // The value has been modified, so the new text is logged.
                    if (isset($newTexts[$key])) {
                        if ($newTexts[$key] !== $oldText) {
                            $updatedElements[$elementId][] = array(
                                HistoryLogChange::TYPE_UPDATE => $newTexts[$key],
                            );
                        }
                        // Else no change.
                    }
                    // The value has been deleted. The old text is logged.
                    else {
                        $updatedElements[$elementId][] = array(
                            HistoryLogChange::TYPE_DELETE => $oldText,
                        );
                    }
                }
                // Check if there are more keys in the new texts.
                if (count($newTexts) > count($oldTexts)) {
                    for ($i = count($oldTexts); $i < count($newTexts); $i++) {
                        $updatedElements[$elementId][] = array(
                            HistoryLogChange::TYPE_CREATE => $newTexts[$i],
                        );
                    }
                }
            }
            // All values of an element have been deleted. They are logged all
            // to keep a track of a deleted item and to simplify the revert
            // process.
            else {
                foreach ($oldTexts as $key => $oldText) {
                    $updatedElements[$elementId][] = array(
                        HistoryLogChange::TYPE_DELETE => $oldText,
                    );
                }
            }
        }

        // Check new texts for elements that weren't in the old ones.
        $newElementsIds = array_diff(array_keys($newElements), array_keys($oldElements));
        foreach ($newElements as $elementId => $newTexts) {
            if (in_array($elementId, $newElementsIds)) {
                foreach ($newTexts as $newText) {
                    $updatedElements[$elementId][] = array(
                        HistoryLogChange::TYPE_CREATE => $newText,
                    );
                }
            }
        }

        return $updatedElements;
    }

    /**
     * Helper to get current elements of a record via post or database.
     *
     * @param Record $record
     * @return array|null
     */
    protected function _getNewElements($record)
    {
        if (is_null($this->_newElements)) {
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

            $this->_newElements = $newElements;
        }

        return $this->_newElements;
    }

    /**
     * Helper to find out all elements of a deleted record.
     *
     * Notes:
     * - Each text of repeatable field is returned.
     * - Checks are done according to the natural order.
     *
     * @param Record $record Record must be an object.
     * @return array|null Associative array of element ids and array of texts of
     * the deleted record.
     */
    protected function _findElementsForDeletedRecord($record)
    {
        // Get the old list of elements.
        $currentElements = array();

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
            $currentElements[$elementText->element_id][] = array(
                HistoryLogChange::TYPE_DELETE => $elementText['text'],
            );
        }

        return $currentElements;
    }

    /**
     * Helper to get the list of referenced elements for the entry.
     *
     * @return array|null List of elements by element id. If an element has been
     * removed, its value is null.
     */
    protected function _getReferencedElements()
    {
        $elementIds = $this->getElementIds();
        if (empty($elementIds)) {
            return;
        }
        return $this->_getElementsFromIds($elementIds);
    }

    /**
     * Helper to get the list of referenced elements for a record.
     *
     * @return array|null List of elements by element id. If an element has been
     * removed, its value is null.
     */
    protected function _getReferencedElementsByRecord()
    {
        $elementIds = $this->getElementIdsByRecord();
        if (empty($elementIds)) {
            return;
        }
        return $this->_getElementsFromIds($elementIds);
    }

    /**
     * Helper to get the list of elements from ids, even if removed.
     *
     * @param array $elementIds
     * @return array|null List of elements by element id. If an element has been
     * removed, its value is null.
     */
    protected function _getElementsFromIds($elementIds)
    {
        // Initialize the list of all element ids.
        $referenceds = array_fill_keys($elementIds, null);

        // Get the list of elements that still exist.
        $table = $this->_db->getTable('Element');
        $alias = $table->getTableAlias();
        $result = $table->findBySql($alias . '.id IN (' . implode(',', $elementIds) . ')');
        foreach ($result as $element) {
            $referenceds[$element->id] = $element;
        }

        return $referenceds;
    }

    // TODO Move all displays in a specific view helper.

    /**
     * Retrieve username of an omeka user by user ID.
     *
     * @return string The username of the Omeka user
     */
    public function displayUser()
    {
        $user = $this->getOwner();
        if (empty($user)) {
            return $this->user_id
                ? __('Deleted user [%d]', $this->user_id)
                : __('Anonymous user');
        }
        return $user->name . ' (' . $user->username . ')';
    }

    /**
     * Retrieve the "part of" type and id, if any, as raw text or url to logs.
     *
     * @param boolean $asUrl
     * @return string The part of, if any.
     */
    public function displayPartOf($asUrl = false)
    {
        $partOf = $this->getPartOfRecord();
        if (empty($partOf)) {
            return;
        }

        switch ($this->record_type) {
            case 'Item':
                $title = is_array($partOf)
                    ? __('Collection %d [deleted]', $this->part_of)
                    : __('Collection %d', $this->part_of);
                return $asUrl
                    ? sprintf('<a href="%s">%s</a>',
                        url(array(
                                'type' => 'collections',
                                'id' => $this->part_of,
                            ), 'history_log_record_log'),
                        $title)
                    : $title;

            case 'File':
                $title = empty($partOf)
                    ? __('Item %d [deleted]', $this->part_of)
                    : __('Item %d', $this->part_of);
                return $asUrl
                    ? sprintf('<a href="%s">%s</a>',
                        url(array(
                                'type' => 'items',
                                'id' => $this->part_of,
                            ), 'history_log_record_log'),
                        $title)
                    : $title;
                break;
        }
    }

    /**
     * Retrieve displayable name of an operation.
     *
     * @return string User displayable operation name.
     */
    public function displayOperation()
    {
        switch ($this->operation) {
            case HistoryLogEntry::OPERATION_CREATE:
                return __('Create');
            case HistoryLogEntry::OPERATION_UPDATE:
                return __('Update');
            case HistoryLogEntry::OPERATION_DELETE:
                return __('Delete');
            case HistoryLogEntry::OPERATION_IMPORT:
                return __('Import');
            case HistoryLogEntry::OPERATION_EXPORT:
                return __('Export');
            // Manage extra type of operation.
            default:
                return ucfirst($this->operation);
        }
    }

    /**
     * Retrieve "change" parameter for the displayable form.
     *
     * @return string The change in a human readable form.
     */
    public function displayChanges()
    {
        // The encoding is different depending on the type of event, so we
        // define different decoding methods for each event type.
        $changes = $this->getChanges();
        switch ($this->operation) {
            // Array for created and updated records.
            case HistoryLogEntry::OPERATION_CREATE:
                return $changes
                    ? $this->_displayElements()
                    : __('Created manually by user');

            case HistoryLogEntry::OPERATION_UPDATE:
                return $changes
                    ? $this->_displayElements()
                    // Internal update: file upload, public/featured...
                    : __('Internal update');

            // Nothing for delete.
            case HistoryLogEntry::OPERATION_DELETE:
                return '';

            // String for import and export.
            case HistoryLogEntry::OPERATION_IMPORT:
                $change = reset($changes);
                return empty($change->text)
                    ? ''
                    : __('Imported from %s', $change->text);

            case HistoryLogEntry::OPERATION_EXPORT:
                $change = reset($changes);
                return empty($change)
                    ? ''
                    : __('Exported to: %s', $change->text);
        }
    }

    /**
     * Helper to display the list of altered elements.
     *
     * @return string
     */
    protected function _displayElements()
     {
         // TODO Only the element name is needed.
        $elements = $this->_getReferencedElements();
        if (empty($elements)) {
            return __('No element.');
        }
        $changes = $this->getChanges();
        $result = array(
            __('Created') => array(),
            __('Updated') => array(),
            __('Deleted') => array(),
            __('Unchanged') => array(),
            __('Altered') => array(),
        );
        foreach ($changes as $change) {
            switch ($change->type) {
                case HistoryLogChange::TYPE_CREATE:
                    $type = __('Created');
                    break;
                case HistoryLogChange::TYPE_UPDATE:
                    $type = __('Updated');
                    break;
                case HistoryLogChange::TYPE_DELETE:
                    $type = __('Deleted');
                    break;
                case HistoryLogChange::TYPE_NONE:
                    $type = __('Unchanged');
                    break;
                default:
                    $type = __('Altered');
                    break;
            }
            $result[$type][] = empty($elements[$change->element_id])
                ? __('Unrecognized element #%d', $change->element_id)
                : $elements[$change->element_id]->name;
        }
        $result = array_filter($result);
        foreach ($result as $type => &$r) {
            $r = __('%s: %s', $type, implode(', ', array_unique($r)));
        }
        $result = implode(";\n", $result);
        return $result;
     }

    /**
     * Helper to display the list of altered elements.
     *
     * @return string
     */
    protected function _displayAlteredElements()
     {
         // TODO Only the element name is needed.
        $elements = $this->_getReferencedElements();
        if (empty($elements)) {
            return __('No element.');
        }
        $result = array();
        foreach ($elements as $elementId => $element) {
            $result[] = $element
                ? $element->name
                : __('Unrecognized element #%d', $elementId);
        }
        return __('Altered: %s', implode(', ', $result));
     }

    /**
     * Format a date in standard form.
     *
     * @return string The formatted dateTime
     */
    public function displayAdded()
    {
        // TODO Clearly, not yet fully implemented.
        return $this->added;
    }

    /**
     * Retrieves the current title, that may be different from the stored title.
     *
     * @return string The current Dublin Core title of the record if any.
     */
    public function displayCurrentTitle()
    {
        if ($this->operation == HistoryLogEntry::OPERATION_DELETE) {
            return __('[Deleted record]');
        }

        $record = $this->getRecord();
        if (empty($record)) {
            return __('[Deleted record]');
        }

        $etTitles = $record->getElementTexts('Dublin Core', 'Title');
        return isset($etTitles[0]) ? $etTitles[0]->text : '';
    }

    /**
     * Simple validation.
     */
    protected function _validate()
    {
        if (empty($this->record_id)) {
            $this->addError('record_id', __('Record cannot be empty.'));
        }
        if (!$this->isLoggable()) {
            $this->addError('record_type', __('Type of record "%s" is not correct.', $this->record_type));
        }
        if (!$this->_isOperationValid()) {
            $this->addError('operation', __('Operation "%s" is not correct.', $this->operation));
        }
    }

    /**
     * Check if the operation is valid.
     *
     * @param string $operation
     * @return boolean
     */
    protected function _isOperationValid($operation = null)
    {
        if (is_null($operation)) {
            $operation = $this->operation;
        }
        return in_array($operation, array(
            HistoryLogEntry::OPERATION_CREATE,
            HistoryLogEntry::OPERATION_UPDATE,
            HistoryLogEntry::OPERATION_DELETE,
            HistoryLogEntry::OPERATION_IMPORT,
            HistoryLogEntry::OPERATION_EXPORT,
        ));
    }

    /**
     * Get a property or special value of this record.
     *
     * @param string $property
     * @return mixed
     */
    public function getProperty($property)
    {
        switch($property) {
            case 'record':
                return $this->getRecord();
            default:
                return parent::getProperty($property);
        }
    }

    /**
     * Get the ACL resource ID for the record.
     *
     * @return string
     */
    public function getResourceId()
    {
        return 'HistoryLogEntries';
    }
}
