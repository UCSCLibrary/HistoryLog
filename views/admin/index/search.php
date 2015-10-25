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
?>
<div id="primary">
    <?php echo flash(); ?>
<?php if (total_records('HistoryLogEntry') > 0): ?>
    <div><?php echo common('quick-filters'); ?></div>
    <br class="clear" />
    <div><?php echo$form; ?></div>
<?php else: ?>
        <p><?php echo __('No entry have been logged.'); ?></p>
<?php endif; ?>
</div>
<?php echo foot();
