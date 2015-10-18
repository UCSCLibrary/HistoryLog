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
     * @var string More information about the action being performed.
     * For modifications, this stores the elements modified.
     * For exports, it stores the export method.
     * @internal Arrays of element ids are saved as set of values separated with
     * a space: "[ 50 49 28 ]". Spaces make indexing and search of a particular
     * id by sql easier: "WHERE change LIKE concat("% ", searched_id, " %")"
     * returns the good result.
     * Internally, the value "_change" below is used, and "change" is updated
     * before save.
     */
    public $change;

    /**
     * @var string The UTF formatted date and time when the log took place.
     */
    public $added;

    /**
     * @var string The dublin core title at the time of log entry.
     */
    public $title;

    /**
     * Cleaned values for "change".
     */
    private $_change;

    /**
     * Initialize the mixins for a record.
     */
    protected function _initializeMixins()
    {
        // TODO Add mixin for user.
        $this->_mixins[] = new Mixin_Timestamp($this, 'added', null);
    }

    /**
     * Log an operation on a record and set associated values.
     *
     * This is the recommended method to log an event.
     *
     * @internal Checks  are done here and during validation.
     *
     * @param Object|array $record The Omeka record to log. It should exist at
     * the time of logging. If the operation is "update", it must be an object.
     * @param User|integer $user
     * @param string $operation The type of event to log (e.g. "create"...).
     * @param string|array $change An extra piece of type specific data for the
     * log. When the operation is "create" or "update", the change of elements
     * is automatically set. For "delete", there is no change. For "import" and
     * "export", it'is an external content that can't be determined inside the
     * history log entry.
     * @return boolean False if an error occur, else true.
      */
    public function logEvent($record, $user, $operation, $change = null)
    {
        $this->setOperation($operation);
        if (empty($this->operation)) {
            return false;
            // throw __('Operation "%s" is not allowed.', $operation);
        }

        // Special check for 'update": an object is required to find old
        // elements.
        if ($this->operation == HistoryLogEntry::OPERATION_UPDATE && !is_object($record)) {
            return false;
            // throw __('Operation "Update" cannot be logged if the record is not an object.');
        }

        // Get the record object if it is an array.
        if (is_array($record)) {
            $record = $this->getRecord();
            if (empty($record)) {
                return false;
            }
        }

        if (!$this->_isLoggable(get_class($record), $record->id)) {
            return false;
        }

        $this->setRecordType(get_class($record));
        $this->setRecordId($record->id);

        // Set the "part_of" if needed.
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

        // Set current title if needed.
        if (in_array($this->operation, array(
                HistoryLogEntry::OPERATION_CREATE,
                HistoryLogEntry::OPERATION_UPDATE,
            ))) {
            $titles = $record->getElementTexts('Dublin Core', 'Title');
            $this->setTitle(isset($titles[0]) ? $titles[0] : '');
        }

        $userId = is_object($user) ? $user->id : $user;
        $this->setUserId($userId);

        // Set change according to the operation.
        switch ($this->operation) {
            case HistoryLogEntry::OPERATION_CREATE:
            case HistoryLogEntry::OPERATION_UPDATE:
                $changes = $this->_findAlteredElements($record, $this->operation);
                $this->setChange($changes);
                break;
            case HistoryLogEntry::OPERATION_DELETE:
                $this->setChange('');
                break;
            case HistoryLogEntry::OPERATION_IMPORT:
            case HistoryLogEntry::OPERATION_EXPORT:
                $this->setChange((string) $change);
                break;
        }

        return true;
    }

    /**
     * Sets the record type.
     *
     * @internal Check is done during validation.
     *
     * @param int $id The record type
     */
    public function setRecordType($type)
    {
        $this->record_type = $type;
    }

    /**
     * Sets the record id.
     *
     * @param int $id The record id
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
     * Set the change.
     *
     * @param string|array $change
     */
    public function setChange($change)
    {
        $this->_change = $change;
    }

    /**
     * Set the title.
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = (string) $title;
    }

    /**
     * Get the record object.
     *
     * @return Record
     */
    public function getRecord()
    {
        // Manage the case where record type has been removed.
        if (class_exists($this->record_type)) {
            return $this->getTable($this->record_type)->find($this->record_id);
        }
    }

    /**
     * Returns the change or the list of element ids that change.
     *
     * @return string|array The change or the list of element ids that change.
     */
    public function getChange()
    {
        if ($this->_change === null) {
            $this->_change = $this->change;
            if (empty($this->change)) {
                $this->_change = '';
            }
            // Check if change is an imploded array.
            elseif (strpos($this->change, '[ ') === 0
                    && strrpos($this->change, ' ]') === strlen($this->change) - 2
                ) {
                $change = explode(' ', trim($this->change, '[ ]'));
                $change = array_filter($change);
                // Check if this is a string or an array of integers.
                if (count($change) && is_numeric($change[0])) {
                    $this->_change = $change;
                }
            }
        }
        return $this->_change;
    }

    /**
     * Executes before the record is saved.
     *
     * @param array $args
     */
    protected function beforeSave($args)
    {
        $change = $this->getChange();
        $this->change = is_array($change) && !empty($change)
            ? '[ ' . implode(' ', $change) . ' ]'
            : (string) $change;
    }

    /**
     * Helper to find out altered elements of a record created or updated.
     *
     * @param Record $record Record must be an object.
     * @param string $operation Only "create" and "update" have elements.
     * @return array|null List of element IDs of created or altered elements.
     */
    protected function _findAlteredElements($record, $operation)
    {
        if (!is_object($record)) {
            throw __('Record should be an object.');
        }

         if (!isset($record->Elements)) {
            return;
        }
        $newElements = $record->Elements;

        switch ($operation) {
            case HistoryLogEntry::OPERATION_CREATE:
                $listElementIds = array();
                foreach ($newElements as $elementId => $elementTexts) {
                    foreach ($elementTexts as $elementText) {
                        // strlen() is used to allow values like "0".
                        if (strlen($elementText['text']) > 0) {
                            $listElementIds[] = $elementId;
                            break;
                        }
                    }
                }
                return $listElementIds;

            case HistoryLogEntry::OPERATION_UPDATE:
                $changedElements = array();
                try {
                    $oldRecord = get_record_by_id(get_class($record), $record->id);
                } catch(Exception $e) {
                    throw $e;
                }

                foreach ($newElements as $newElementId => $newElementTexts) {
                    $flag = false;

                    try {
                        $element = get_record_by_id('Element', $newElementId);
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
                        $changedElements[] = $newElementId;
                    }
                }

                return $changedElements;

            default:
                return;
        }
    }

    /**
     * Helper to get the list of referenced elements.
     *
     * @return array|null List of elements by element id. If an element has been
     * removed, its value is null.
     */
    protected function _getReferencedElements()
    {
        $change = $this->getChange();
        // Check if this is a list of elements (ids) or a string.
        if (empty($change) || !is_array($change)) {
            return;
        }

        // Else "change" is one or multiple values in a array.
        // Get the list of elements that still exist.
        $table = $this->_db->getTable('Element');
        $alias = $table->getTableAlias();
        $result = $table->findBySql($alias . '.id IN (' . implode(',', $change) .')');
        // Add them in the list of all elements.
        $referenceds = array_fill_keys($change, null);
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
        $user = get_record_by_id('User', $this->user_id);
        if (empty($user)) {
            return __('No user / deleted user [%d]', $this->user_id);
        }
        return $user->name . ' (' . $user->username . ')';
    }

    /**
     * Retrieve displayable name of an operation by its slug
     *
     * @return string User displayable operation name
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
    public function displayChange()
    {
        // The encoding is different depending on the type of event, so we
        // define different decoding methods for each event type.
        $change = $this->getChange();
        switch ($this->operation) {
            // Array for created and updated records.
            case HistoryLogEntry::OPERATION_CREATE:
                return $change
                    ? $this->_displayElements()
                    : __('Created manually by user');

            case HistoryLogEntry::OPERATION_UPDATE:
                return $change
                    ? $this->_displayElements()
                    // Internal update: file upload, public/featured...
                    : __('Internal update');

            // Nothing for delete.
            case HistoryLogEntry::OPERATION_DELETE:
                return '';

            // String for import and export.
            case HistoryLogEntry::OPERATION_IMPORT:
                return empty($change)
                    ? ''
                    : __('Imported from %s', $change);

            case HistoryLogEntry::OPERATION_EXPORT:
                return empty($change)
                    ? ''
                    : __('Exported to: %s', $change);
        }
    }

    /**
     * Helper to display the list of elements in change.
     *
     * @return string
     */
     protected function _displayElements()
     {
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
        return __('Metadata altered: %s', implode(', ', $result));
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
     * Display the stored title.
     *
     * @return string The stored title of the record if any.
     */
    public function displayTitle()
    {
        return $this->title;
    }

    /**
     * Retrieves the current title, that may be different from the stored title.
     *
     * @return string The current Dublin Core title of the record if any.
     */
    public function displayCurrentTitle()
    {
        if ($this->operation == HistoryLogEntry::OPERATION_DELETE) {
            return __('Deleted record');
        }

        $record = $this->getRecord();
        if (empty($record)) {
            return __('Deleted record');
        }

        $titles = $record->getElementTexts('Dublin Core', 'Title');
        return isset($titles[0]) ? $titles[0] : '';
    }

    /**
     * Simple validation.
     */
    protected function _validate()
    {
        if (empty($this->record_id)) {
            $this->addError('record_id', __('Record cannot be empty.'));
        }
        if (!$this->_isLoggable()) {
            $this->addError('record_type', __('Type of record "%s" is not correct.', $this->record_type));
        }
        if (!$this->_isOperationValid()) {
            $this->addError('operation', __('Operation "%s" is not correct.', $this->operation));
        }
        if (is_null($this->change)) {
            $this->change = '';
        }
        if (is_null($this->title)) {
            $this->title = '';
        }
    }

    /**
     * Check if the record is loggable (item, collection, file).
     *
     * @param string $recordType
     * @param integer $recordId
     * @return boolean
     */
    protected function _isLoggable($recordType = null, $recordId = null)
    {
        if (is_null($recordType)) {
            $recordType = $this->record_type;
        }
        if (is_null($recordId)) {
            $recordId = $this->record_id;
        }
        return !empty($recordId)
            && in_array($recordType, $this->_validRecordTypes);
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

    public function getProperty($property)
    {
        switch($property) {
            case 'record':
                return $this->getRecord();
            default:
                return parent::getProperty($property);
        }
    }

    public function getResourceId()
    {
        return 'HistoryLogEntry';
    }
}
