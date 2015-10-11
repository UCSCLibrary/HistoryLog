<?php
$pageTitle = __('Curation History Log (%d total)', $total_results);
echo head(array(
    'title' => $pageTitle,
    'bodyclass' => 'history-log browse',
));
?>
<div id="primary">
    <?php echo flash(); ?>
    <h2><?php echo __('Curation History Log'); ?></h2>
<?php if (iterator_count(loop('HistoryLogEntry'))): ?>
        <?php echo common('quick-filters'); ?>
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
                    <td><?php echo $logEntry->record_id; ?></td>
                    <td><?php echo $logEntry->title; ?></td>
                    <td><?php
                    if (!empty($logEntry->part_of)) {
                        switch ($logEntry->record_type) {
                            case 'Item':
                                echo __('Collection %d', $logEntry->part_of);
                                break;
                            case 'File':
                                echo __('Item %d', $logEntry->part_of);
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
    </form>
<?php else: ?>
    <?php if (total_records('HistoryLogEntry') == 0): ?>
        <p><?php echo __('No entry have been logged.'); ?></p>
    <?php else: ?>
        <p><?php echo __('The query searched %s records and returned no results.', total_records('HistoryLogEntry')); ?></p>
        <p><a href="<?php echo url('history-log'); ?>"><?php echo __('See all history log entries.'); ?></a></p>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php
    echo foot();
