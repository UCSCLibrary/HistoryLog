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
     * @var string The dublin core title at the time of log entry.
     */
    public $title;

    /**
     * @var int The id of the Item record associated with this log entry.
     */
    public $itemID;

    /**
     * @var int The id of the Collection record in which the associated Item
     * record was stored at the time of log entry.
     */
    public $collectionID;

    /**
     * @var int The id of the User record who performed the logged action.
     */
    public $userID;

    /**
     * @var string The UTF formatted date and time when the log took place.
     */
    public $time;

    /**
     * @var string The type of action being logged: 'delete', 'modify',
     * 'create', 'export'.
     */
    public $type;

    /**
     * @var string More information about the action being performed.
     * For modifications, this stores the elements modified.
     * For exports, it stores the export method.
     */
    public $value;

    /**
     * Retrieve username of an omeka user by user ID.
     *
     * @return string $username The username of the Omeka user
     */
    public function displayUsername()
    {
        $user = get_record_by_id('User', $this->userID);
        if (empty($user)) {
            return 'No user / deleted user';
        }
        return $user->name . ' (' . $user->username . ')';
    }

    /**
     * Retrieve "value" parameter for the displayable form.
     *
     * @return string $value The value is human readable form.
     */
    public function displayValue()
    {
        switch ($this->type) {
            case 'created':
                if (!empty($this->value)) {
                    return 'Imported from ' . $this->value;
                }
                else {
                    return 'Created manually by user';
                }
                break;

            case 'updated':
                $update = unserialize($this->value);
                if (empty($update)) {
                    $rv = 'File upload/edit';
                    return $rv;
                }
                $rv = 'Elements altered: ';

                $flag = false;
                foreach ($update as $elementID) {
                    if ($flag) {
                        $rv .= ', ';
                    }
                    $flag = true;
                    $element = get_record_by_id('Element', $elementID);
                    $rv .= $element->name;
                }
                return $rv;

            case 'exported':
                if (!empty($this->value)) {
                    return 'Exported to: ' . $this->value;
                }
                else {
                    return '';
                }
                break;

            case 'deleted':
                return '';
                break;
        }
    }

    /**
     * Retrieves the current title.
     *
     * @return string $title The Dublin Core title of the item.
     */
    public function displayCurrentTitle()
    {
        if (empty($this->itemID)) {
            throw new Exception('Could not retrieve Item ID');
        }

        $item = get_record_by_id('Item', $this->itemID);
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
