<?php
$title = __('Curation History');
$subtitle = __('Last Changes for %s #%d', $record_type, $record_id);
?>
<div class="element-set">
    <h2><?php echo html_escape($title); ?></h2>
    <div id="<?php echo text_to_id(html_escape("history-log")); ?>" class="element">
        <h3><?php echo html_escape($subtitle); ?></h3>
        <div class="element-text">
            <table>
                 <tr>
                    <td><strong><?php echo __('Time'); ?></strong></td>
                    <td><strong><?php echo __('User'); ?></strong></td>
                    <td><strong><?php echo __('Action'); ?></strong></td>
                    <td><strong><?php echo __('Details'); ?></strong></td>
                  </tr>
                <?php
                foreach ($logEntries as $logEntry):
                ?>
                <tr>
                    <td><?php echo $logEntry->added; ?></td>
                    <td><?php echo $logEntry->displayUser(); ?></td>
                    <td><?php echo $logEntry->displayOperation(); ?></td>
                    <td><?php echo nl2br($logEntry->displayChanges(), true); ?></td>
                </tr>
                <?php
                endforeach;
                if ($limit > 0 && count($logEntries) >= $limit):
                ?>
                <tr>
                    <td>
                        <a href="<?php echo url(array(
                                    'type' => Inflector::tableize($logEntry->record_type),
                                    'id' => $logEntry->record_id,
                                ), 'history_log_record_log'); ?>">
                            <strong><?php echo __('See more'); ?></strong>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
