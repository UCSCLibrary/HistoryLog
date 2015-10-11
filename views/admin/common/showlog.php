<?php
$setName = __('Item Curation History');
$elementName = __('Log');
?>
<div class="element-set">
    <h2><?php echo html_escape($setName); ?></h2>
    <div id="<?php echo text_to_id(html_escape("item-curation-history-log")); ?>" class="element">
        <h3><?php echo html_escape($elementName); ?></h3>
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
                    <td><?php echo $logEntry->action; ?></td>
                    <td><?php echo $logEntry->displayChange(); ?></td>
                </tr>
                <?php
                endforeach;
                if ($limit > 0 && count($logEntries) >= $limit):
                ?>
                <tr>
                    <td>
                        <a href="<?php echo $this->url('history-log/log/show/item/' . $itemId); ?>">
                            <strong><?php echo __('See more'); ?></strong>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div><!-- end element-text -->
    </div><!-- end element -->
</div><!-- end element-set -->
