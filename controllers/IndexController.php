<?php
/**
 * HistoryLog
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The HistoryLog index controller class.
 *
 * @package HistoryLog
 */
class HistoryLog_IndexController extends Omeka_Controller_AbstractActionController
{
    protected $_browseRecordsPerPage = 100;
    protected $_autoCsrfProtection = true;

    public function init()
    {
        $this->_helper->db->setDefaultModelName('HistoryLogEntry');
    }

    /**
     * The browse action.
     */
    public function browseAction()
    {
        if (!$this->_getParam('sort_field')) {
            $this->_setParam('sort_field', 'added');
            $this->_setParam('sort_dir', 'd');
        }

        // Request for downloading.
        $export = $this->_getParam('export');

        // Check format.
        if ($export == 'ods') {
            $zipProcessor = $this->_getZipProcessor();
            if (empty($zipProcessor)) {
                $flashMessenger = $this->_helper->FlashMessenger;
                $flashMessenger->addMessage(__('Your server cannot return ods zipped files.'), 'error');
                $flashMessenger->addMessage(__('Try the format fods instead.'), 'success');
                $export = null;
            }
        }

        if ($export) {
            // Return all results without pagination ("00" removes it).
            $this->setParam('per_page', '00');
        }

        parent::browseAction();

        $this->view->params = $this->getAllParams();

        if ($export) {
            if (empty($this->view->total_results)) {
                $flashMessenger = $this->_helper->FlashMessenger;
                $flashMessenger->addMessage(__('Your request returns no result.'), 'error');
                $flashMessenger->addMessage(__('The form has been reset.'), 'success');
                $this->_helper->redirector('search');
                return;
            }

            $response = $this->getResponse();

            $headers = array();
            $headers[] = __('Date');
            $headers[] = __('Type');
            $headers[] = __('Id');
            $headers[] = __('Title');
            $headers[] = __('Part Of');
            $headers[] = __('User');
            $headers[] = __('Action');
            $headers[] = __('Details');
            $this->view->headers = $headers;

            $this->view->generator = $this->_getGenerator();

            // Prepare the export if needed.
            switch ($export) {
                case 'csv':
                    $response
                        ->setHeader('Content-Disposition',
                            'attachment; filename=Omeka_History_Log_' . date('Ymd-His') . '.csv')
                        ->setHeader('Content-type', 'text/csv');
                    $this->render('browse-csv');
                    break;

                case 'ods':
                    $this->_prepareSpreadsheet();
                    $filename = $this->_prepareOds();
                    if (empty($filename)) {
                        $flashMessenger = $this->_helper->FlashMessenger;
                        $flashMessenger->addMessage(__('Cannot create the ods file. Check your temp directory and your rights.'), 'error');
                        $this->_helper->redirector('search');
                        return;
                    }

                    $this->_helper->viewRenderer->setNoRender();
                    $response
                        ->setHeader('Content-Disposition',
                           'attachment; filename=Omeka_History_Log_' . date('Ymd-His') . '.ods')
                        ->setHeader('Content-type', 'application/vnd.oasis.opendocument.spreadsheet');
                    $response->clearBody();
                    $response->setBody(file_get_contents($filename));
                    break;

                case 'fods':
                    $this->_prepareSpreadsheet();
                    $response
                        ->setHeader('Content-Disposition',
                            'attachment; filename=Omeka_History_Log_' . date('Ymd-His') . '.fods')
                        ->setHeader('Content-type', 'text/xml');
                    $this->render('browse-fods');
                    break;
            }
        }
    }

    /**
     * This shows the search form for records by going to the correct URI.
     *
     * @return void
     */
    public function searchAction()
    {
        include_once dirname(dirname(__FILE__))
            . DIRECTORY_SEPARATOR . 'forms'
            . DIRECTORY_SEPARATOR . 'Search.php';
        $form = new HistoryLog_Form_Search();

        // Prepare the form to return result in the browse view with pagination.
        $form->setAction(url(array(
            'module' => 'history-log',
            'controller' => 'index',
            'action' =>'browse',
        )));
        // The browse method requires "get" to process the query.
        $form->setMethod('get');

        $this->view->form = $form;
    }

    /**
     * Return the generator of the OpenDocument.
     *
     * @return string
     */
    protected function _getGenerator()
    {
        $iniReader = new Omeka_Plugin_Ini(PLUGIN_DIR);
        $path = basename(dirname(dirname(__FILE__)));
        $generator = sprintf('Omeka/%s - %s/%s [%s] (%s)',
            OMEKA_VERSION,
            $iniReader->getPluginIniValue($path, 'name'),
            $iniReader->getPluginIniValue($path, 'version'),
            $iniReader->getPluginIniValue($path, 'author'),
            $iniReader->getPluginIniValue($path, 'link'));
        return $generator;
    }

    /**
     * Prepare arguments for a spreadsheet.
     *
     * @return array
     */
    protected function _prepareSpreadsheet()
    {
        $tableNames = array(__('Export'));
        $headers = array($this->view->headers);
        $dateTime = date('Y-m-d\TH:i:s') . strtok(substr(microtime(), 1), ' ');
        $cells = (iterator_count(loop('HistoryLogEntry')) + ($this->view->params['exportheaders'] ? 1 : 0)) * count($this->view->headers);

        $variables = array(
            'module' => 'history-log',
            'params' => $this->view->params,
            'tableNames' => $tableNames,
            'headers' => $headers,
            'loop' => 'HistoryLogEntry',
            'generator' => $this->view->generator,
            'user' => current_user(),
            'dateTime' => $dateTime,
            'cells' => $cells,
            'tableActive' => 0,
            'declaration' => false,
        );

        unset($this->view->params);
        unset($this->view->headers);
        unset($this->view->generator);

        $this->view->variables = $variables;
    }

    /**
     * Prepare output as OpenDocument Spreadsheet (ods).
     *
     * @return string|null Filename of the ods. Null if error.
     */
    protected function _prepareOds()
    {
        $this->view->variables['declaration'] = true;

        // Create a temp dir to build the ods.
        $tempDir = tempnam(sys_get_temp_dir(), 'ods');
        unlink($tempDir);
        mkdir($tempDir);
        // @chmod($tempDir, 0755);

        // Prepare the structure of the ods file via a temp dir.
        $sourceDir = dirname(dirname(__FILE__))
            . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'scripts'
            . DIRECTORY_SEPARATOR . 'ods'
            . DIRECTORY_SEPARATOR . 'base';
        mkdir($tempDir . DIRECTORY_SEPARATOR . 'META-INF');
        // @chmod($tempDir . DIRECTORY_SEPARATOR . 'META-INF');
        mkdir($tempDir . DIRECTORY_SEPARATOR . 'Thumbnails');
        // @chmod($tempDir . DIRECTORY_SEPARATOR . 'Thumbnails');

        // Copy the default files.
        $defaultFiles = array(
            // OpenDocument requires that "mimetype" be the first file, without
            // compression in order to get the mime type without unzipping.
            'mimetype',
            'manifest.rdf',
            'META-INF' . DIRECTORY_SEPARATOR . 'manifest.xml',
            // TODO A thumbnail of the true content.
            'Thumbnails' . DIRECTORY_SEPARATOR . 'thumbnail.png',
        );
        foreach ($defaultFiles as $file) {
            $result = copy(
                $sourceDir . DIRECTORY_SEPARATOR . $file,
                $tempDir . DIRECTORY_SEPARATOR . $file);
            if (!$result) {
                return;
            }
            // @chmod($tempDir . DIRECTORY_SEPARATOR . $file, 0644);
        }

        // Prepare the other files.
        $xmlFiles = array(
            'meta.xml',
            'settings.xml',
            'styles.xml',
            'content.xml',
        );
        foreach ($xmlFiles as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $filename = tempnam(sys_get_temp_dir(), $name);
            $xml = $this->view->partial('ods/' . $name . '.php',
                $this->view->variables['module'],
                $name == 'content' ? array('variables' => $this->view->variables) : $this->view->variables);
            $result = file_put_contents($filename, $xml);
            if (!$result) {
                return;
            }
            $result = rename($filename, $tempDir . DIRECTORY_SEPARATOR . $file);
            if (!$result) {
                return;
            }
            // @chmod($tempDir . DIRECTORY_SEPARATOR . $file, 0644);
        }

        // Prepare the zip file.
        $filename = tempnam(sys_get_temp_dir(), 'OmekaOds');
        // No simple function to create a temp file with an extension.
        unlink($filename);
        $filename .= strtok(substr(microtime(), 2), ' ') . '.ods';

        // Get the zip processor.
        $zipProcessor = $this->_getZipProcessor();
        if (empty($zipProcessor)) {
            return;
        }

        switch ($zipProcessor) {
            case 'ZipArchive':
                // Create the zip.
                $zip = new ZipArchive();
                if ($zip->open($filename, ZipArchive::CREATE) !== true) {
                    return;
                }

                // Add all files.
                foreach ($defaultFiles as $file) {
                    $zip->addFile($tempDir . DIRECTORY_SEPARATOR . $file, $file);
                    $zip->setCompressionName($file, ZipArchive::CM_DEFLATE);
                }
                foreach ($xmlFiles as $file) {
                    $zip->addFile($tempDir . DIRECTORY_SEPARATOR . $file, $file);
                    $zip->setCompressionName($file, ZipArchive::CM_DEFLATE);
                }

                // No compression for "mimetype" to be readable directly by the OS.
                $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

                // Zip the file.
                $result = $zip->close();
                if (empty($result)) {
                    return;
                }
                break;

            case '/usr/bin/zip':
            default:
                // Create the zip file with "mimetype" uncompressed.
                $cd = 'cd ' . escapeshellarg($tempDir);
                $cmd = $cd
                    . ' && ' . $zipProcessor . ' --quiet -X -0 ' . escapeshellarg($filename) . ' ' . escapeshellarg('mimetype');
                Omeka_File_Derivative_Strategy_ExternalImageMagick::executeCommand($cmd, $status, $output, $errors);
                if ($status != 0) {
                    return false;
                }

                // Add other files and compress them.
                $cmd = $cd
                    . ' && ' . $zipProcessor . ' --quiet -X -9 --exclude ' . escapeshellarg('mimetype') . ' --recurse-paths ' . escapeshellarg($filename) . ' ' . escapeshellarg('.');
                Omeka_File_Derivative_Strategy_ExternalImageMagick::executeCommand($cmd, $status, $output, $errors);
                if ($status != 0) {
                    return false;
                }
                break;
        }

        return $filename;
    }

    /**
     * Check if the server support zip and return the method used.
     *
     * @return boolean
     */
    protected function _getZipProcessor()
    {
        if (class_exists('ZipArchive') && method_exists('ZipArchive', 'setCompressionName')) {
            return 'ZipArchive';
        }

        // Test the zip command line via  the processor of ExternalImageMagick.
        try {
            $cmd = 'which zip';
            Omeka_File_Derivative_Strategy_ExternalImageMagick::executeCommand($cmd, $status, $output, $errors);
            return $status == 0 ? trim($output) : false;
        } catch (Exception $e) {
            return false;
        }
    }
}
