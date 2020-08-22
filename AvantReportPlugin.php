<?php

class AvantReportPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'public_head',
        'public_items_show'
    );

    protected $_filters = array(
    );

    public function hookPublicHead($args)
    {
        queue_css_file('avantreport');
    }

    public function hookPublicItemsShow($args)
    {
        $linkName = __('Save this item as a PDF file');
        echo "<p><a id='save-item-pdf-link' href='?report'>$linkName</a></p>";
    }
}
