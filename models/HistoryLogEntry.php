<?php
/**
 * A History Log entry
 * 
 * @package Historylog
 *
 */

class HistoryLogEntry extends Omeka_Record_AbstractRecord
{
    public $id; 
    public $title;
    public $itemID;
    public $collectionID;
    public $userID;
    public $time;
    public $type;
    public $value;

}

?>