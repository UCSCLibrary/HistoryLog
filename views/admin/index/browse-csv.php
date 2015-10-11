<?php
if (iterator_count(loop('HistoryLogEntry'))):
    // Delimiter is a tabulation, so no issues with speciic characters, except
    // multilines.
    $delimiter = "\t";
    // No enclosure is needed with a tabulation, because generally there is no
    // tabulation in the database.
    $enclosure = '';
    $endOfLine = PHP_EOL;
    $separator = $enclosure . $delimiter . $enclosure;

    if ($_REQUEST['csvheaders']):
        $row = array();
        $row[] = __('Type');
        $row[] = __('Title');
        $row[] = __('User');
        $row[] = __('Action');
        $row[] = __('Details');
        $row[] = __('Date');
        echo $enclosure . implode($separator, $row) . $enclosure . $endOfLine;
    endif;

    foreach (loop('HistoryLogEntry') as $logEntry):
        $row = array();
        $row[] = $logEntry->record_type;
        $row[] = $logEntry->title;
        $row[] = $logEntry->displayUser();
        $row[] = $logEntry->displayOperation();
        $row[] = $logEntry->displayChange();
        $row[] = $logEntry->displayAdded();
        echo $enclosure . implode($separator, $row) . $enclosure . $endOfLine;
    endforeach;
else: ?>
    <strong><?php __('No matching logs found.'); ?></strong>
<?php
endif;
