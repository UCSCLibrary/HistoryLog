<?php

echo head(array('title' => __('Curation History Log')));

echo flash();

echo $this->showlog($record, 0);

echo foot();
