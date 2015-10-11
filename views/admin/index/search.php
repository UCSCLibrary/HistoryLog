<?php
/**
 * View script for curation history log reports.
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

$title = __('History Log | View Log Report');
$head = array(
    'title' => html_escape($title),
    'bodyclass' => 'history-log search',
);
echo head($head);
echo flash();
echo $form;
echo foot();
