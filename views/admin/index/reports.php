
<?php
/**
 * View script for curation history log reports.
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

if (isset($download) && $download) {
    echo $report;
}
else {
    $head = array(
        'bodyclass' => 'history-log primary',
        'title' => html_escape(__('History Log | View Log Report'))
    );
    echo head($head);
    echo flash();
    echo $form;
    if (isset($report)) {
        echo $report;
    }
    echo foot();
}
