<?php
$testing = false;
$title = __('History Log | Check logs');
$head = array(
    'title' => html_escape($title),
    'bodyclass' => 'history-log admin',
);
echo head($head);
$total = 0;
?>
<div id="primary">
    <?php echo flash(); ?>
    <div>
        <h2><?php echo __('Missing log entries'); ?></h2>
        <?php if ($testing): ?>
        <h3><?php  echo __('FOR TESTING PURPOSE ONLY'); ?></h3>
        <p><?php
        echo __('These buttons allow to check and rebuild logs.');
        echo ' ' . __('This may be useful when the plugin has been installed after some records have been created, or when a plugin uses non standard functions.');
        echo ' ' . __('The rebuild is not a requirement to use this plugin, but it can help to get better stats and to recover data in the future.');
        ?></p>
        <p><?php
        echo ' ' . __('The process is still in beta phase, so the entries may be incomplete or not exact.');
        ?></p>
        <p><strong><?php
        echo __('Use these buttons only on a server for tests and after a backup of the base (at least the tables of the plugin).');
        ?></strong></p>
        <p><?php
        echo __('Limits');
        ?><br /><?php
        echo ' ' . __('If an element has been updated, the previous data are lost if the creation of the record has not been recorded.');
        echo ' ' . __('So the plugin should be improved to log the replaced text and not the new one.');
        ?></p>
        <?php else: ?>
        <p><?php echo __('This page lists the missing log entries for each type of record.'); ?></p>
        <?php endif; ?>
        <?php foreach ($result as $recordType => $operations): ?>
            <ul>
                <li><?php echo __('%s (%d records)', $recordType, $totalRecords[$recordType]); ?>
                    <?php if ($testing && count($result[$recordType][HistoryLogEntry::OPERATION_CREATE])):
                        $rebuildUrl = url('history-log/admin/rebuild/type/' . strtolower($recordType) . '/operation/' . 'update'); ?>
                        <div>
                            <a href="<?php echo html_escape($rebuildUrl); ?>" class="history-log-process small button blue">
                                <?php echo html_escape(__('Rebuild a full entry for %s', $recordType)); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <ul>
                        <?php foreach ($operations as $operation => $values): ?>
                            <li><?php
                            $count = count($values);
                            $total += $count;
                            echo __('Operation "%s": %d', ucfirst($operation), $count);
                            ?>
                            <ul><li><?php echo implode(', ', $values); ?></li></ul>
                            <?php if ($testing && $count):
                                $rebuildUrl = url('history-log/admin/rebuild/type/' . strtolower($recordType) . '/operation/' . $operation); ?>
                                <div>
                                    <a href="<?php echo html_escape($rebuildUrl); ?>" class="history-log-process small button red">
                                        <?php echo html_escape(__('Rebuild log entries "%s" for %s', ucfirst($operation), $recordType)); ?>
                                    </a>
                                </div>
                            <?php endif;
                            ?></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
        <?php endforeach; ?>
        <p><?php
            if ($total):
                echo __('%d entries are missing in the history logs.', $total);
            else:
                echo __('Fine, all records have log entries!');
            endif;
        ?></p>
    </div>
</div>
<?php echo foot();
