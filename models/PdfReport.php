<?php

class PDF extends FPDF
{
    function Footer()
    {
        $this->SetTextColor(80, 80, 80);
        $this->SetY(-0.5);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 0, date('n/j/Y'));
        $this->Cell(0, 0, 'Page '.$this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

class PdfReport
{
    // For development/debugging, set the border to 1 so you can see individual cells.
    const BORDER = 0;

    public function createReportForItem($item)
    {
        $pdf = new PDF('P', 'in', 'letter');

        // Replace {nb} in the footer with the page number.
        $pdf->AliasNbPages();

        // Set the margins.
        $pdf->SetTopMargin(0.75);
        $pdf->SetLeftMargin(0.75);
        $pdf->SetRightMargin(0.75);

        // Add the first page. Other pages are added automatically.
        $pdf->AddPage();

        // Emit the organization name and item URL at the top of the page, with a line under.
        $url = WEB_ROOT . '/items/show/' . $item->id;
        $pdf->SetFont('Arial','',10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 0.2, get_option('site_title') . ' ' . __('Digital Archive'), self::BORDER);
        $pdf->SetFont('','U');
        $pdf->AddLink();
        $pdf->Cell(0, 0.2, $url, self::BORDER, 0, 'R');
        $pdf->Line(0.8, 1.1, 7.70, 1.1);

        // Emit the item title.
        $pdf->Ln(0.2);
        $pdf->SetFont('Arial','B',13);
        $title = ItemMetadata::getItemTitle($item);
        $pdf->Ln(0.4);
        $pdf->Cell(0, 0.2, self::decode($title), self::BORDER);
        $pdf->Ln(0.4);

        // Switch to the font that subsequent text fill use.
        $pdf->SetFont('Arial','',10);

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
                $pdf->Image($imageFileName);
            }
            else
            {
                // The attachment is not an image. Just emit its file name.
                $pdf->Cell(1.0, 0.2, "Attachment:", self::BORDER, 0, 'R');
                $pdf->SetFont('', 'U');
                $pdf->AddLink();
                $attachmentUrl = WEB_DIR . '/files/original/' . $primaryImage['filename'];
                $pdf->Cell(0, 0.2, $attachmentUrl, self::BORDER);
                $pdf->SetFont('', '');
            }
            $pdf->Ln(0.4);
        }

        // Get the item's elements.
        $elementTexts = get_db()->getTable('ElementText')->findByRecord($item);
        $privateElementsData = CommonConfig::getOptionDataForPrivateElements();
        $skipPrivateElements = empty(current_user());
        $previousName = '';

        // Loop over each element.
        foreach ($elementTexts as $elementText)
        {
            $name = ItemMetadata::getElementNameFromId($elementText['element_id']);
            $isPrivateElement = in_array($name,$privateElementsData);

            // Skip private elements if no user is logged in.
            if ($isPrivateElement && $skipPrivateElements)
                continue;

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
                $pdf->SetFont('', 'I');
                $pdf->SetTextColor(120, 120, 120);
            }
            else
            {
                $pdf->SetTextColor(0, 0, 0);
            }
            $pdf->Cell(1.0, 0.18, $name, self::BORDER, 0, 'R');

            // Emit the element value with normal black text, left justified. Long values will wrap in their multicell.
            $pdf->SetFont('', '');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(6.0, 0.18, self::decode($elementText['text']), self::BORDER);
            $pdf->Ln(0.08);
        }

        // Prompt the user to save the file.
        $pdf->Output(__('item-') . ItemMetadata::getItemIdentifier($item) . '.pdf', 'D');
    }

    public function createReportForSearchResults()
    {

    }

    protected static function decode($text)
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}