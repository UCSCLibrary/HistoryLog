<?php
class Table_HistoryLogEntry extends Omeka_Db_Table
{
    /**
     * Return selected entries, with start and end time limit.
     *
     * @param array $params A set of parameters by which to filter the objects
     * that get returned from the database.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     * @return array|null The set of objects that is returned
     */
    public function getEntries($params, $start = null, $end = null, $limit = null, $page = null)
    {
        $select = $this->getSelectForFindBy($params);
        if ($limit) {
            $this->applyPagination($select, $limit, $page);
        }

        $alias = $this->getTableAlias();
        if (!empty($start)) {
            // Accept an ISO 8601 date, set the tiemzone to the server's default
            // timezone, and format the date to be MySQL timestamp compatible.
            $date = new Zend_Date($start, Zend_Date::ISO_8601);
            $date->setTimezone(date_default_timezone_get());
            $date = $date->get('yyyy-MM-dd HH:mm:ss');
            $select->where("$alias.added >= ?", $date);
        }
        if (!empty($end)) {
            $date = new Zend_Date($start, Zend_Date::ISO_8601);
            $date->setTimezone(date_default_timezone_get());
            $date = $date->get('yyyy-MM-dd HH:mm:ss');
            $select->where("$alias.added <= ?", $date);
        }

        return $this->fetchObjects($select);
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
                case 'user_id':
                    $this->filterByUser($select, $value, 'user_id');
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
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select
     * @param Record.
     */
    public function filterByRecord($select, $record)
    {
        if (is_array($record)) {
            $recordType = Inflector::classify($record['record_type']);
            $recordId = (integer) $record['record_id'];
        }
        // Convert the record.
        else {
            $recordType = get_class($record);
            $recordId =$record->id;
        }
        $alias = $this->getTableAlias();
        $select->where($alias . '.record_type = ?', $recordType);
        $select->where($alias . '.record_id = ?', $recordId);
    }
}
