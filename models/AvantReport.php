<?php

class AvantReport
{
    // For development/debugging, set the border to 1 so you can see individual cells.
    const BORDER = 1;

    protected $pdf;

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
    public function createReportForSearchResults($searchResults, $findUrl)
    {
        $layoutId = $searchResults->getSelectedLayoutId();
        $this->initializeReport($layoutId == 1 ? 'P' : 'L');

        // Emit the search filters and selector bar options.
        $this->pdf->SetFont('Arial','B',10);
        $filterText = $this->getFilterText($searchResults);
        $this->pdf->Cell(0, 0.2, $filterText, self::BORDER, 0, '');
        $this->pdf->Ln(0.5);

        // Switch to the font that subsequent text will use.
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('Arial', '', 8);

        // Emit the result rows.
        $useElasticsearch = $searchResults->useElasticsearch();
        $results = $searchResults->getResults();
        $layoutId = $searchResults->getSelectedLayoutId();
        if ($layoutId == 1)
            $this->emitRowsForDetailLayout($results, $useElasticsearch);
        else
            $this->emitRowsForCompressedLayout($layoutId, $results, $useElasticsearch);

        // Prompt the user to save the file.
        $this->downloadReport(__('search-') . '001');
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

    protected function emitItemElements($item, $leftColumnWidth = 1.0)
    {
        $elementTexts = get_db()->getTable('ElementText')->findByRecord($item);
        $privateElementsData = CommonConfig::getOptionDataForPrivateElements();
        $skipPrivateElements = empty(current_user());
        $previousName = '';

        // Loop over each element.
        foreach ($elementTexts as $elementText)
        {
            $name = ItemMetadata::getElementNameFromId($elementText['element_id']);
            $isPrivateElement = in_array($name, $privateElementsData);

            // Skip private elements if no user is logged in.
            if ($isPrivateElement && $skipPrivateElements)
            {
                continue;
            }

            // Put a colon after the element name. If the element has multiple values, only show the name on the first.
            if ($name == $previousName)
            {
                $name = '';
            }
            else
            {
                $previousName = $name;
                $name .= ':';
            }

            // Emit the element name in the left column, right justified.
            // Show names of private elements in gray italics.
            if ($isPrivateElement)
            {
                $this->pdf->SetFont('Arial', 'I', 8);
                $this->pdf->SetTextColor(120, 120, 120);
            }
            else
            {
                $this->pdf->SetFont('Arial', '', 8);
                $this->pdf->SetTextColor(0, 0, 0);
            }
            $this->pdf->Cell($leftColumnWidth, 0.18, $name, self::BORDER, 0, 'R');

            // Emit the element value with normal black text, left justified. Long values will wrap in their multicell.
            $this->pdf->SetFont('Arial', '', 8);
            $this->pdf->SetTextColor(0, 0, 0);
            $rightColumnWidth = 7.0 - $leftColumnWidth;
            $this->pdf->MultiCell($rightColumnWidth, 0.18, self::decode($elementText['text']), self::BORDER);
            $y = $this->pdf->GetY();
            $this->pdf->Ln(0.02);
        }
    }

    protected function emitRowsForCompressedLayout($layoutId, $results, $useElasticsearch)
    {
        foreach ($results as $result)
        {
            $identifier = $result['_source']['core-fields']['identifier'][0];
            $title = $result['_source']['core-fields']['title'][0];
            $title = self::decode($title);
            $this->pdf->Cell(0, 0.18, "$identifier - $title", self::BORDER, 0, '');
            $this->pdf->Ln(0.2);
        }
    }

    protected function emitRowsForDetailLayout($results, $useElasticsearch)
    {
        $firstItemOnPage = true;

        foreach ($results as $result)
        {
            if ($useElasticsearch)
            {
                $source = $result['_source'];
                $itemId = $source['item']['id'];
                $item = ItemMetadata::getItemFromId($itemId);
                $title = $source['core-fields']['title'][0];
            }
            else
            {
                $item = $result;
                $itemId = $item->id;
                $title = ItemMetadata::getItemTitle($item);
            }

            $title = self::decode($title);

            $y = $this->pdf->GetY();
            if ($y > 8.5)
            {
                $this->pdf->AddPage();
                $firstItemOnPage = true;
                $y = $this->pdf->GetY();
            }

            if (!$firstItemOnPage)
            {
                $this->pdf->Ln(0.1);
            }

            $this->pdf->SetFont('Arial', 'B', 8);
            $this->pdf->Cell(0, 0.18, "$title", self::BORDER, 0, '');
            $this->pdf->Ln(0.2);

            $imageTop = $this->pdf->GetY();
            $itemFiles = $item->Files;
            if (!$itemFiles)
            {
                $coverImageIdentifier = ItemPreview::getCoverImageIdentifier($itemId);
                $coverImageItem = empty($coverImageIdentifier) ? null : ItemMetadata::getItemFromIdentifier($coverImageIdentifier);
                $itemFiles = $coverImageItem->Files;
            }

            if ($itemFiles)
            {
                // The item has an attachment.
                foreach ($itemFiles as $itemFile)
                {
                    if ($itemFile['mime_type'] == 'image/jpeg')
                    {
                        // Emit the image.
                        $imageFileName = FILES_DIR . '/thumbnails/' . $itemFile['filename'];
                        $this->pdf->Image($imageFileName, 0.8, null, null, 1.0);
                        $imageBottom = $this->pdf->GetY();
                        $imagePageNo = $this->pdf->PageNo();
                        $this->pdf->SetY($imageTop);
                        break;
                    }
                }
            }

            $this->emitItemElements($item, 3.0);

            // If the metadata did not display below the image on the same page, move Y to below the image.
            if ($this->pdf->GetY() < $imageBottom && $imagePageNo == $this->pdf->PageNo())
            {
                $this->pdf->SetY($imageBottom);
            }

            $firstItemOnPage = false;
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
        $filterText .= ", sorted by $sortField";
        if ($imagesOnly)
        {
            $filterText .= ", only items with images";
        }
        return $filterText;
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

        // Emit a line under the header.
        $endLine = $orientation == 'P' ? 7.70 : 10.20;
        $this->pdf->Line(0.8, 1.1, $endLine, 1.1);
        $this->pdf->Ln(0.5);
    }
}