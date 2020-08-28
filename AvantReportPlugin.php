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
        $totalResults = $args['total'];
        if ($totalResults <= AvantSearch::MAX_SEARCH_RESULTS)
        {
            $url = $args['url'] . '&report=' . $totalResults;
            echo "<p><a class='search-link' id='save-search-results-pdf-link' href='$url'>$linkName</a></p>";
        }
        else
        {
            $message = __('There are too many results to create a PDF.\n\nRefine your search to return no more than %s results.', AvantSearch::MAX_SEARCH_RESULTS);
            echo "<p><a class='search-link' id='save-search-results-pdf-link' onclick='alert(\"$message\");' href='#'>$linkName</a></p>";
        }
    }
}
