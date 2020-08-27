<?php

class AvantReport
{
    // For development/debugging, set the border to 1 so you can see individual cells.
    const BORDER = 0;

    protected $detailLayoutElementNames;
    protected $pdf;
    protected $privateElementsData;
    protected $skipPrivateElements;

    function __construct()
    {
        $this->detailLayoutElementNames = SearchConfig::getOptionDataForDetailLayout()[0];
        $this->privateElementsData = CommonConfig::getOptionDataForPrivateElements();
        $this->skipPrivateElements = empty(current_user());
    }

    public function createReportForItem($item)
    {
        $this->initializeReport('P', $item);

        // Emit the item title.
        $this->pdf->SetFont('Arial','B',13);
        $title = ItemMetadata::getItemTitle($item);
        $this->pdf->Cell(0, 0.2, self::decode($title), self::BORDER);
        $this->pdf->Ln(0.4);

        // Determine if the item has an attachment.
        $itemFiles = $item->Files;
        if ($itemFiles)
        {
            // The item has an attachment.
            $primaryImage = $itemFiles[0];
            if ($primaryImage['mime_type'] == 'image/jpeg')
            {
                // Emit the image.
                $imageFileName = FILES_DIR . '/fullsize/' . $primaryImage['filename'];
                $this->pdf->Image($imageFileName);
            }
            else
            {
                // The attachment is not an image. Just emit its file name.
                $this->pdf->Cell(1.0, 0.2, "Attachment:", self::BORDER, 0, 'R');
                $this->pdf->SetFont('Arial', 'U', 10);
                $attachmentUrl = WEB_DIR . '/files/original/' . $primaryImage['filename'];
                $this->pdf->AddLink();
                $this->pdf->Cell(0, 0.2, $attachmentUrl, self::BORDER);
            }
            $this->pdf->Ln(0.4);
        }

        // Get the item's elements.
        $this->emitItemElements($item);

        // Prompt the user to save the file.
        $this->downloadReport(__('item-') . ItemMetadata::getItemIdentifier($item));
    }

    /* @var $searchResults SearchResultsTableView */
    public function createReportForSearchResults($searchResults)
    {
        $layoutId = $searchResults->getSelectedLayoutId();
        $this->initializeReport($layoutId == 1 ? 'P' : 'L');

        // Emit the search filters and selector bar options.
        $this->pdf->SetFont('Arial', 'B', 9);
        $filterText = $this->getFilterText($searchResults);
        $this->pdf->Cell(0, 0.2, $filterText, self::BORDER, 0, '');
        $this->pdf->Ln(0.3);

        // Switch to the font that subsequent text will use.
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('Arial', '', 8);

        // Emit the result rows.
        if ($searchResults->getSelectedLayoutId() == 1)
            $this->emitRowsForDetailLayout($searchResults);
        else
            $this->emitRowsForCompressedLayout($searchResults);

        // Prompt the user to save the file.
        $this->downloadReport('search-results');
    }

    protected static function decode($text)
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return iconv('UTF-8', 'windows-1252', $text);
    }

    protected function downloadReport($name)
    {
        $fileName = $name . '.pdf';
        $this->pdf->Output($fileName, 'D');
    }

    protected function emitElementNameValuePairs(array $elementNameValuePairs, $leftColumnWidth)
    {
        // This method emits the two-column presentation of element name and values.
        // The name appears right justified in the left column and the value appears in the right column.
        // The names of private elements, if shown, appear in gray italics.
        $previousName = '';
        foreach ($elementNameValuePairs as $elementNameValuePair)
        {
            // Handle the case where an element has multiple values. The element's name is displayed
            // only once in the left column, and each value is displayed on a separate line in the right column.
            $name = $elementNameValuePair['name'];
            if ($name == $previousName)
            {
                $name = '';
            }
            else
            {
                $previousName = $name;
                $name .= ':';

                // Show names of private elements in gray italics.
                if ($elementNameValuePair['private'])
                {
                    $this->pdf->SetFont('Arial', 'I', 8);
                    $this->pdf->SetTextColor(120, 120, 120);
                }
                else
                {
                    $this->pdf->SetFont('Arial', '', 8);
                    $this->pdf->SetTextColor(0, 0, 0);
                }
            }

            // Emit the element name in the left column, right justified.
            $this->pdf->Cell($leftColumnWidth, 0.18, $name, self::BORDER, 0, 'R');

            // Emit the element value with normal black text, left justified. Long values will wrap in their multicell.
            $this->pdf->SetFont('Arial', '', 8);
            $this->pdf->SetTextColor(0, 0, 0);
            $rightColumnWidth = 7.0 - $leftColumnWidth;
            $value = $elementNameValuePair['value'];
            $this->pdf->MultiCell($rightColumnWidth, 0.18, $value, self::BORDER);
            $this->pdf->Ln(0.02);
        }
    }

    protected function emitItemElements($item, $leftColumnWidth = 1.0)
    {
        $elementTexts = get_db()->getTable('ElementText')->findByRecord($item);
        $elementNameValuePairs = array();

        foreach ($this->detailLayoutElementNames as $elementName)
        {
            foreach ($elementTexts as $elementText)
            {
                $name = ItemMetadata::getElementNameFromId($elementText['element_id']);
                if ($name != $elementName)
                    continue;

                $isPrivateElement = in_array($name, $this->privateElementsData);

                // Skip private elements if no user is logged in.
                if ($isPrivateElement && $this->skipPrivateElements)
                {
                    continue;
                }

                $elementData['private'] = $isPrivateElement;
                $elementData['name'] = $elementName;
                $elementData['value'] = self::decode($elementText['text']);

                $elementNameValuePairs[] = $elementData;
            }
        }

        $this->emitElementNameValuePairs($elementNameValuePairs, $leftColumnWidth);
    }

    protected function emitLine($orientation, $y)
    {
        $endLine = $orientation == 'P' ? 7.70 : 10.20;
        $this->pdf->Line(0.8, $y, $endLine, $y);
    }

    protected function emitRowsForCompressedLayout($searchResults)
    {
        $useElasticsearch = $searchResults->useElasticsearch();
        $results = $searchResults->getResults();
        $layoutId = $searchResults->getSelectedLayoutId();

        // Push the table row a little to the right so that the table's left border aligns with the page header.
        $indent = 0.05;

        $layoutColumns = $searchResults->getLayoutsData()[$layoutId]['columns'];

        $widths = array();
        $availableWidth = 9.5 - ($indent * 2);
        $skippedColumns = array();
        $columnCount = count($layoutColumns);
        $columnIndex = 0;
        foreach ($layoutColumns as $layoutColumnName)
        {
            $columnIndex += 1;

            if ($availableWidth <= 0)
            {
                $skippedColumns[] = $layoutColumnName;
                continue;
            }

            if ($layoutColumnName == 'Identifier')
                $width = 0.6;
            elseif ($layoutColumnName == 'Title' || $layoutColumnName == 'Description')
                $width = $columnCount > 5 ? 1.75 : 4.0;
            else
                $width = $columnCount > 6 ? 1.25 : 2.0;

            if ($columnIndex == $columnCount)
            {
                // Give the last column all of the remaining space.
                $width = $availableWidth;
            }
            else
            {
                if ($availableWidth - $width < 0)
                    $width = $availableWidth;
                $availableWidth -= $width;
            }

            $widths[] = $width;
        }
        $this->pdf->SetWidths($widths);

        $rows = array();
        $headerRow = array();

        foreach ($results as $index => $result)
        {
            $item = $this->getItem($useElasticsearch, $result);

            $elementTexts = get_db()->getTable('ElementText')->findByRecord($item);

            // Loop over each element column. Note that this logic does not need to check for and excluded private
            // elements, because a user has to be logged in to choose a layout that contains private elements.
            $row = array();

            foreach ($layoutColumns as $columnName)
            {
                if (in_array($columnName, $skippedColumns))
                    continue;

                if ($index == 0)
                    $headerRow[] = $columnName;

                $row[$columnName] = '';

                foreach ($elementTexts as $count => $elementText)
                {
                    $name = ItemMetadata::getElementNameFromId($elementText['element_id']);
                    if ($name != $columnName)
                        continue;

                    $text = self::decode($elementText['text']);
                    if (!empty($row[$columnName]))
                        $row[$columnName] .= PHP_EOL;
                    $row[$columnName] .= $text;
                }
            }

            $data = array();
            foreach($row as $cell)
                $data[] = $cell;
            $rows[] = $data;
        }

        foreach ($rows as $index => $row)
        {
            if ($index == 0)
            {
                // Emit the header row.
                $this->pdf->Row($headerRow, $indent, null);
            }

            // Emit the result row.
            $this->pdf->Row($row, $indent, $headerRow);
        }
    }

    protected function emitRowsForDetailLayout($searchResults)
    {
        $useElasticsearch = $searchResults->useElasticsearch();
        $results = $searchResults->getResults();

        foreach ($results as  $result)
        {
            // Determine if the next row needs to start on a new page.
            if ($this->pdf->GetY() > 8.5)
            {
                // Start the next row on a new page.
                $this->pdf->AddPage();
            }
            else
            {
                // Draw a line above the next row.
                $this->pdf->Ln(0.1);
                $this->pdf->SetDrawColor(160, 160, 160);
                $this->emitLine('P', $this->pdf->GetY());
                $this->pdf->Ln(0.1);
            }

            // Get the item and its title.
            $item = $this->getItem($useElasticsearch, $result);
            $title = self::decode(ItemMetadata::getItemTitle($item));

            // Emit the title
            $this->pdf->SetFont('Arial', 'B', 8);
            $this->pdf->SetTextColor(64, 64, 64);
            $this->pdf->Cell(0, 0.18, "$title", self::BORDER, 0, '');
            $this->pdf->Ln(0.2);

            // Emit the item's thumbnail image.
            $hasImage = false;
            $imageTop = $this->pdf->GetY();
            $itemFiles = $item->Files;
            if (!$itemFiles)
            {
                $coverImageIdentifier = ItemPreview::getCoverImageIdentifier($item->id);
                $coverImageItem = empty($coverImageIdentifier) ? null : ItemMetadata::getItemFromIdentifier($coverImageIdentifier);
                $itemFiles = $coverImageItem ? $coverImageItem->Files : null;
            }

            if ($itemFiles)
            {
                // The item has an attachment.
                foreach ($itemFiles as $itemFile)
                {
                    if ($itemFile['mime_type'] == 'image/jpeg')
                    {
                        // Emit the image.
                        $hasImage = true;
                        $imageFileName = FILES_DIR . '/thumbnails/' . $itemFile['filename'];
                        $y = $this->pdf->GetY();
                        $this->pdf->Image($imageFileName, 0.8, $y + 0.05, null, 1.0);
                        $imageBottom = $this->pdf->GetY();
                        $imagePageNo = $this->pdf->PageNo();
                        $this->pdf->SetY($imageTop);
                        break;
                    }
                }
            }

            // Emit the item's element names and values.
            $this->emitItemElements($item, 3.0);

            // If the metadata did not display below the image on the same page, move Y to below the image.
            if ($hasImage && $this->pdf->GetY() < $imageBottom && $imagePageNo == $this->pdf->PageNo())
            {
                $this->pdf->SetY($imageBottom);
            }
        }
    }

    protected function getFilterText(SearchResultsTableView $searchResults)
    {
        // Get the search filters.
        $totalResults = $searchResults->getTotalResults();
        $filterText = "$totalResults search results for: ";
        $filters = $searchResults->emitSearchFiltersText();
        $parts = explode(PHP_EOL, $filters);
        foreach ($parts as $index => $part)
        {
            if (empty($part))
            {
                continue;
            }
            if ($index > 0)
            {
                $filterText .= ', ';
            }
            $filterText .= $part;
        }

        // Get the Sort and Items settings.
        $query = $searchResults->getQuery();
        $sortField = '';
        if (isset($query['sort']))
        {
            $sortField = $query['sort'];
        }
        $imagesOnly = false;
        if (isset($query['filter']))
        {
            $imagesOnly = $query['filter'] == '1';
        }
        if (empty($sortField))
            $sortField = __('relevance');
        $filterText .= ", sorted by $sortField";
        if ($imagesOnly)
        {
            $filterText .= ", only items with images";
        }
        return $filterText;
    }

    protected function getItem($useElasticsearch, $result)
    {
        if ($useElasticsearch)
        {
            $item = ItemMetadata::getItemFromId($result['_source']['item']['id']);
        }
        else
        {
            $item = $result;
        }
        return $item;
    }

    protected function initializeReport($orientation, $item = null)
    {
        $this->pdf = new FPDFExtended($orientation, 'in', 'letter');

        // Replace {nb} in the footer with the page number.
        $this->pdf->AliasNbPages();

        // Set the margins.
        $this->pdf->SetTopMargin(0.75);
        $this->pdf->SetLeftMargin(0.75);
        $this->pdf->SetRightMargin(0.75);

        // Add the first page. Other pages are added automatically.
        $this->pdf->AddPage();

        // Emit the organization name and item URL at the top of the page, with a line under.
        $this->pdf->SetFont('Arial','',10);
        $this->pdf->SetTextColor(80, 80, 80);
        $this->pdf->Cell(0, 0.2, get_option('site_title') . ' ' . __('Digital Archive'), self::BORDER);

        // Emit a link to the item.
        if ($item)
        {
            $this->pdf->SetFont('', 'U', 9);
            $this->pdf->AddLink();
            $url = WEB_ROOT . '/items/show/' . $item->id;
            $this->pdf->Cell(0, 0.2, $url, self::BORDER, 0, 'R');
        }

        // Emit a line, followed by spacing, under the header.
        $this->emitLine($orientation, 1.1);
        $this->pdf->Ln(0.5);
    }
}