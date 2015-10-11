<?php
/**
 * A History Log entry
 *
 * @package Historylog
 *
 */
class HistoryLogEntry extends Omeka_Record_AbstractRecord
{
    /**
     * @var int The record ID.
     */
    public $id;

    /**
     * @var int The id of the Item record associated with this log entry.
     */
    public $item_id;

    /**
     * @var int The id of the Collection record in which the associated Item
     * record was stored at the time of log entry.
     */
    public $collection_id;

    /**
     * @var int The id of the User record who performed the logged action.
     */
    public $user_id;

    /**
     * @var string The limited list of type of action being logged: "created",
     * "imported", "updated", "exported", "deleted".
     */
    public $action;

    /**
     * @var string More information about the action being performed.
     * For modifications, this stores the elements modified.
     * For exports, it stores the export method.
     * @internal Arrays of element ids are saved as set of values separated with
     * a space: "[ 50 49 28 ]". Spaces make indexing and search of a particular
     * id by sql easier: "WHERE change LIKE concat("% ", searched_id, " %")"
     * returns the good result. It avoids to manage first and last values or an
     * extra table for these data that never change.
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
     * Sets change.
     *
     * @param string|array $change
     */
    public function setChange($change)
    {
        $this->_change = $change;
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
            return 'No user / deleted user';
        }
        return $user->name . ' (' . $user->username . ')';
    }

    /**
     * Retrieve displayable name of an action by its slug
     *
     * @return string User displayable action name
     */
    public function displayAction()
    {
        switch ($this->action) {
            case 'created':
                return 'Item Created';
            case 'imported':
                return 'Item Imported';
            case 'updated':
                return 'Item Modified';
            case 'exported':
                return 'Item Exported';
            case 'deleted':
                return 'Item Deleted';
            default:
                return $this->action;
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
        switch ($this->action) {
            case 'created':
                return empty($change)
                    ? 'Created manually by user'
                    : 'Imported from ' . $change;

            case 'imported':
                return empty($change)
                    ? ''
                    : 'Imported from: ' . $change;

            case 'updated':
                if (empty($change)) {
                    $rv = 'File upload/edit';
                    return $rv;
                }
                // Check if this is a list of elements (ids) or a string.
                if (!is_array($change)) {
                    return $change;
                }

                // Else multiple values, so presume all  numeric.
                $rv = 'Metadata altered: ';
                $flag = false;
                foreach ($change as $elementID) {
                    if ($flag) {
                        $rv .= ', ';
                    }
                    // First value.
                    else {
                        $flag = true;
                    }
                    $element = get_record_by_id('Element', $elementID);
                    if (empty($element)) {
                        $rv .= 'Unrecognized element #' . $elementID;
                    }
                    else {
                        $rv .= $element->name;
                    }
                }
                return $rv;

            case 'exported':
                return empty($change)
                    ? ''
                    : 'Exported to: ' . $change;

            case 'deleted':
                return '';
        }
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
     * Retrieves the current title.
     *
     * @return string The current Dublin Core title of the item if any.
     */
    public function displayCurrentTitle()
    {
        if (empty($this->item_id)) {
            throw new Exception('Could not retrieve Item ID');
        }

        $item = get_record_by_id('Item', $this->item_id);
        if (empty($item)) {
            return 'deleted item';
        }

        $titles = $item->getElementTexts('Dublin Core', 'Title');
        $title = isset($titles[0])
            ? $titles[0]
            : 'untitled / title unknown';

        return $title;
    }
}
