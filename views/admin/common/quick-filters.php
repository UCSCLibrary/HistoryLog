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
        <li><span style="font-weight: bold; font-style: italic; padding-left: 4px; background-color:#fff;"><?php echo __('Date'); ?></span></li>
        <li><a href="<?php echo url('history-log', array('added' => date('Y-m-d', time()))); ?>"><?php echo __('Today'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('since' => date('Y-m-d', strtotime('monday this week')))); ?>"><?php echo __('This Week'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('since' => date('Y-m-d', strtotime('first day of this month')))); ?>"><?php echo __('This Month'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('since' => date('Y', time()) . '-01-01')); ?>"><?php echo __('This Year'); ?></a></li>
        <li><a href="<?php echo url('history-log', array('added' => date('Y-m-d', strtotime('-1 day')))); ?>"><?php echo __('Yesterday'); ?></a></li>
        <li><a href="<?php echo url('history-log', array(
            'since' => date('Y-m-d', strtotime('monday last week')),
            'until' => date('Y-m-d', strtotime('monday this week')),
        )); ?>"><?php echo __('Last Week'); ?></a></li>
        <li><a href="<?php echo url('history-log', array(
            'since' => date('Y-m', strtotime('1 month ago')) . '-01',
            'until' => date('Y-m', time()) . '-01',
        )); ?>"><?php echo __('Last Month'); ?></a></li>
        <li><a href="<?php echo url('history-log', array(
            'since' => date('Y', strtotime('1 year ago')) . '-01-01',
            'until' => date('Y', time()) . '-01-01',
        )); ?>"><?php echo __('Last Year'); ?></a></li>
    </ul>
    </li>
</ul>
