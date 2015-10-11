<ul class="quick-filter-wrapper">
    <li><a href="#" tabindex="0"><?php echo __('Quick Filter'); ?></a>
    <ul class="dropdown">
        <li><span class="quick-filter-heading"><?php echo __('Quick Filter') ?></span></li>
        <li><a href="<?php echo url('history-log'); ?>"><?php echo __('View All') ?></a></li>
        <li><span style="font-weight: bold; font-style: italic; padding-left: 4px; background-color:#fff;"><?php echo __('Types'); ?></span></li>
        <li><a href="<?php echo url('history-log', array('record_type' => 'Item')); ?>"><?php echo __('Items'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('record_type' => 'Collection')); ?>"><?php echo __('Collections'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('record_type' => 'File')); ?>"><?php echo __('Files'); ?></a></li>
        <li><span style="font-weight: bold; font-style: italic; padding-left: 4px; background-color:#fff;"><?php echo __('Actions'); ?></span></li>
        <li><a href="<?php echo url('history-log', array('operation' => 'created')); ?>"><?php echo __('Created'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('operation' => 'imported')); ?>"><?php echo __('Imported'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('operation' => 'updated')); ?>"><?php echo __('Updated'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('operation' => 'exported')); ?>"><?php echo __('Exported'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('operation' => 'deleted')); ?>"><?php echo __('Deleted'); ?></a></li>
    </ul>
    </li>
</ul>
