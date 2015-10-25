<?php
$pageTitle = __('Curation History Log (%d total)', $total_results);
echo head(array(
    'title' => $pageTitle,
    'bodyclass' => 'history-log browse',
));
?>
<style>
div.record-title {
    max-width: 15em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
<div id="primary">
    <?php echo flash(); ?>
    <div>
<?php if (total_records('HistoryLogEntry') > 0): ?>
        <div class="table-actions">
            <a href="<?php echo html_escape(url('history-log/index/search')); ?>" class="add button small green"><?php echo __('Advanced Reports'); ?></a>
        </div>
        <?php echo common('quick-filters'); ?>
        <?php if (iterator_count(loop('HistoryLogEntry'))): ?>
        <div class="pagination"><?php echo $paginationLinks = pagination_links(); ?></div>
        <table id="history-log-entries" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <?php
                    $browseHeadings[__('Date')] = 'date';
                    $browseHeadings[__('Type')] = 'record_type';
                    $browseHeadings[__('Id')] = 'record_id';
                    $browseHeadings[__('Part of ')] = 'part_of';
                    $browseHeadings[__('User')] = 'user';
                    $browseHeadings[__('Action')] = 'operation';
                    $browseHeadings[__('Changes')] = null;
                    echo browse_sort_links($browseHeadings, array('link_tag' => 'th scope="col"', 'list_tag' => ''));
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php $key = 0; ?>
                <?php
                foreach (loop('HistoryLogEntry') as $logEntry):
                ?>
                <tr class="history-log-entry <?php if (++$key%2 == 1) echo 'odd'; else echo 'even'; ?>">
                    <td><?php echo $logEntry->added; ?></td>
                    <td colspan="2">
                        <a href="<?php
                        echo url(array(
                                'type' => Inflector::tableize($logEntry->record_type),
                                'id' => $logEntry->record_id,
                            ), 'history_log_record_log'); ?>"><?php
                            echo $logEntry->record_type;
                            echo ' ';
                            echo $logEntry->record_id;
                        ?></a>
                        <div class="record-title"><?php echo $logEntry->displayCurrentTitle(); ?></div>
                    </td>
                    <td><?php
                    if (!empty($logEntry->part_of)) {
                        switch ($logEntry->record_type) {
                            case 'Item':
                                echo '<a href="'
                                    .  url(array(
                                            'type' => 'collections',
                                            'id' => $logEntry->part_of,
                                        ), 'history_log_record_log')
                                    . '">'
                                    . __('Collection %d', $logEntry->part_of)
                                    . '</a>';
                                break;
                            case 'File':
                                echo '<a href="'
                                    .  url(array(
                                            'type' => 'items',
                                            'id' => $logEntry->part_of,
                                        ), 'history_log_record_log')
                                    . '">'
                                    . __('Item %d', $logEntry->part_of)
                                    . '</a>';
                                break;
                        }
                    }
                    ?></td>
                    <td><?php echo $logEntry->displayUser(); ?></td>
                    <td><?php echo $logEntry->displayOperation(); ?></td>
                    <td><?php echo nl2br($logEntry->displayChanges(), true); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination"><?php echo $paginationLinks; ?></div>
        <?php else: ?>
        <br class="clear" />
        <div>
            <p><?php echo __('The query searched %s records and returned no results.', total_records('HistoryLogEntry')); ?></p>
            <p><?php echo __('Display all %shistory log entries%s.', '<a href="' . url('history-log') . '">', "</a>"); ?></p>
        </div>
        <?php endif; ?>
<?php else: ?>
        <p><?php echo __('No entry have been logged.'); ?></p>
<?php endif; ?>
    </div>
</div>
<?php
echo foot();
