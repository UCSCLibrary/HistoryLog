<fieldset id="fieldset-history-log-check"><legend><?php echo __('Admin logs'); ?></legend>
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
<fieldset id="fieldset-history-log-display"><legend><?php echo __('Display History Logs'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('history_log_display', __('Pages where to display logs')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <ul style="list-style-type: none;">
                <?php
                    $currentPages = json_decode(get_option('history_log_display'), true) ?: array();
                    $pages = array(
                        'collections/show',
                        'items/show',
                        'files/show',
                        'items/browse',
                    );
                    foreach ($pages as $page) {
                        echo '<li>';
                        echo $this->formCheckbox('history_log_display[]', $page,
                            array('checked' => in_array($page, $currentPages) ? 'checked' : ''));
                        echo $page;
                        echo '</li>';
                    }
                ?>
                </ul>
            </div>
        </div>
    </div>
</fieldset>
