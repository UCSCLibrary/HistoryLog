<?php
/**
 * METS Export
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


/**
 * METS Export plugin.
 */
class MetsExportPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
			      'config_form',
			      'admin_head',
			      'admin_collections_show'
			      );
    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array('action_contexts','response_contexts');


    /*
     * Define the METS context and set browser headers 
     * to output an XML file with a .mets extension
     */
    public function filterResponseContexts($contexts)
    {
      $contexts['METS'] = array('suffix' => 'mets',
				'headers' => array('Content-Type' => 'text/xml')
				);
      
      $contexts['METSzip'] = array('suffix' => 'metszip',
				   'headers' => array('Content-Type' => 'application/octet-stream')
				   );
   
      return $contexts;

    }

    /**
     *  Add a button on the collection display page to export the 
     * collection as a zipped array of .mets files
     */
    public function hookAdminCollectionsShow($args) {
      $collection = $args['collection'];
      echo '<a href="'.$collection->id.'?output=METSzip"><button>Export as .mets</button></a>';
    }


    /**
     * Add METS format to Omeka item output list
     */
    public function filterActionContexts($contexts, $args)
    {
      if($args['controller'] instanceOf ItemsController)
	$contexts['show'][] = 'METS';
      else if($args['controller'] instanceOf CollectionsController)
	$contexts['show'][] = 'METSzip';

      return $contexts;
    }

    /**
     * Display the plugin config form.
     */
    public function hookConfigForm()
    {
        require dirname(__FILE__) . '/config_form.php';

	//TODO make this a subclass of Omeka_Form which I instantiate here
    }

    public function hookAdminHead()
    {
      //queue_js_file('MetsExport');
    }

}
