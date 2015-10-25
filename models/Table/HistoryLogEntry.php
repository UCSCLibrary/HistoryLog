<?php
/**
 * The table for History Log Entries.
 */
class Table_HistoryLogEntry extends Omeka_Db_Table
{
    protected $_target = 'HistoryLogEntry';

    /**
     * Return selected entries, with start and end time limit.
     *
     * @param array $params A set of parameters by which to filter the objects
     * that get returned from the database.
     * @param string $since Set the start date.
     * @param string $until Set the end date (not included).
     * @param User|integer $user Limit to a user.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     * @return array|null The set of objects that is returned.
     */
    public function getEntries($params, $since = null, $until = null, $user = null, $limit = null, $page = null)
    {
        if (!empty($since)) {
            $params['since'] = $since;
        }
        if (!empty($until)) {
            $params['until'] = $until;
        }
        if (!empty($user)) {
            $params['user'] = $user;
        }
        return $this->findBy($params, $limit, $page);
    }

    /**
     * Return the list of all element ids that have been altered for a record.
     *
     * @param Object|array $record
     * @return array|null The list of element ids that have been altered for a
     * record.
     */
    public function getElementIdsForRecord($record)
    {
        $alias = $this->getTableAlias();
        $tableChange = $this->_db->getTable('HistoryLogChange');
        $aliasChange = $tableChange->getTableAlias();

        $select = $this->getSelect();
        $select->join(
            array($aliasChange => $this->_db->HistoryLogChange),
            "`$aliasChange`.`entry_id` = `$alias`.`id`",
            array());
        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns($aliasChange . '.element_id');

        $this->filterByRecord($select, $record);
        $select->where("`$aliasChange`.`.element_id` != 0");
        $select->order("$aliasChange.element_id ASC");
        $select->distinct();

        return $this->fetchCol($select);
    }

    /**
     * Return last entry for a record.
     *
     * @param Object|array $record
     * @return HistoryLogEntry|null The last entry if any.
     */
    public function getLastEntryForRecord($record)
    {
        $params = array();
        $params['record'] = $record;
        $params['sort_field'] = 'added';
        $params['sort_dir'] = 'd';

        $entries = $this->findBy($params, 1);
        if ($entries) {
            return reset($entries);
        }
    }

    /**
     * Return the last entry for elements of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @return array|null The last entry if any for each element of the record.
     * Null if empty record.
     */
    public function getLastEntryForElements($record, $elements = array())
    {
        if (!is_array($elements)) {
            $elements = array($elements);
        }

        $alias = $this->getTableAlias();
        $tableChange = $this->_db->getTable('HistoryLogChange');
        $aliasChange = $tableChange->getTableAlias();

        $select = $this->getSelect();

        $this->filterByRecord($select, $record);

        // Elements can't be get from the current list of elements, because some
        // may be deleted, so they are get from all entries of the record.
        if (empty($elements)) {
            $elements = $this->getElementIdsForRecord($record);
        }
        $this->filterByChangedElement($select, $elements);
        return $this->fetchObjects($select);
    }

    /**
     * Wrapper to get changes for elements of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @param boolean $onlyElements If true, return only true elements, not the
     * special changes with an element id of "0".
     * @return array|null Associative array of the last change of each element.
     */
    public function getChanges($record, $elements = array(), $onlyElements = false)
    {
        return $this->_db->getTable('HistoryLogChange')
            ->getChanges($record, $elements, $onlyElements);
    }

    /**
     * Wrapper to get the first change of each element of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @param boolean $onlyElements If true, return only true elements, not the
     * special changes with an element id of "0".
     * @return array|null Associative array of the first change of each element.
     */
    public function getFirstChanges($record, $elements = array(), $onlyElements = false)
    {
        return $this->_db->getTable('HistoryLogChange')
            ->getFirstChanges($record, $elements, $onlyElements);
    }

    /**
     * Wrapper to get the last change of each element of a record.
     *
     * @param Object|array $record
     * @param array|Object|integer $elements All altered elements if empty.
     * @param boolean $onlyElements If true, return only true elements, not the
     * special changes with an element id of "0".
     * @return array|null Associative array of the last change of each element.
     */
    public function getLastChanges($record, $elements = array(), $onlyElements = false)
    {
        return $this->_db->getTable('HistoryLogChange')
            ->getLastChanges($record, $elements, $onlyElements);
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
                case 'record':
                    $this->filterByRecord($select, $value);
                    break;
                case 'collection':
                    if ($params['record_type'] == 'Item') {
                        $select->where("`$alias`.`part_of` = ?", $value);
                    }
                    break;
                case 'item':
                    if ($params['record_type'] == 'File') {
                       $this->filterColumnByRange($select, $value, 'part_of');
                    }
                    break;
                case 'user':
                    $userId = (integer) (is_object($value) ? $value->id : $value);
                    $this->filterByUser($select, $userId, 'user_id');
                    break;
                case 'since':
                    if (strtolower($value) != 'yyyy-mm-dd') {
                        $this->filterBySince($select, $value, 'added');
                    }
                    break;
                case 'until':
                    if (strtolower($value) != 'yyyy-mm-dd') {
                        $this->filterByUntil($select, $value, 'added');
                    }
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
     * Filter entry by record.
     *
     * @see HistoryLogChange::filterByRecord()
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

        $alias = $this->getTableAlias();
        $select->where("`$alias`.`record_type` = ?", $recordType);
        $select->where("`$alias`.`record_id` = ?", $recordId);
    }

    /**
     * Filter returned records by a column.
     *
     * Can specify a range of valid record IDs or an individual ID
     *
     * @version 2.2.2
     * @param Omeka_Db_Select $select
     * @param string $range Example: 1-4, 75, 89
     * @param string $range Example: 1-4, 75, 89
     * @return void
     * @see self::filterByRange()
     */
    public function filterColumnByRange($select, $range, $column = 'id')
    {
        // Check the column.
        $columns = $this->getColumns();
        if (!array_search($column, $columns)) {
            return;
        }

        // Comma-separated expressions should be treated individually.
        $exprs = explode(',', $range);

        // Construct a SQL clause where every entry in this array is linked by 'OR'.
        $wheres = array();

        $alias = $this->getTableAlias();

        foreach ($exprs as $expr) {
            // If it has a '-' in it, it is a range of ids. Otherwise it is a
            // single id.
            if (strpos($expr, '-') !== false) {
                list($start, $finish) = explode('-', $expr);

                // Naughty naughty koolaid, no SQL injection for you
                $start  = (integer) trim($start);
                $finish = (integer) trim($finish);

                $wheres[] = "($alias.$column BETWEEN $start AND $finish)";

            }
            // Else, it's a single id.
            else {
                $id = (integer) trim($expr);
                $wheres[] = "($alias.$column = $id)";
            }
        }

        $where = join(' OR ', $wheres);

        $select->where('(' . $where . ')');
    }

    /**
     * Apply a date since filter to the select object.
     *
     * @internal Same as parent::filterBySince(), but with greater or equal.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param string $dateSince ISO 8601 formatted date
     * @param string $dateField "added" or "modified"
     */
    public function filterBySince(Omeka_Db_Select $select, $dateSince, $dateField)
    {
        // Reject invalid date fields.
        if (!in_array($dateField, array('added', 'modified'))) {
            return;
        }

        // Accept an ISO 8601 date, set the timezone to the server's default
        // timezone, and format the date to be MySQL timestamp compatible.
        $date = new Zend_Date($dateSince, Zend_Date::ISO_8601);
        $date->setTimezone(date_default_timezone_get());
        $date = $date->get('yyyy-MM-dd HH:mm:ss');

        // Select all dates that are greater than the passed date.
        $alias = $this->getTableAlias();
        $select->where("`$alias`.`$dateField` >= ?", $date);
    }

    /**
     * Apply a date until filter to the select object.
     *
     * @internal This is a forgotten method.
     * @internal Unlike ilterBySince(), the date is strictly lower in order to
     * simplify queries and to avoid to manage queries with start/end of day,
     * So use from Monday 00:00:00 to Monday+7 00:00:00 to get a week.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param string $dateSince ISO 8601 formatted date
     * @param string $dateField "added" or "modified"
     */
    public function filterByUntil(Omeka_Db_Select $select, $dateUntil, $dateField)
    {
        // Reject invalid date fields.
        if (!in_array($dateField, array('added', 'modified'))) {
            return;
        }

        // Accept an ISO 8601 date, set the timezone to the server's default
        // timezone, and format the date to be MySQL timestamp compatible.
        $date = new Zend_Date($dateUntil, Zend_Date::ISO_8601);
        $date->setTimezone(date_default_timezone_get());
        $date = $date->get('yyyy-MM-dd HH:mm:ss');

        // Select all dates that are lower than the passed date.
        $alias = $this->getTableAlias();
        $select->where("`$alias`.`$dateField` < ?", $date);
    }

    /**
     * Apply a element filter to the select object.
     *
     * @see HistoryLogChange::filterByChangedElement()
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param Element|array|integer $elements One or multiple element or ids.
     * May be a "0" for non element change.
     * @todo Cannot be a 0?
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
        $tableChange = $this->_db->getTable('HistoryLogChange');
        $aliasChange = $tableChange->getTableAlias();

        $select->join(
            array($aliasChange => $this->_db->HistoryLogChange),
            "`{$aliasChange}`.`entry_id` = `{$alias}`.`id`",
            array());

        // One change.
        if (count($elements) == 1) {
            $select->where("`$aliasChange`.`element_id` = ?", reset($elements));
        }
        // Multiple changes.
        else {
            $select->where("`$aliasChange`.`element_id` IN (?)", $elements);
        }
    }
}
