<?php
$pageTitle = __('Curation History Log (%d total)', $total_results);
echo head(array(
    'title' => $pageTitle,
    'bodyclass' => 'history-log browse',
));
?>
<div id="primary">
    <?php echo flash(); ?>
    <div>
<?php if (total_records('HistoryLogEntry') == 0): ?>
        <p><?php echo __('No entry have been logged.'); ?></p>
<?php else: ?>
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
                    $browseHeadings[__('Title')] = 'title';
                    $browseHeadings[__('Part of ')] = 'part_of';
                    $browseHeadings[__('User')] = 'user_id';
                    $browseHeadings[__('Action')] = 'operation';
                    $browseHeadings[__('Change')] = null;
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
                    <td><?php echo $logEntry->record_type; ?></td>
                    <td><?php
                        if ($record = $logEntry->getRecord()) {
                            echo link_to($record, 'show', $logEntry->record_id);
                        } else {
                            echo $logEntry->record_id;
                        }
                    ?></td>
                    <td><?php echo $logEntry->title; ?></td>
                    <td><?php
                    if (!empty($logEntry->part_of)) {
                        switch ($logEntry->record_type) {
                            case 'Item':
                                $part = get_record_by_id('Collection', $logEntry->part_of);
                                $text = __('Collection %d', $logEntry->part_of);
                                if ($part) {
                                    echo link_to($part, 'show', $text);
                                } else {
                                    echo $text;
                                }
                                break;
                            case 'File':
                                $part = get_record_by_id('Item', $logEntry->part_of);
                                $text = __('Item %d', $logEntry->part_of);
                                if ($part) {
                                    echo link_to($part, 'show', $text);
                                } else {
                                    echo $text;
                                }
                                break;
                        }
                    }
                    ?></td>
                    <td><?php echo $logEntry->displayUser(); ?></td>
                    <td><?php echo $logEntry->displayOperation(); ?></td>
                    <td><?php echo $logEntry->displayChange(); ?></td>
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
    </div>
<?php endif; ?>
</div>
<?php
    echo foot();
