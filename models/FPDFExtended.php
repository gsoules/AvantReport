<?php

class FPDFExtended extends FPDFBase
{
    function Footer()
    {
        $this->SetTextColor(80, 80, 80);
        $this->SetY(-0.5);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 0, date('n/j/Y'));
        $this->Cell(0, 0, 'Page '.$this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    var $widths;
    var $aligns;

    function SetWidths($w)
    {
        //Set the array of column widths
        $this->widths = $w;
    }

    function SetAligns($a)
    {
        //Set the array of column alignments
        $this->aligns = $a;
    }

    function Row($data, $indent, $header)
    {
        //Calculate the height of the row
        $nb = 0;
        for ($i = 0; $i < count($data); $i++)
        {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = 0.2 * $nb;

        //Issue a page break first if needed
        if ($this->CheckPageBreak($h))
            $this->Row($header, $indent, null);

        //Draw the cells of the row
        for ($i = 0; $i < count($data); $i++)
        {
            $w = $this->widths[$i];

            //Save the current position
            $x = $this->GetX();
            if ($i == 0)
            {
                $x += $indent;
                $this->SetX($x);
            }
            $y = $this->GetY();

            // Draw a border that has the height of the tallest cell.
            $this->Rect($x, $y, $w, $h);

            // Print the text with no border since it would be the height of this cell.
            $this->MultiCell($w, 0.18, $data[$i], 0, 'L');

            // Put the position to the right of the cell
            $this->SetXY($x + $w, $y);
        }

        //Go to the next line
        $this->Ln($h);
    }

    function CheckPageBreak($h)
    {
        //If the height h would cause an overflow, add a new page immediately
        if ($this->GetY() + $h > $this->PageBreakTrigger)
        {
            $this->AddPage($this->CurOrientation);
            return true;
        }
        return false;
    }

    function NbLines($w, $txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw =& $this->CurrentFont['cw'];
        if ($w == 0)
        {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n")
        {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb)
        {
            $c = $s[$i];
            if ($c == "\n")
            {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
            {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax)
            {
                if ($sep == -1)
                {
                    if ($i == $j)
                    {
                        $i++;
                    }
                }
                else
                {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
            {
                $i++;
            }
        }
        return $nl;
    }
}