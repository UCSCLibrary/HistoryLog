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
     * @param Entry|integer|array $entry May be multiple.
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
        $tableEntry->filterByRecord($select, $record);
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
     * Count all records with the specified text for an element during a period.
     *
     * This is useful for elements with a limited vocabulary and without
     * repeatable values. For example, it allows to respond to a query such "How
     * many records have the element "Metadata Status" set to "Published" by
     * user "John Smith" during "September 2015"?" (example used by the plugin
     * "Curator Monitor"). The interpretation of this count is harder when there
     * are multiple values for the same element.
     *
     * @todo Manage repeatable values.
     * @todo Manage deletion of records.
     * @todo Import/Export are not checked (element_id = "0").
     *
     * @param array $params
     * @param boolean $lastChange If true, only the value at the end of the
     * period will be compute. This allows to avoid cases where the text has
     * been updated multiple times, for example "Complete" then "Ready to
     * Publish" and finally "Incomplete" (from the plugin "Curator Monitor").
     * @param boolean $withAllDates If true, the dates without value will be
     * added. For example, if there is no item added in August, the August value
     * will be added with a count of "0".
     * @param boolean $withDeletedElements When all changes are returned,
     * merge deletion of elements too.
     * @return array The number of records.
     */
    public function countRecords($params, $lastChange = true, $withAllDates = true, $withDeletedElements = true)
    {
        $normalizedPeriod = $this->_normalizePeriod($params);
        if (empty($normalizedPeriod)) {
            return array();
        }

        list($since, $until, $by, $columnsDate) = $normalizedPeriod;
        $params['since'] = $since;
        $params['until'] = $until;
        $params['by'] = $by;

        // Strict / non strict requests use different queries.
        return $lastChange
            ? $this->_countRecordsLastChange($params, $columnsDate, $withAllDates)
            : $this->_countRecordsAllChanges($params, $columnsDate, $withAllDates, $withDeletedElements);
    }

    /**
     * Helper to count all records with only the last change of each period.
     *
     * @param array $params
     * @param array $columnsDate
     * @param boolean $withAllDates
     * @return array The result.
     */
    protected function _countRecordsLastChange($params, $columnsDate, $withAllDates)
    {
        $db = $this->_db;
        $alias = $this->getTableAlias();
        $tableEntry = $db->getTable('HistoryLogEntry');
        $aliasEntry = $tableEntry->getTableAlias();

        // Prepare the main query used to count returned values.
        $countColumns = array();
        $countColumns['element_id'] = 'sub_1.element_id';
        $countColumns = array_merge($countColumns, array_keys($columnsDate));
        $countColumns['text'] = 'sub_1.text';
        // Count is added below to simplify the building of the query.
        // $countColumns['Count'] = 'COUNT(*)';
        $countGroup = array();
        $countGroup[] = 'element_id';
        $countGroup = array_merge($countGroup, array_keys($columnsDate));
        $countGroup[] = 'text';

        // Prepare the query used to get all values.
        $selectAllChanges = $this->getSelectForCount();
        $allChangesColumns = array();
        $allChangesColumns['element_id'] = "$alias.element_id";
        $allChangesColumns['record_type'] = "$aliasEntry.record_type";
        $allChangesColumns['record_id'] = "$aliasEntry.record_id";
        $allChangesColumns['text'] = new Zend_Db_Expr("IF ($alias.type = '" . HistoryLogChange::TYPE_DELETE . "', NULL, $alias.text)");
        $allChangesColumns['added'] = "$aliasEntry.added";
        $selectAllChanges
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns($allChangesColumns);
        $this->applySearchFilters($selectAllChanges, $params);

        // Prepare the query used to get the last value of each period.
        $selectLastChange = $this->getSelectForCount();
        $lastChangesColumns = array();
        $lastChangesColumns['element_id'] = "$alias.element_id";
        $lastChangesColumns['record_type'] = "$aliasEntry.record_type";
        $lastChangesColumns['record_id'] = "$aliasEntry.record_id";
        $lastChangesColumns['added'] = "MAX($aliasEntry.added)";
        $lastChangesColumns += $columnsDate;
        $lastChangesGroup = array();
        $lastChangesGroup[] = "$alias.element_id";
        $lastChangesGroup[] = "$aliasEntry.record_type";
        $lastChangesGroup[] = "$aliasEntry.record_id";
        $lastChangesGroup = array_merge($lastChangesGroup, array_keys($columnsDate));
        $selectLastChange
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns($lastChangesColumns)
            ->reset(Zend_Db_Select::GROUP)
            ->group($lastChangesGroup);
        $this->applySearchFilters($selectLastChange, $params);

        // Two temporary tables are required when the history changes grows,
        // because there are two "JOIN" in the query.
        $presql = array();
        $presql[] = 'DROP TABLE IF EXISTS history_log_1;';
        $presql[] = 'DROP TABLE IF EXISTS history_log_2;';
        $presql[] = 'CREATE TEMPORARY TABLE history_log_1 AS '
            . $selectAllChanges->__toString() . ';';
        $presql[] = 'CREATE TEMPORARY TABLE history_log_2 AS '
            . $selectLastChange->__toString() . ';';
        // The indexes imrpove speed in big bases.
        $presql[] = 'CREATE INDEX element_id ON history_log_1 (`element_id`);';
        $presql[] = 'CREATE INDEX record_type ON history_log_1 (`record_type`);';
        $presql[] = 'CREATE INDEX record_id ON history_log_1 (`record_id`);';
        $presql[] = 'CREATE INDEX i_text ON history_log_1 (`text`(31));';
        $presql[] = 'CREATE INDEX added ON history_log_1 (`added`);';
        $presql[] = 'CREATE INDEX element_id ON history_log_2 (`element_id`);';
        $presql[] = 'CREATE INDEX record_type ON history_log_2 (`record_type`);';
        $presql[] = 'CREATE INDEX record_id ON history_log_2 (`record_id`);';
        $presql[] = 'CREATE INDEX added ON history_log_2 (`added`);';

        // Execute all temporary sql.
        foreach ($presql as $query) {
            $stmt = $db->query($query);
        }

        // Build the optimized full query.
        $sql = 'SELECT '
            . implode(', ', $countColumns)
            . ', COUNT(*) AS "Count"'
            // Add the sql to get all values.
            . ' FROM history_log_1 as sub_1'
            // Add the sql to get last values.
            . ' JOIN history_log_2 as sub_2
                ON sub_2.`element_id` = sub_1.`element_id`
                    AND sub_2.`record_type` = sub_1.`record_type`
                    AND sub_2.`record_id` = sub_1.`record_id`
                    AND sub_2.`added` = sub_1.`added`'
            // Finalize the main query.
            . ' WHERE `text` IS NOT NULL'
            . ' GROUP BY '
            . implode(', ', $countGroup);

        if ($columnsDate && $withAllDates) {
            return $this->_countRecordsWithMissingDates($sql, $params, $columnsDate);
        }

        $sql .= ' ORDER BY `element_id` ASC';
        if ($columnsDate) {
            $sql .= ', `' . implode('` ASC, `' , array_keys($columnsDate)) . '` ASC';
        }

        $result = $db->fetchAll($sql);
        return $result;
    }

    /**
     * Helper to count all records with all changes of each period.
     *
     * @param array $params
     * @param array $columnsDate
     * @param boolean $withAllDates
     * @param boolean $withDeletedElements
     * @return array The result.
     */
    protected function _countRecordsAllChanges($params, $columnsDate, $withAllDates, $withDeletedElements)
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelectForCount();

        $columns = array();
        $columns['element_id'] = "$alias.element_id";
        $columns += $columnsDate;
        $columns['text'] = $withDeletedElements
            ? "$alias.text"
            : new Zend_Db_Expr("IF ($alias.type = '" . HistoryLogChange::TYPE_DELETE . "', NULL, $alias.text)");
        $columns['Count'] = "COUNT(`$alias`.`id`)";

        $group = array();
        $group[] = "$alias.element_id";
        $group = array_merge($group, array_keys($columnsDate));
        $group[] = 'text';
        $order = array();
        $order[] = "$alias.element_id";
        $order = array_merge($order, array_keys($columnsDate));

        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns($columns)
            ->reset(Zend_Db_Select::GROUP)
            ->group($group);
        if (!$withDeletedElements) {
            $select
                ->where('text IS NOT NULL');
        }

        $this->applySearchFilters($select, $params);

        if ($columnsDate && $withAllDates) {
            return $this->_countRecordsWithMissingDates($select->_toString(), $params, $columnsDate);
        }

        $select
            ->reset(Zend_Db_Select::ORDER)
            ->order($order);
        $result = $this->fetchAll($select);
        return $result;
    }

    /**
     * Helper to count all records: add all missing dates.
     *
     * @param string $select The select to sql.
     * @param array $params
     * @param array $columnsDate
     * @return array The result.
     */
    protected function _countRecordsWithMissingDates($selectSql, $params, $columnsDate)
    {
        // How to do a left join with a temporary table with Zend?
        $sql = $this->_getAllPeriods($selectSql, $params, $columnsDate);
        if (empty($sql)) {
            return array();
        }

        $sql .= ' ORDER BY `element_id` ASC';
        if ($columnsDate) {
            $sql .= ', `' . implode('` ASC, `' , array_keys($columnsDate)) . '` ASC';
        }

        $result = $this->_db->fetchAll($sql);
        return $result;
    }

    /**
     * @param Omeka_Db_Select
     * @param array
     * @return void
     */
    public function applySearchFilters($select, $params)
    {
        $alias = $this->getTableAlias();
        $tableEntry = $this->_db->getTable('HistoryLogEntry');
        $boolean = new Omeka_Filter_Boolean;
        $genericParams = array();
        foreach ($params as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) == '')) {
                continue;
            }
            // Filters for Entry can be used directly, because the getSelect()
            // initializes it directly.
            switch ($key) {
                case 'entry':
                    $this->filterByEntry($select, $value);
                    break;
                case 'record':
                    $tableEntry->filterByRecord($select, $value);
                    break;
                case 'collection':
                    if ($params['record_type'] == 'Item') {
                        $aliasEntry = $tableEntry->getTableAlias();
                        $select->where("`$aliasEntry`.`part_of` = ?", $value);
                    }
                    break;
                case 'item':
                    if ($params['record_type'] == 'File') {
                       $tableEntry->filterColumnByRange($select, $value, 'part_of');
                    }
                    break;
                case 'user':
                    $userId = (integer) (is_object($value) ? $value->id : $value);
                    $tableEntry->filterByUser($select, $userId, 'user_id');
                    break;
                case 'since':
                    if (strtolower($value) != 'yyyy-mm-dd') {
                        $tableEntry->filterBySince($select, $value, 'added');
                    }
                    break;
                case 'until':
                    if (strtolower($value) != 'yyyy-mm-dd') {
                        $tableEntry->filterByUntil($select, $value, 'added');
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
     * Apply an element filter to the select object.
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

    /**
     * Normalize a date for sql.
     *
     * @param string $date A date string, else now.
     * @param string $hms The "first" or the "last" time of the date.
     */
    protected function _normalizeDate($date = null, $hms = 'first')
    {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        // Accept an ISO 8601 date, set the timezone to the server's default
        // timezone, and format the date to be MySQL timestamp compatible.
        $date = new Zend_Date($date, Zend_Date::ISO_8601);
        $date->setTimezone(date_default_timezone_get());
        return $date->get('yyyy-MM-dd') . ($hms == 'first' ? ' 00:00:00' : ' 23:59:59.999999');
    }

    /**
     * Clean the params for period.
     *
     * For example, if the "by" is "month", the since will be the first day of
     * the specified month and the "until" the last one. This simplifies queries
     * with now() and some other ones.
     *
     * @param array $params
     * @return array|boolean Cleaned list of date "since", "until", "by" and
     * "columns". False if a param is not good.
     */
    protected function _normalizePeriod($params)
    {
        If (empty($params['since'])) {
            if (!empty($params['added'])) {
                $since = $params['added'];
            }
            // Get the earliest date if empty.
            else {
                $select = $this->_db->getTable('HistoryLogEntry')->getSelect();
                $select
                    ->reset(Zend_Db_Select::COLUMNS)
                    ->columns('added')
                    ->order('added ASC');
                $since = $this->_db->fetchOne($select);
                $since = $this->_normalizeDate($since);
            }
        }
        // Check and normalize the date "since".
        else {
            $since = $this->_normalizeDate($params['since']);
        }

        If (empty($params['until'])) {
            if (!empty($params['added'])) {
                $until = $params['added'];
            }
            // Get the latest date if empty.
            else {
                // Use 'NOW()'? But a true date is simpler to check here.
                $until = $this->_normalizeDate(null, 'last');
            }
        }
        // Check and normalize the date "until".
        else {
            $until = $this->_normalizeDate($params['until'], 'last');
        }

        if (empty($since) || empty($until) || $since > $until) {
            return false;
        }

        $by = empty($params['by']) ? null : strtoupper($params['by']);

        // This is possible only to get a cumulative of all values.
        if (empty($by)) {
            return array($since, $until, null, array());
        }

        $columns = array();
        $tableEntry = $this->_db->getTable('HistoryLogEntry');
        $aliasEntry = $tableEntry->getTableAlias();

        // Get the first day of the period and the next period.
        switch ($by) {
            case 'DATE':
                // No change;
                $since = date('Y-m-d', strtotime($since));
                $until = date('Y-m-d', strtotime($until));
                $columns['Date'] = "Date(`$aliasEntry`.`added`)";
                break;
            case 'DAY':
                // Synonymous.
            case 'DAYOFMONTH':
                // No change;
                $since = date('Y-m-d', strtotime($since));
                $until = date('Y-m-d', strtotime($until));
                $columns['Year'] = "YEAR(`$aliasEntry`.`added`)";
                $columns['Month'] = "MONTH(`$aliasEntry`.`added`)";
                $columns['Day'] = "DAY(`$aliasEntry`.`added`)";
                break;
            case 'WEEK':
                $since = date('Y-m-d', strtotime('last monday', strtotime($since .' +1 day')));
                $until = date('Y-m-d', strtotime('next sunday', strtotime($until .' -1 day')));
                $columns['Year'] = "YEAR(`$aliasEntry`.`added`)";
                $columns['Week'] = "WEEK(`$aliasEntry`.`added`)";
                break;
            case 'MONTH':
                $since = date('Y-m', strtotime($since)) . '-01';
                $until = date('Y-m-d', strtotime('last day of this month', strtotime($until)));
                $columns['Year'] = "YEAR(`$aliasEntry`.`added`)";
                $columns['Month'] = "MONTH(`$aliasEntry`.`added`)";
                break;
            case 'QUARTER':
                $month = date('m', strtotime($since));
                if ($month < 4) {
                    $since = date('Y', strtotime($since)) . '-01-01';
                } elseif ($month < 7) {
                    $since = date('Y', strtotime($since)) . '-04-01';
                } elseif ($month < 10) {
                    $since = date('Y', strtotime($since)) . '-07-01';
                } else {
                    $since = date('Y', strtotime($since)) . '-10-01';
                }
                $month = date('m', strtotime($until));
                if ($month < 4) {
                    $until = date('Y', strtotime($until)) . '-03-31';
                } elseif ($month < 7) {
                    $until = date('Y', strtotime($until)) . '-06-30';
                } elseif ($month < 10) {
                    $until = date('Y', strtotime($until)) . '-09-30';
                } else {
                    $until = date('Y', strtotime($until)) . '-12-31';
                }
                $columns['Year'] = "YEAR(`$aliasEntry`.`added`)";
                $columns['Quarter'] = "QUARTER(`$aliasEntry`.`added`)";
                break;
            case 'YEAR':
                $since = date('Y', strtotime($since)) . '-01-01';
                $until = date('Y', strtotime($until)) . '-12-31';
                $columns['Year'] = "YEAR(`$aliasEntry`.`added`)";
                break;

            case 'DAYNAME':
            case 'DAYOFWEEK':
            case 'DAYOFYEAR':
            case 'MONTHNAME':
            case 'WEEKDAY':
            case 'YEARWEEK':
            case 'WEEKOFYEAR':
            case 'HOUR':
            case 'MINUTE':
            case 'SECOND':
            default:
                return false;
        }

        return array($since, $until, $by, $columns);
    }

    /**
     * Prepare a sql to create a temporary table with all requested periods.
     *
     * @internal The params "since", "until" and "by" should be cleaned before.
     * @internal Period below day (hour, minute, second) have not been checked.
     * @todo Check sql for time (not useful).
     *
     * @param string $select The select to string.
     * @param array $params
     * @param array $columnsDate
     * @return string|boolean Sql query, else false.
     */
    protected function _getAllPeriods($selectSql, $params, $columnsDate)
    {
        $num = $this->_db->prefix . 'numerals';
        $dateSince = new DateTime($params['since']);
        $dateUntil = new DateTime($params['until']);
        $interval = $dateSince->diff($dateUntil);
        $period = array();

        switch (strtoupper($params['by'])) {
            case 'DATE':
                $interval = $interval->format('%R%a');
                $intervalName = 'DAY';
                // For days, "AddDate" is used, simpler than "Date_Add".
                $sqlPeriodStart = 'SELECT ADDDATE(' . $this->_db->quote($params['since']) . ', `numlist`.`i`) AS `Date`
                    FROM (';
                $sqlPeriodEnd = ') AS `numlist`
                    WHERE ADDDATE(' . $this->_db->quote($params['since']) . ', `numlist`.`i`) <= ' . $this->_db->quote($params['until']) . '';
                $sqlMainJoinEnd = ') AS `Periods` ON `Periods`.`Date` = `stats`.`Date`';
                break;
            case 'DAY':
            case 'DAYNAME':
            case 'DAYOFMONTH':
            case 'DAYOFWEEK':
            case 'DAYOFYEAR':
            case 'WEEKDAY':
                $interval = $interval->format('%R%a');
                $intervalName = 'DAY';
                $period = array('YEAR' => 'Year', 'MONTH' => 'Month', 'DAY' => 'Day');
                break;
            case 'WEEK':
            case 'YEARWEEK':
            case 'WEEKOFYEAR':
                $interval = $interval->format('%R%a') / 7;
                $intervalName = 'WEEK';
                $period = array('YEAR' => 'Year', 'WEEK' => 'Week');
                break;
            case 'MONTH':
            case 'MONTHNAME':
                $interval = $interval->format('%R%a') / 28;
                $intervalName = 'MONTH';
                $period = array('YEAR' => 'Year', 'MONTH' => 'Month');
                break;
            case 'QUARTER':
                $interval = $interval->format('%R%a') / 90;
                $intervalName = 'QUARTER';
                $period = array('YEAR' => 'Year', 'QUARTER' => 'Quarter');
                break;
            case 'YEAR':
                $interval = $interval->format('%R%a') / 365;
                $intervalName = 'YEAR';
                $period = array('YEAR' => 'Year');
                break;
            // TODO Time is not checked.
            case 'HOUR':
            case 'MINUTE':
            case 'SECOND':
            default:
                return false;
        }

        // Add columns to select and join, except for "Date".
        if (!empty($period)) {
            $sqlPeriodStart = 'SELECT ';
            $sqlMainJoinEnd = ') AS `Periods` ON ';
            $i = count($period);
            foreach ($period as $function => $label) {
                $sqlPeriodStart .= sprintf('%s(DATE_ADD(%s, INTERVAL `numlist`.`i` %s)) AS `%s`',
                    $function, $this->_db->quote($params['since']), $intervalName, $label);
                $sqlMainJoinEnd .= sprintf('`Periods`.`%s` = `stats`.`%s`', $label, $label);
                if (--$i > 0) {
                    $sqlPeriodStart .= ', ';
                    $sqlMainJoinEnd .= ' AND ';
                }
            }
            $sqlPeriodStart .= '
                FROM (';
            $sqlPeriodEnd = ') AS `numlist`
                WHERE DATE_ADD(' . $this->_db->quote($params['since']) . ', INTERVAL `numlist`.`i` ' . $intervalName . ') <= ' . $this->_db->quote($params['until']) . '';
        }

        // Select the shortest numerals list according to the number of periods.
        switch (strlen((integer) $interval + 2)) {
            case 1:
                $sqlNumList = "SELECT `n1`.`i` AS `i`
                    FROM `$num` `n1`";
                break;
             case 2:
                $sqlNumList = "SELECT `n1`.`i` + `n10`.`i` * 10 AS `i`
                    FROM `$num` `n1`
                    CROSS JOIN `$num` AS `n10`";
                break;
            case 3:
                $sqlNumList = "SELECT `n1`.`i` + `n10`.`i` * 10 + `n100`.`i` * 100 AS `i`
                    FROM `$num` `n1`
                    CROSS JOIN `$num` AS `n10`
                    CROSS JOIN `$num` AS `n100`";
                break;
            case 4:
                $sqlNumList = "SELECT `n1`.`i` + `n10`.`i` * 10 + `n100`.`i` * 100 + `n1000`.`i` * 1000 AS `i`
                    FROM `$num` `n1`
                    CROSS JOIN `$num` AS `n10`
                    CROSS JOIN `$num` AS `n100`
                    CROSS JOIN `$num` AS `n1000`";
                break;
            case 5:
                $sqlNumList = "SELECT `n1`.`i` + `n10`.`i` * 10 + `n100`.`i` * 100 + `n1000`.`i` * 1000 + `n10000`.`i` * 10000  AS `i`
                    FROM `$num` `n1`
                    CROSS JOIN `$num` AS `n10`
                    CROSS JOIN `$num` AS `n100`
                    CROSS JOIN `$num` AS `n1000`
                    CROSS JOIN `$num` AS `n10000`";
                break;
            case 6:
                $sqlNumList = "SELECT `n1`.`i` + `n10`.`i` * 10 + `n100`.`i` * 100 + `n1000`.`i` * 1000 + `n10000`.`i` * 10000 + `n100000`.`i` * 100000 AS `i`
                    FROM `$num` `n1`
                    CROSS JOIN `$num` AS `n10`
                    CROSS JOIN `$num` AS `n100`
                    CROSS JOIN `$num` AS `n1000`
                    CROSS JOIN `$num` AS `n10000`
                    CROSS JOIN `$num` AS `n100000`";
            default:
                return false;
        }

        $sqlMainSelectColumnsDate = ' `Periods`.`' . implode('`, `Periods`.`', array_keys($columnsDate)) . '`,';

        $sqlMainSelectStart = 'SELECT coalesce(`stats`.`element_id`, "") AS `element_id`,';
        $sqlMainSelectEnd = ' coalesce(`stats`.`text`, "") AS `text`, coalesce(`stats`.`Count`, 0) AS `Count`
        ';
        $sqlMainFromStart = 'FROM (
        ';
        $sqlMainFromEnd = ') AS `stats`
        ';
        $sqlMainJoinStart = 'RIGHT OUTER JOIN (
        ';

        $sql = $sqlMainSelectStart
            . $sqlMainSelectColumnsDate
            . $sqlMainSelectEnd
            . $sqlMainFromStart
            . $selectSql
            . $sqlMainFromEnd
            . $sqlMainJoinStart
            . $sqlPeriodStart
            . $sqlNumList
            . $sqlPeriodEnd
            . $sqlMainJoinEnd;

        return $sql;
    }
}
