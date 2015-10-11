<?php

echo head(array('title' => 'Item Curation History Log'));

echo flash();

echo $this->showlog($itemId, 0);

echo foot();
