<script type="text/javascript">
jQuery(document).ready(function ()
{
    <?php echo $viewerScript; ?>

    // Style the zoom navigator dynamically to override its hard-coded element styles.
    var zoomNavigator = jQuery('.navigator');
    zoomNavigator.css({'background-color': 'transparent'});
    zoomNavigator.css({'border-width': '1px'});

    // Override h1 styling when on a page having a zoomable image.
    jQuery('h1').css({'margin-bottom': '4px'});

    var zoomToggle = jQuery('#zoom-toggle-link');
    var zoomViewerContainer = jQuery('#openseadragon');
    var itemFile = jQuery('#item-files');

    // Don't initially show the zoomable image on mobile devices where users may want to
    // scroll the page, but do so while touching the zoom viewer which only pans the image.
    var isMobile = /Mobi/i.test(navigator.userAgent) || /Android/i.test(navigator.userAgent);
    var showingzoomViewer = !isMobile;

    var buttonTextHide = '<?php echo __('Turn Image Zoom Off'); ?>';
    var buttonTextShow= '<?php echo __('Turn Image Zoom On'); ?>';

    if (showingzoomViewer)
    {
        zoomToggle.text(buttonTextHide);
        itemFile.hide();
    }
    else
    {
        zoomToggle.text(buttonTextShow);
        zoomViewerContainer.hide();
    }

    zoomToggle.click(function(e)
    {
        e.preventDefault();
        showingzoomViewer = !showingzoomViewer;
        zoomViewerContainer.toggle();
        itemFile.toggle();
        zoomToggle.text(showingzoomViewer ? buttonTextHide : buttonTextShow);
    });
});
</script>
