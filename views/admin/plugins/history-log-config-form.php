<fieldset id="fieldset-history-log"><legend><?php echo __('Admin logs'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <a href="<?php echo html_escape(url('history-log/admin/check')); ?>" class="add button small green"><?php echo __('Missing logs'); ?></a>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php
                echo __('This button displays a page that allows to check missing logs.');
                /*
                echo ' ' . __('This may be useful when the plugin has not been installed after some records have been created, or when a plugin uses non standard functions.');
                echo ' ' . __('The rebuild is not a requirement to use this plugin, but it can help to track or recover data in the future.');
                echo ' ' . __('Furthermore, the process is still in beta phase, so the entry may be incomplete or not exact.');
                 */
                ?>
            </p>
        </div>
    </div>
</fieldset>
