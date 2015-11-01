<?php
/**
 * Test Table_HistoryLogEntry class.
 *
 * @package Omeka
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2011
 */
class HistoryLog_HistoryLogMainTest extends HistoryLog_Test_AppTestCase
{
    protected $_isAdminTest = true;

    public function testCreate()
    {
        $item = $this->_createOne();

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('HistoryLogEntry'));
        $this->assertEquals(3, total_records('HistoryLogChange'));
    }

    public function testNoUpdate()
    {
        $item = $this->_createOne();
        $itemId = $item->id;
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('HistoryLogEntry'));
        $this->assertEquals(3, total_records('HistoryLogChange'));

        // No update, so no change.
        $item = get_record_by_id('Item', $itemId);
        $item->save();

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('HistoryLogEntry'));
        $this->assertEquals(3, total_records('HistoryLogChange'));
    }

    public function testUpdateAdd()
    {
        $item = $this->_createOne();

        $elementTexts = array();
        $elementTexts['Dublin Core']['Title'][] = array('text' => 'title updated', 'html' => false);
        $elementTexts['Dublin Core']['Subject'][] = array('text' => 'subject other', 'html' => false);
        $item->addElementTextsByArray($elementTexts);
        $item->save();

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(2, total_records('HistoryLogEntry'));
        $this->assertEquals(5, total_records('HistoryLogChange'));
    }

    public function testMultiple()
    {
        // Create ten items via standard functions.
        $items = array();
        $metadata = array();
        for ($i = 1; $i <= 10; $i++) {
            $elementTexts = array();
            $elementTexts['Dublin Core']['Title'][] = array('text' => 'title ' . $i, 'html' => false);
            $elementTexts['Dublin Core']['Subject'][] = ($i%2 == 1)
                ? array('text' => 'subject odd', 'html' => false)
                : array('text' => 'subject even', 'html' => false);
            $elementTexts['Dublin Core']['Creator'][] = array('text' => 'creator #' . $i, 'html' => false);
            $elementTexts['Dublin Core']['Date'][] = array('text' => 2000 + $i, 'html' => false);
            if ($i >= 8) {
                $elementTexts['Dublin Core']['Creator'][] = array('text' => 'creator bis #' . $i, 'html' => false);
            }
            $items[$i] = insert_item($metadata, $elementTexts);
        }

        $this->assertEquals(11, total_records('Item'));
        $this->assertEquals(10, total_records('HistoryLogEntry'));
        $this->assertEquals(43, total_records('HistoryLogChange'));

        // Update some of them.
        $items[1]->delete();
        $this->assertEquals(10, total_records('Item'));
        $this->assertEquals(11, total_records('HistoryLogEntry'));
        $this->assertEquals(47, total_records('HistoryLogChange'));

        $elementTexts = array();
        $elementTexts['Dublin Core']['Title'][] = array('text' => 'title updated', 'html' => false);
        $elementTexts['Dublin Core']['Subject'][] = array('text' => 'subject updated', 'html' => false);
        $items[2]->addElementTextsByArray($elementTexts);
        $items[2]->save();
        $this->assertEquals(10, total_records('Item'));
        $this->assertEquals(12, total_records('HistoryLogEntry'));
        $this->assertEquals(49, total_records('HistoryLogChange'));

        $elementTexts = array();
        $elementTexts['Dublin Core']['Creator'][] = array('text' => 'creator ter #8', 'html' => false);
        $items[8]->addElementTextsByArray($elementTexts);
        $items[8]->save();
        $this->assertEquals(10, total_records('Item'));
        $this->assertEquals(13, total_records('HistoryLogEntry'));
        $this->assertEquals(50, total_records('HistoryLogChange'));

        $elementTexts = array();
        $elementTexts['Dublin Core']['Creator'][] = array('text' => 'creator ter #9', 'html' => false);
        $items[9]->addElementTextsByArray($elementTexts);
        $items[9]->save();
        $this->assertEquals(10, total_records('Item'));
        $this->assertEquals(14, total_records('HistoryLogEntry'));
        $this->assertEquals(51, total_records('HistoryLogChange'));
    }

    public function testBuilderItemAdd()
    {
        $item = $this->_createOne();
        $itemId = $item->id;
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('HistoryLogEntry'));
        $this->assertEquals(3, total_records('HistoryLogChange'));

        $item = get_record_by_id('Item', $itemId);
        $metadata = array();
        $elementTexts = array();
        $elementTexts['Dublin Core']['Title'] = array();
        $elementTexts['Dublin Core']['Creator'][] = array('text' => 'creator replaced', 'html' => false);
        $item = update_item($item, $metadata, $elementTexts);
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(2, total_records('HistoryLogEntry'));
        $this->assertEquals(4, total_records('HistoryLogChange'));
    }

    public function testBuilderItemOverwrite()
    {
        $item = $this->_createOne();
        $itemId = $item->id;
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('HistoryLogEntry'));
        $this->assertEquals(3, total_records('HistoryLogChange'));

        $item = get_record_by_id('Item', $itemId);
        $metadata = array(
            Builder_Item::OVERWRITE_ELEMENT_TEXTS => true,
        );
        $elementTexts = array();
        $elementTexts['Dublin Core']['Creator'][] = array('text' => 'creator replaced', 'html' => false);
        $item = update_item($item, $metadata, $elementTexts);
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(2, total_records('HistoryLogEntry'));
        $this->assertEquals(4, total_records('HistoryLogChange'));
    }

    public function testBuilderItemOverwriteDelete()
    {
        $item = $this->_createOne();
        $itemId = $item->id;
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('HistoryLogEntry'));
        $this->assertEquals(3, total_records('HistoryLogChange'));

        $item = get_record_by_id('Item', $itemId);
        $metadata = array(
            Builder_Item::OVERWRITE_ELEMENT_TEXTS => true,
        );
        $elementTexts = array();
        $elementTexts['Dublin Core']['Title'] = array();
        $item = update_item($item, $metadata, $elementTexts);
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->markTestSkipped(
            __('Builder_Item does not work with "overwrite" to delete an element.')
        );
        $this->assertEquals(2, total_records('HistoryLogEntry'));
        $this->assertEquals(5, total_records('HistoryLogChange'));
    }

    public function testDeleteElementTextsByElementId()
    {
        $item = $this->_createOne();
        $itemId = $item->id;
        unset($item);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('HistoryLogEntry'));
        $this->assertEquals(3, total_records('HistoryLogChange'));

        $item = get_record_by_id('Item', $itemId);
        $elementCreator = get_record('Element', array('element_set_name' => 'Dublin Core', 'name' => 'Creator'));
        $result = $item->deleteElementTextsByElementId(array($elementCreator->id));
        $elementDate = get_record('Element', array('element_set_name' => 'Dublin Core', 'name' => 'Date'));
        $result = $item->deleteElementTextsByElementId(array($elementDate->id));
        $item->save();

        $this->assertEquals(2, total_records('Item'));
        $this->markTestSkipped(
            __('Process done via deleteElementTextsByElementId() does not log anything.')
        );
        $this->assertEquals(2, total_records('HistoryLogEntry'));
        $this->assertEquals(5, total_records('HistoryLogChange'));
    }

    public function testItemAndCollection()
    {
        $item = $this->_createOne();
        $itemId = $item->id;
        unset($item);

        // Create a collection.
        $metadata = array();
        $elementTexts = array();
        $elementTexts['Dublin Core']['Title'][] = array('text' => 'Collection title 1', 'html' => false);
        $elementTexts['Dublin Core']['Creator'][] = array('text' => 'Collection creator #1', 'html' => false);
        $elementTexts['Dublin Core']['Date'][] = array('text' => 2002, 'html' => false);

        $collection = insert_collection($metadata, $elementTexts);

        $this->assertEquals(2, total_records('Item'));
        $this->assertEquals(1, total_records('Collection'));
        $this->assertEquals(2, total_records('HistoryLogEntry'));
        $this->assertEquals(6, total_records('HistoryLogChange'));

        // Put the item in that collection, without any other change.
        $item = get_record_by_id('Item', $itemId);
        $item->collection_id = $collection->id;
        $item->save();
        $this->assertEquals(3, total_records('HistoryLogEntry'));
        $this->assertEquals(6, total_records('HistoryLogChange'));
    }
}
