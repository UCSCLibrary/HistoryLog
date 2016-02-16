<?php
// Delimiter is a tabulation, so no issues with specific characters, except
// multilines.
$delimiter = "\t";
// No enclosure is needed with a tabulation, because generally there is no
// tabulation in the database.
$enclosure = '';
$endOfLine = PHP_EOL;
$separator = $enclosure . $delimiter . $enclosure;

// Row for headers.
if ($params['exportheaders']):
    echo $enclosure . implode($separator, $headers) . $enclosure . $endOfLine;
endif;

// Rows for each result.
if (iterator_count(loop('HistoryLogEntry'))):
    foreach (loop('HistoryLogEntry') as $logEntry):
        $row = array();
        $row[] = $logEntry->displayAdded();
        $row[] = $logEntry->record_type;
        $row[] = $logEntry->record_id;
        $row[] = $logEntry->displayCurrentTitle();
        $row[] = $logEntry->displayPartOf();
        $row[] = $logEntry->displayUser();
        $row[] = $logEntry->displayOperation();
        $row[] = str_replace(PHP_EOL, ' ', $logEntry->displayChanges());
        echo $enclosure . implode($separator, $row) . $enclosure . $endOfLine;
    endforeach;
// In case of an empty result.
else:
    echo __('No matching logs found.');
endif;
