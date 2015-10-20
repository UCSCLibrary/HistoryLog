<?php
class Table_HistoryLogEntry extends Omeka_Db_Table
{
    public function getEntries($params,$start,$end) {
        $dB = get_db();
        $sql = "SELECT id,title,itemID,collectionID,userID,type,value,time FROM `{$dB->HistoryLogEntry}` ";//.'WHERE itemID LIKE "'.$params['itemID'];

        unset($params['itemID']);
        $flag = false;
        foreach( $params as $column => $value) {
            $sql .= $flag ? ' AND ' : ' WHERE ';
            $sql .=  $column . ' = "' . $value . '"';
            $flag = true;
        }
        if(!is_null($start)) {
            $sql .= $flag ? ' AND ' : ' WHERE ';
            $sql .= 'time > "'.$start.'"';
            $flag = true;
        }
        if(!is_null($end)){
            $sql .= $flag ? ' AND ' : ' WHERE ';
            $sql .= 'time < "'.$end.'"';
        }
        $sql .= ' ORDER BY id DESC;';
        return($this->fetchObjects($sql));
        
    }

}