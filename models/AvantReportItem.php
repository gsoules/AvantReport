<?php

class AvantReportItem
{
    protected $detailLayoutElementNames;
    protected $elementNameValuePairs;
    protected $itemId;
    protected $thumbnailUrl;
    protected $privateElementNames;
    protected $skipPrivateElements;

    function __construct($itemData, $detailLayoutElementNames, $privateElementNames, $sharedSearchEnabled = false)
    {
        $this->detailLayoutElementNames = $detailLayoutElementNames;
        $this->privateElementNames = $privateElementNames;
        $this->skipPrivateElements = empty(current_user());

        // Create an array of element names in the order in which they appear on a public item view page.
        $this->elementNameValuePairs = array();
        foreach ($this->detailLayoutElementNames as $name)
            $this->elementNameValuePairs[$name] = null;

        // Get the item's element values.
        if (($itemData instanceof Item))
            $this->getOmekaValues($itemData);
        else
            $this->getElasticsearchValues($itemData, $sharedSearchEnabled);
    }

    protected function createPair($name, $value, $private = false)
    {
        return array('name' => $name, 'value' => AvantReport::decode($value), 'private' => $private);
    }

    public function getElementNameValuePairs()
    {
        return $this->elementNameValuePairs;
    }

    protected function getElasticsearchValues($result, $sharedSearchEnabled)
    {
        $avantElasticsearch = new AvantElasticsearch();
        $source = $result["_source"];
        $this->thumbnailUrl = isset($source['url']['thumb']) ? $source['url']['thumb'] : '';

        foreach ($this->detailLayoutElementNames as $elementName)
        {
            $fieldName = $avantElasticsearch->convertElementNameToElasticsearchFieldName($elementName);
            $found = false;

            foreach ($source['core-fields'] as $name => $coreField)
            {
                if ($name != $fieldName)
                    continue;

                foreach ($coreField as $value)
                {
                    if ($name == 'identifier' && $sharedSearchEnabled)
                    {
                        $value = $source["item"]["contributor-id"] . "-$value";
                    }
                    $this->elementNameValuePairs[$elementName][] = $this->createPair($name, $value);
                }

                $found = true;
                break;
            }

            if (!$found && isset($source['local-fields']))
            {
                foreach ($source['local-fields'] as $name => $localField)
                {
                    if ($name != $fieldName)
                        continue;

                    foreach ($localField as $value)
                    {
                        $this->elementNameValuePairs[$elementName][] = $this->createPair($name, $value);
                    }

                    $found = true;
                    break;
                }
            }

            if (!$found && isset($source['private-fields']))
            {
                foreach ($source['private-fields'] as $name => $privateField)
                {
                    if ($name != $fieldName)
                        continue;

                    foreach ($privateField as $value)
                    {
                        $this->elementNameValuePairs[$elementName][] = $this->createPair($name, $value, true);
                    }

                    break;
                }
            }
        }
    }

    protected function getOmekaValues($item)
    {
        // Get each of this item's element values and attach it to its element name in the pairs array.
        $elementTexts = get_db()->getTable('ElementText')->findByRecord($item);
        $this->thumbnailUrl = ItemPreview::getImageUrl($item, true, true);

        foreach ($elementTexts as $elementText)
        {
            $name = ItemMetadata::getElementNameFromId($elementText['element_id']);

            // Skip private elements if no user is logged in.
            $isPrivateElement = in_array($name, $this->privateElementNames);
            if ($isPrivateElement && $this->skipPrivateElements)
            {
                continue;
            }

            $text = $elementText['text'];

            if (plugin_is_active("MDIBL"))
            {
                // Special handling for MDIBL author/school and species/common. Presentation of these data pairs is
                // very plain in a PDF. Nicer formatting with wider columns, bolded primary names (author and species)
                // with secondary names (school and common name) on a second line below the primary name, would require
                // significant enhancements to this plugin.
                $speciesElementId = ItemMetadata::getElementIdForElementName("Species");
                $authorElementId = ItemMetadata::getElementIdForElementName("Author");
                if ($elementText['element_id'] == $speciesElementId)
                {
                    // Show the species and its common name separated by a tilde.
                    $speciesData = MDIBL::getSpeciesDataFromLookupTable($text);
                    $text = MDIBL::speciesText($speciesData) . " ~ " . MDIBL::speciesCommonName($speciesData);
                }
                else if ($elementText['element_id'] == $authorElementId)
                {
                    // Show the author and their school separated by a tilde.
                    [$author, $school] = MDIBL::combineAuthorAndInstitution($item, $authorElementId, $text, false);
                    $text = "$author ~ $school";
                }
            }

            // Create a data array for this value.
            $elementData['name'] = $name;
            $elementData['value'] = AvantReport::decode($text);
            $elementData['private'] = $isPrivateElement;

            // Associate the element data array with the element name. If an element has multiple values,
            // each value will be attached as its own data array. For example, if the Subject element has
            // three values, there will be one 'Subject' entry in the $elementNameValuePairs array, but
            // that entry will have three data arrays.
            $this->elementNameValuePairs[$name][] = $elementData;
        }
    }

    public function getThumbnailUrl()
    {
        return $this->thumbnailUrl;
    }
}