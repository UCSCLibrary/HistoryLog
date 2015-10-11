<?php

echo head(array(
    'title' => __('Curation History Log'),
    'bodyclass' => 'history-log entries',
));

echo flash();

echo $this->showlog($record, 0);

echo foot();
