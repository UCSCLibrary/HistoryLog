<?php
$setName = 'Item Curation History';
$elementName = 'Log';
?>
<div class="element-set">
    <h2><?php echo html_escape(__($setName)); ?></h2>
    <div id="<?php echo text_to_id(html_escape("$setName $elementName")); ?>" class="element">
        <h3><?php echo html_escape(__($elementName)); ?></h3>
        <div class="element-text">
            <table>
                 <tr>
                    <td><strong>Time</strong></td>
                    <td><strong>User</strong></td>
                    <td><strong>Action</strong></td>
                    <td><strong>Details</strong></td>
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
                            <strong>See more</strong>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div><!-- end element-text -->
    </div><!-- end element -->
</div><!-- end element-set -->
