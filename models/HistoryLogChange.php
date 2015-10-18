<?php
/**
 * A History Log Change
 *
 * @package Historylog
 *
 */
class HistoryLogChange extends Omeka_Record_AbstractRecord
{
    const TYPE_NONE = 'none';
    const TYPE_CREATE = 'create';
    const TYPE_UPDATE = 'update';
    const TYPE_DELETE = 'delete';

    /**
     * @var int The record ID.
     */
    public $id;

    /**
     * @var int The entry id from the table HistoryLogEntry.
     */
    public $entry_id;

    /**
     * @var int The id of the element that is updated. "0" if there is no
     * element, in particular for operation "import" and "Export".
     */
    public $element_id;

    /**
     * @var string The limited list of type of change being processed:
     * "none" (for import or export), "create", "update", "delete".
     */
    public $type;

    /**
     * @var string The new content of the element after the update. If there is
     * no element, the  value depends on the operation (source, service...).
     */
    public $text;

    /**
     * Records related to an Item.
     *
     * @var array
     */
    protected $_related = array(
        'Record' => 'getRecord',
        'Entry' => 'getEntry',
        'Element' => 'getElement',
    );

    /**
     * Cache the entry for this record.
     *
     * @var HistoryLogEntry
     */
    private $_entry;

    /**
     * Get the Record this change belongs to.
     *
     * @return Record
     */
    public function getRecord()
    {
        $entry = $this->getEntry();
        if ($entry) {
            return $entry->getRecord();
        }
    }

    /**
     * Get the Entry this change belongs to.
     *
     * @return Entry
     */
    public function getEntry()
    {
        if (empty($this->_entry)) {
            $this->_entry = $this->getTable('HistoryLogEntry')->find($this->entry_id);
        }
        return $this->_entry;
    }

    /**
     * Get the Element this change belongs to.
     *
     * @return Element
     */
    public function getElement()
    {
        if ($this->element_id != 0) {
            return $this->getTable('Element')->find($this->element_id);
        }
    }

    /**
     * Helper to display the element of this change.
     *
     * @return string
     */
    public function displayChange()
    {
        // Special change.
        if ($this->element_id == 0) {
            $entry = $this->getEntry();
            switch ($entry->operation) {
                case HistoryLogEntry::OPERATION_IMPORT:
                    return empty($this->text)
                        ? __('Imported')
                        : __('Imported from %s', $this->text);
                case HistoryLogEntry::OPERATION_EXPORT:
                    return empty($this->text)
                        ? __('Exported')
                        : __('Exported to %s', $this->text);
            }
            return;
        }

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

        // Normal element, possibly removed.
        $element = $this->getElement();
        return empty($element)
            ? __('Unrecognized element #%d', $this->element_id)
            : sprintf($type . ': ' . $element->name);
     }

    // Wrappers for the Entry.

     /**
     * Wrapper to retrieve username of an omeka user by user ID.
     *
     * @return string The username of the Omeka user
     */
    public function displayUser()
    {
        $entry = $this->getEntry();
        if ($entry) {
            return $entry->displayUser();
        }
    }

    /**
     * Wrapper to retrieve displayable name of an operation.
     *
     * @return string User displayable operation name.
     */
    public function displayOperation()
    {
        $entry = $this->getEntry();
        if ($entry) {
            return $entry->displayOperation();
        }
    }

    /**
     * Wrapper to format a date in standard form.
     *
     * @return string The formatted dateTime
     */
    public function displayAdded()
    {
        $entry = $this->getEntry();
        if ($entry) {
            return $entry->displayAdded();
        }
    }

    /**
     * Wrapper to retrieves the current title, that may be different from the
     * stored title.
     *
     * @return string The current Dublin Core title of the record if any.
     */
    public function displayCurrentTitle()
    {
        $entry = $this->getEntry();
        if ($entry) {
            return $entry->displayCurrentTitle();
        }
    }

    /**
     * Validate this record.
     */
    protected function _validate()
    {
        if (!$this->_isTypeValid()) {
            $this->addError('type', __('Type "%s" is not correct.', $this->type));
        }
    }

    /**
     * Check if the type is valid.
     *
     * @param string $type
     * @return boolean
     */
    protected function _isTypeValid($type = null)
    {
        if (is_null($type)) {
            $type = $this->type;
        }
        return in_array($type, array(
            HistoryLogChange::TYPE_NONE,
            HistoryLogChange::TYPE_CREATE,
            HistoryLogChange::TYPE_UPDATE,
            HistoryLogChange::TYPE_DELETE,
        ));
    }

    /**
     * Return whether this change is owned by the given user.
     *
     * Proxies to the Entry's isOwnedBy.
     *
     * @uses Ownable::isOwnedBy
     * @param User $user
     * @return bool
     */
    public function isOwnedBy($user)
    {
        if (($entry = $this->getEntry())) {
            return $entry->isOwnedBy($user);
        } else {
            return false;
        }
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
            case 'entry':
                return $this->getEntry();
            case 'element':
                return $this->getElement();
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
        return 'HistoryLogChanges';
    }
}
