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
        // Get each of this item's element values and attach it to it's element name in the pairs array.
        $elementTexts = get_db()->getTable('ElementText')->findByRecord($item);
        $this->thumbnailUrl = ItemPreview::getImageUrl($item, true, true);;

        foreach ($elementTexts as $elementText)
        {
            $name = ItemMetadata::getElementNameFromId($elementText['element_id']);

            // Skip private elements if no user is logged in.
            $isPrivateElement = in_array($name, $this->privateElementNames);
            if ($isPrivateElement && $this->skipPrivateElements)
            {
                continue;
            }

            // Create a data array for this value.
            $elementData['name'] = $name;
            $elementData['value'] = AvantReport::decode($elementText['text']);
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