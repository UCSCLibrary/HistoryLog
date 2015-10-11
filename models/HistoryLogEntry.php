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

}
