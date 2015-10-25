<?php
/**
 * The table for History Log Change.
 */
class Table_HistoryLogChange extends Omeka_Db_Table
{
    protected $_target = 'HistoryLogChange';

    /**
     * All changes should only be retrieved if they join properly on the entry
     * table.
     *
     * @return Omeka_Db_Select
     */
    public function getSelect()
    {
        $select = parent::getSelect();
        $db = $this->_db;

        $alias = $this->getTableAlias();
        $aliasEntry = $this->_db->getTable('HistoryLogEntry')->getTableAlias();

        $select->joinInner(
            array($aliasEntry => $db->HistoryLogEntry),
            "`$aliasEntry`.`id` = `$alias`.`entry_id`",
            array());

        $select->group($alias . '.id');
        return $select;
    }

    /**
     * Retrieve changes associated with an history log entry.
     *
     * @param Entry|integer $entry May be multiple.
     * @param array $elements Optional If given, this will only retrieve
     * elements with these specific ids.
     * @param string $sort The manner by which to order the changes.
     * @return array List of Change objects.
     */
    public function findByEntry($entry, $elements = array(), $sort = 'id')
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();

        $this->filterByEntry($select, $entry);
        $this->filterByChangedElement($select, $elements);
        $this->orderChangesBy($select, $sort);

        return $this->fetchObjects($select);
    }

    /**
     * Get all changes for elements of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @param boolean $onlyElements If true, return only true elements, not the
     * special changes with an element id of "0".
     * @return array|null Associative array of the last change of each element.
     */
    public function getChanges($record, $elements = array(), $onlyElements = false)
    {
        $alias = $this->getTableAlias();
        $tableEntry = $this->_db->getTable('HistoryLogEntry');
        $aliasEntry = $tableEntry->getTableAlias();

        $select = $this->getSelect();

        $tableEntry->filterByRecord($select, $record);
        $this->filterByChangedElement($select, $elements);
        if ($onlyElements) {
            $select->where("`$alias`.`element_id` != 0");
        }
        $this->orderChangesBy($select, 'element_id', 'ASC');

        return $this->fetchObjects($select);
    }

    /**
     * Get the first change of each element of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @param boolean $onlyElements If true, return only true elements, not the
     * special changes with an element id of "0".
     * @return array|null Associative array of the first change of each element.
     */
    public function getFirstChanges($record, $elements = array(), $onlyElements = false)
    {
        return $this->_getFirstOrLastChanges($record, $elements, $onlyElements, 'first');
    }

    /**
     * Get the last change of each element of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @param boolean $onlyElements If true, return only true elements, not the
     * special changes with an element id of "0".
     * @return array|null Associative array of the last change of each element.
     */
    public function getLastChanges($record, $elements = array(), $onlyElements = false)
    {
        return $this->_getFirstOrLastChanges($record, $elements, $onlyElements, 'last');
    }

    /**
     * Get the first or last change of each element of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @param boolean $onlyElements If true, return only true elements, not the
     * special changes with an element id of "0".
     * @param string $firstOrLast "first" or "last".
     * @return array|null Associative array of the first or last change of each
     * element.
     */
    protected function _getFirstOrLastChanges($record, $elements = array(), $onlyElements = false, $firstOrLast = 'last')
    {
        $firstOrLast = ($firstOrLast == 'first') ? 'MIN' : 'MAX';

        $alias = $this->getTableAlias();
        $tableEntry = $this->_db->getTable('HistoryLogEntry');
        $aliasEntry = $tableEntry->getTableAlias();

        $select = $this->getSelect();
        $tableEntry->filterByRecord($select, $record);
        $this->filterByChangedElement($select, $elements);
        if ($onlyElements) {
            $select->where("`$alias`.`element_id` != 0");
        }
        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                "$alias.element_id",
                'x_added' => "$firstOrLast(`$aliasEntry`.`added`)",
            ))
            ->reset(Zend_Db_Select::GROUP)
            ->group("$alias.element_id");
        $subSelect = $select;

        $select = $this->getSelect();
        $select->joinInner(
            array('hlcx' => $subSelect),
            "`hlcx`.`element_id` = `$alias`.`element_id` AND `hlcx`.`x_added` = `$aliasEntry`.`added`",
            array());
        $this->orderChangesBy($select, 'element');

        return $this->fetchObjects($select);
    }

    /**
     * Returns the list of changed element ids related to an entry.
     *
     * @param Entry|integer $entry
     * @return array List of element ids. 0 is not returned, because it's not an
     * element id.
     */
    public function getElementIds($entry, $sort = 'id')
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();
        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns($alias . '.element_id');

        $this->filterByEntry($select, $entry);
        $select->where("`$alias`.`element_id` != 0");
        $select->distinct();
        $this->orderChangesBy($select, 'element');

        return $this->fetchCol($select);
    }

    /**
     * @param Omeka_Db_Select
     * @param array
     * @return void
     */
    public function applySearchFilters($select, $params)
    {
        $alias = $this->getTableAlias();
        $boolean = new Omeka_Filter_Boolean;
        $genericParams = array();
        foreach ($params as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) == '')) {
                continue;
            }
            switch ($key) {
                case 'entry':
                    $this->filterByEntry($select, $value);
                    break;
                case 'record':
                    $this->filterByRecord($select, $value);
                    break;
                case 'element':
                    $this->filterByChangedElement($select, $value);
                    break;
                default:
                    $genericParams[$key] = $value;
            }
        }

        if (!empty($genericParams)) {
            parent::applySearchFilters($select, $genericParams);
        }
    }

    /**
     * Apply a entry filter to the select object.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param Entry|array|integer $entry One or multiple entry or ids.
     */
    public function filterByEntry(Omeka_Db_Select $select, $entry)
    {
        if (empty($entry)) {
            return;
        }
        if (!is_array($entry)) {
            $entry = array($entry);
        }

        // Reset elements to ids.
        $entries = array();
        foreach ($entry as $e) {
            $entries[] = (integer) (is_object($e) ? $e->id : $e);
        }

        $alias = $this->getTableAlias();
        // One change.
        if (count($entries) == 1) {
            $select->where("`$alias`.`entry_id` = ?", reset($entries));
        }
        // Multiple changes.
        else {
            $select->where("`$alias`.`entry_id` IN (?)", $entries);
        }
    }

    /**
     * Filter entry by record.
     *
     * @see HistoryLogEntry::filterByRecord()
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param Record|array $record
     */
    public function filterByRecord($select, $record)
    {
        $recordType = '';
        // Manage the case where the record is a new one.
        $recordId = 0;
        if (is_array($record)) {
            if (!empty($record['record_type']) && !empty($record['record_id'])) {
                $recordType = Inflector::classify($record['record_type']);
                $recordId = (integer) $record['record_id'];
            }
        }
        // Convert the record.
        elseif ($record) {
            $recordType = get_class($record);
            $recordId = $record->id ?: 0;
        }

        $tableEntry = $this->_db->getTable('HistoryLogEntry');
        $aliasEntry = $tableEntry->getTableAlias();

        $select->where("`$aliasEntry`.`record_type` = ?", $recordType);
        $select->where("`$aliasEntry`.`record_id` = ?", $recordId);
    }

    /**
     * Apply a element filter to the select object.
     *
     * @see HistoryLogEntry::filterByChangedElement()
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param Element|array|integer $elements One or multiple element or ids.
     * May be a "0" for non element change.
     */
    public function filterByChangedElement(Omeka_Db_Select $select, $elements)
    {
        // Reset elements to ids.
        if (!is_array($elements)) {
            $elements = array($elements);
        }
        foreach ($elements as &$element) {
            $element = (integer) (is_object($element) ? $element->id : $element);
        }
        if (empty($elements)) {
            return;
        }

        $alias = $this->getTableAlias();
        // One change.
        if (count($elements) == 1) {
            $select->where("`$alias`.`element_id` = ?", reset($elements));
        }
        // Multiple changes.
        else {
            $select->where("`$alias`.`element_id` IN (?)", $elements);
        }
    }

    /**
     * Orders select results for changes.
     *
     * @param Omeka_Db_Select The select object for finding changes
     * @param string $sort The manner in which to order the changes by. For
     * example: 'id' = change id ; 'element' = by element id.
     * @param string $dir Ascendant ("ASC") or descendant ("DESC").
     * @return void
     */
    public function orderChangesBy($select, $sort = 'id', $dir = 'ASC')
    {
        $alias = $this->getTableAlias();
        $dir = ($dir == 'DESC') ? 'DESC' : 'ASC';
        switch($sort) {
            case 'entry':
            case 'entry_id':
                $select->order("$alias.entry_id $dir");
                break;
            case 'element':
            case 'element_id':
                $select->order("$alias.element_id $dir");
                break;
            case 'id':
            default:
                $select->order("$alias.id $dir");
                break;
        }
    }
}
