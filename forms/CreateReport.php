<?php
/**
 * History log report generation form
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * History log report generation form class
 *
 * @package HistoryLog
 */
class HistoryLog_Form_Reports extends Omeka_Form
{
    /**
     * Construct the report generation form.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        try {
            $this->_registerElements();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Define the form elements.
     *
     * @return void
     */
    private function _registerElements()
    {
        try {
            $recordTypeOptions = $this->_getRecordTypeOptions();
            $collectionOptions = $this->_getCollectionOptions();
            $userOptions = $this->_getUserOptions();
            $operationOptions = $this->_getoperationOptions();
        } catch (Exception $e) {
            throw $e;
        }

        if (version_compare(OMEKA_VERSION, '2.2.1') >= 0) {
            $this->addElement('hash', 'history_log_token');
        }

        // Record type.
        $this->addElement('select', 'record_type', array(
            'label' => __('Record Type'),
            'description' => __("The type of record whose log information will be retrieved (default: all)"),
            'value' => '0',
            'order' => 1,
            'validators' => array(
                'alnum',
            ),
            'required' => false,
            'multiOptions' => $recordTypeOptions,
        ));

        // Collection.
        $this->addElement('select', 'collection', array(
            'label' => __('Collection'),
            'description' => __("If record type is Item, the collection whose items' log information will be retrieved (default: all)"),
            'value' => '0',
            'order' => 2,
            'validators' => array(
                'digits',
            ),
            'required' => false,
            'multiOptions' => $collectionOptions,
        ));

        // Item.
        $this->addElement('text', 'item', array(
            'label' => __('Item'),
            'description' => __("If record type is File, the item whose files' log information will be retrieved (default: all)"),
            'value' => '',
            'order' => 3,
            'validators' => array(
                'digits',
            ),
            'required' => false,
        ));

        // User(s).
        $this->addElement('select', 'user', array(
            'label' => __('User(s)'),
            'description' => __('All administrator users whose edits will be retrieved (default: all)'),
            'value' => '0',
            'order' => 4,
            'validators' => array(
                'digits',
            ),
            'required' => false,
            'multiOptions' => $userOptions,
        ));

        // Operations.
        $this->addElement('select', 'operation', array(
            'label' => __('Operation'),
            'description' => __('Logged curatorial operations to retrieve in this report (default: all)'),
            'value' => '0',
            'order' => 5,
            'validators' => array(
                'alnum',
            ),
            'required' => false,
            'multiOptions' => $operationOptions,
        ));

        // Dates.
        $this->addElement('text', 'date-start', array(
            'label' => __('Start Date:'),
            'description' => __('The earliest date from which to retrieve logs'),
            'value' => 'YYYY-MM-DD',
            'order' => 6,
            'style' => 'max-width: 120px;',
            'required' => false,
            'validators' => array(
                array(
                    'Date',
                    false,
                    array(
                        'format' => 'yyyy-mm-dd',
                    )
                )
            )
        ));

        $this->addElement('text', 'date-end', array(
            'label' => __('End Date:'),
            'description' => __('The latest date from which to retrieve logs'),
            'value' => 'yyyy-mm-dd',
            'order' => 7,
            'style' => 'max-width: 120px;',
            'required' => false,
            'validators' => array(
                array(
                    'Date',
                    false,
                    array(
                        'format' => 'yyyy-mm-dd',
                    )
                )
            )
        ));

        $this->addElement('checkbox', 'csv-download', array(
            'label' => __('Download log as CSV file'),
            'order' => 8,
            'style' => 'max-width: 120px;',
            'required' => false,
        ));

        $this->addElement('checkbox', 'csv-headers', array(
            'label' => __('Include headers in csv files'),
            'order' => 9,
            'style' => 'max-width: 120px;',
            'required' => false,
        ));

        // Submit.
        $this->addElement('submit', 'submit-view', array(
            'label' => __('Generate Log'),
        ));

        /*
        $this->addElement('submit', 'submit-download', array(
            'label' => __('Download Log'),
        ));
        */

        // Display Groups.
        $this->addDisplayGroup(array(
            'record_type',
            'collection',
            'item',
            'user',
            'operation',
            'date-start',
            'date-end',
            'csv-download',
            'csv-headers'
        ), 'fields');

        $this->addDisplayGroup(array(
                'submit-view'
            ),
            'submit_buttons',
            array(
                'style' => 'clear:left;'
        ));
    }

    /**
     * Process the data from the form and retrieve the requested log data.
     *
     * @param string $style The style in which to return the data.
     * Accepted values: "html"(default), "JSON"
     * @return string $log Html to display requested log information
     */
    public static function ProcessPost($style = 'html')
    {
        $log = '';

        if (isset($_REQUEST['operation'])) {
            $params = array();
            if (!empty($_REQUEST['record_type'])) {
                $params['record_type'] = $_REQUEST['record_type'];
            }
            // TODO Add record id range like in advanced search.
            // if (!empty($_REQUEST['record_id'])) {
            //     $params['record_id'] = $_REQUEST['record_id'];
            // }
            if (!empty($_REQUEST['collection']) && $_REQUEST['record_type'] == 'Item') {
                $params['part_of'] = $_REQUEST['collection'];
            }
            if (!empty($_REQUEST['item']) && $_REQUEST['record_type'] == 'File') {
                $params['part_of'] = $_REQUEST['item'];
            }
            if (!empty($_REQUEST['user'])) {
                $params['user_id'] = $_REQUEST['user'];
            }
            if (!empty($_REQUEST['operation'])) {
                $params['operation'] = $_REQUEST['operation'];
            }

            $timeStart = null;
            if (!empty($_REQUEST['datestart']) && $_REQUEST['datestart'] != 'yyyy-mm-dd') {
                $timeStart = $_REQUEST['datestart'];
            }
            $timeEnd = null;
            if (!empty($_REQUEST['dateend']) && $_REQUEST['dateend'] != 'yyyy-mm-dd') {
                $timeEnd = $_REQUEST['dateend'];
            }

            $logTable = get_db()->getTable('HistoryLogEntry');

            try {
                $logEntries = $logTable->getEntries($params, $timeStart, $timeEnd);
            }
            catch (Exception $e) {
                throw $e;
            }

            if (count($logEntries) > 0) {
                if ($style == 'html') {
                    $logStart = '<table><tr style="font-weight:bold"><td>' . __('Type') . '</td><td>' . __('Record Title') . '</td><td>' . __('User') . '</td><td>' . __('Action') . '</td><td>' . __('Details') . '</td><td>' . __('Date') . '</td></tr>';
                    $rowStart = '<tr><td>';
                    $colSep = '</td><td>';
                    $rowEnd = '</td></tr>';
                    $logEnd = '</table>';
                } else if ($style == 'csv') {
                    $logStart = $_REQUEST['csvheaders'] ? __('Type') . ',' . __('Record Title') . ',' . __('User') . ',' . __('Action') . ',' . __('Details') . ',' . __('Date') . PHP_EOL : '';
                    $rowStart = '';
                    $colSep = ',';
                    $rowEnd = PHP_EOL;
                    $logEnd = '';
                }

                $log .= $logStart;
                foreach ($logEntries as $logEntry) {
                    $log .= $rowStart;
                    $log .= str_replace($colSep, '\\' . $colSep, $logEntry->record_type);
                    $log .= $colSep;
                    $log .= str_replace($colSep, '\\' . $colSep, $logEntry->title);
                    $log .= $colSep;
                    $log .= str_replace($colSep, '\\' . $colSep, $logEntry->displayUser());
                    $log .= $colSep;
                    $log .= str_replace($colSep, '\\' . $colSep, $logEntry->displayOperation());
                    $log .= $colSep;
                    $log .= str_replace($colSep, '\\' . $colSep, $logEntry->displayChange());
                    $log .= $colSep;
                    $log .= str_replace($colSep, '\\' . $colSep, $logEntry->displayAdded());
                    $log .= $rowEnd;
                }
                $log .= $logEnd;
            } else {
                $log .= '<strong>' . __('No matching logs found.') . '</strong>';
            }
        }

        return $log;
    }

    /**
     * Retrieve possible record types as selectable option list.
     *
     * @return array $options An associative array of the logged record event
     * types.
     */
    private function _getRecordTypeOptions()
    {
        return array(
            '0' => __('All types of record'),
            'Item' => __('Items'),
            'Collection' => __('Collections'),
            'File' => __('Files'),
        );
    }

    /**
     * Retrieve Collections as selectable option list.
     *
     * @return array $collections An associative array of the collection IDs and
     * titles.
     */
    private function _getCollectionOptions()
    {
        $collections = get_table_options('Collection');
        unset($collections['']);

        $options = array(
            '0' => __('All Collections'),
        );
        $options += $collections;
        return $options;
    }

    /**
     * Retrieve Omeka Admin Users as selectable option list
     *
     * @return array $collections An associative array of the userIds and
     * usernames of all omeka users with admin privileges.
     */
    private function _getUserOptions()
    {
        $options = array(
            '0' => __('All Users'),
        );

        try {
            $acl = get_acl();
            $roles = $acl->getRoles();
            foreach ($roles as $role) {
                $users = get_records('User', array(
                        'role' => $role,
                    ), '0');
                foreach ($users as $user) {
                    $options[$user->id] = $user->name . ' (' . $role . ')';
                }
            }
        } catch (Exception $e) {
            throw ($e);
        }

        return $options;
    }

    /**
     * Retrieve possible log operations as selectable option list.
     *
     * @return array $options An associative array of the logged record event
     * types.
     */
    private function _getOperationOptions()
    {
        return array(
            '0' => __('All Actions'),
            'created' => __('Record Created'),
            'imported' => __('Record Imported'),
            'updated' => __('Record Updated'),
            'exported' => __('Record Exported'),
            'deleted' => __('Record Deleted'),
        );
    }
}
