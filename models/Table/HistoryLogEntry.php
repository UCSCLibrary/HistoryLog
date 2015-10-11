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
}
