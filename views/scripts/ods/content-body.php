<?php
// Prepare tags to simplify export with small indentation to keep human output.
// Anyway, this will be zipped.
$indent = ' ';
// The eol may be removed. See the function used for the row too.
$eol = PHP_EOL;

// Prepare empty and normal cells. Empty cells are currently not checked to
// simplify process.
$emptyCell = str_repeat($indent, 5) . '<table:table-cell/>' . $eol;
$repeatedCell = str_repeat($indent, 5) . '<table:table-cell table:number-columns-repeated="%s"/>' . $eol;
$beforeCell = str_repeat($indent, 5) . '<table:table-cell office:value-type="string" calcext:value-type="string">' . $eol
    . str_repeat($indent, 6) . '<text:p>';
$afterCell = '</text:p>' . $eol
    . str_repeat($indent, 5) . '</table:table-cell>' . $eol;
$betweenCells = $afterCell . $beforeCell;

?>
 <office:body>
  <office:spreadsheet>
   <table:calculation-settings table:automatic-find-labels="false"/>
<?php

foreach ($tableNames as $iTable => $tableName):
    // Main tag of the table.
    echo str_repeat($indent, 3) . '<table:table table:name="' . $tableName . '" table:style-name="ta1">' . $eol;

    // Prepare the style of each column (the same in fact).
    echo str_repeat($indent, 4) . '<table:table-column table:style-name="co1" table:default-cell-style-name="Default"/>' . $eol;

    // Row for headers.
    if ($params['exportheaders']):
        echo str_repeat($indent, 4) . '<table:table-row table:style-name="ro1">' . $eol;
        echo $beforeCell . implode($betweenCells, $headers[$iTable]) . $afterCell;
        echo str_repeat($indent, 4) . '</table:table-row>' . $eol;
    endif;

    // Rows for each result.
    if (iterator_count(loop($loop))):
        foreach (loop($loop) as $i => $logEntry):
            echo str_repeat($indent, 4) . '<table:table-row table:style-name="ro1">' . $eol;

            // There are no special character to escape.
            $row = array();
            $row[] = $logEntry->displayAdded();
            $row[] = $logEntry->record_type;
            $row[] = $logEntry->record_id;
            $row[] = $logEntry->displayCurrentTitle();
            $row[] = $logEntry->displayPartOf();
            $row[] = $logEntry->displayUser();
            $row[] = $logEntry->displayOperation();
            $row[] = $logEntry->displayChanges();

            // TODO Manage repeated cells.

            // Replace all internal ends of line by a tag.
            $row = array_map(function ($value) { return str_replace(PHP_EOL, '</text:p><text:p>', $value); }, $row);
            echo $beforeCell . implode($betweenCells, $row) . $afterCell;

            echo str_repeat($indent, 4) . '</table:table-row>' . $eol;
        endforeach;

    // In case of an empty result.
    else:
        echo str_repeat($indent, 4) . '<table:table-row table:style-name="ro1">' . $eol;
        echo $beforeCell . __('No matching logs found.') . $afterCell;
        $k = count($headers[$iTable]) - 1;
        echo $k == 1 ? $emptyCell : sprintf($repeatedCell, $k);
        echo str_repeat($indent, 4) . '</table:table-row>' . $eol;
    endif;

    // End of the table.
    echo str_repeat($indent, 3) . '</table:table>' . $eol;
endforeach;

?>
   <table:named-expressions/>
  </office:spreadsheet>
 </office:body>
