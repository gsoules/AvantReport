<?php

class AvantReportPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'public_head',
        'public_items_show',
        'public_search_results'
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

    public function hookPublicSearchResults($args)
    {
        $linkName = __('Save these search results as a PDF file');
        $url = $args['url'] . '&report=' . AvantSearch::MAX_SEARCH_RESULTS;
        echo "<p><a id='save-search-results-pdf-link' href='$url'>$linkName</a></p>";
    }
}
