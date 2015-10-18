<?php
echo head(array(
    'title' => __('Curation History Log'),
    'bodyclass' => 'history-log entries',
));
echo flash();
$logs = $this->showlog($record, 0);
if (empty($logs)):
    echo __('No log for this record.');
else:
    echo $this->showlog($record, 0);
endif;
echo foot();
