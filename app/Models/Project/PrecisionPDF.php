<?php

namespace App\Models\Project;

use App\Exceptions\AnalysisException;
use App\Services\Analysis\Report\PdfComponentsAccessTrait;
use App\Services\Currency\CurrencyConverter;
use App\Services\Filesystems;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use TCPDF;
use Illuminate\Support\Facades\File;
use TCPDF_STATIC;

class PrecisionPDF extends TCPDF
{
    use PdfComponentsAccessTrait;

    /**
     * @var float|int
     */
    public $__widthAddition;

    /**
     * @var bool
     */
    public $__ignoreWidthAddition = false;

    public function Header()
    {
        // Set font
        $this->SetFont('helvetica', 'B', 20);
        // Title
        $this->Cell(0, 15, 'TCO Assessment', 0, false, 'R', 0, '', 0, false, 'T', 'B');
    }

    public function Footer()
    {
        if ($this->page == 1) {
            $html = '<p style="text-align:left; font-size:9pt"><span style="text-align:left; font-weight: bold; font-size:9pt">Notices</span><br/>This report is CONFIDENTIAL and is provided for informational purposes only.  ' .
                ' Precision IT, Inc. and/or ' . $this->project->provider . ' is not responsible for any damages related to the information in this report,' .
                ' which is provided “as is” without warranty of any kind, whether express, implied, or statutory.' .
                '  Nothing in this report creates any warranties or representations from Precision IT, Inc. and/or ' . $this->project->provider . ', its affiliates,' .
                ' suppliers or licensors.</p>';
            $this->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        } else {
            // todo revisit logo situation here

            $imagePath = 'images/logos/precisionLogo.png';
            if (isset($this->logo) && $this->logo != "" && Filesystems::imagesFilesystem()->exists($this->logo)) {
                $imagePath = $this->logo;
            }
            $path = str_replace('images/', 'storage/', $imagePath);
            $this->setFont('helvetica', '', 8);
            $this->image('http://localhost/' . str_replace(' ', '%20', $path), '', '', 0, 6);
            $this->Cell(0, 0, 'CONFIDENTIAL', 0, false, 'C');
            $this->Cell(0, 0, 'page ' . $this->getAliasNumPage() . '        ', 0, false, 'R');
        }
    }

    protected function copyLocal($path) {
        $dir = str_replace('images/', '', File::dirname($path));
        $tempLogoPath = storage_path('app/public/' . $dir);
        if (config('app.debug')) {
            logger("Creating temp path $tempLogoPath");
        }
        if (!File::exists($tempLogoPath)) {
            File::ensureDirectoryExists($tempLogoPath);
        }
        $fileName = File::basename($path);
        $localPath = "$tempLogoPath/$fileName";
        if (File::exists($localPath)) {
            File::delete($localPath);
        }

        if (config('app.debug')) {
            logger("Attempting to copy file to $localPath from images filesystem");
        }

        try {
            $fileContents = Filesystems::imagesFilesystem()->get($path);
            File::put($localPath, $fileContents);
        } catch (FileNotFoundException $e) {
            logger()->error("image file not found $path");
            logger()->error($e->getMessage());
        }
        return $localPath;
    }

    public function tcoCostRow($title, $width, $height, $var, $ignoreExisting, $environments)
    {
        $hasValue = false;
        foreach ($environments as $environment) {
            if ($environment->is_existing && $ignoreExisting)
                continue;
            if ($environment[$var] != 0) {
                $hasValue = true;
                break;
            }
        }
        //If there is no value in any environment, we can ignore this line.
        if (!$hasValue)
            return;
        $pageHeight = 260;
        if ($this->GetY() > $pageHeight) {
            $this->AddPage();
        }
        $this->MultiCell(60, $height, $title, 1, 'L', 0, 0);
        foreach ($environments as $environment) {
            $this->MultiCell($width, $height, (($environment->is_existing && $ignoreExisting) || $environment[$var] == 0) ? "N/A" : CurrencyConverter::convertAndFormat(round($environment[$var])), 1, 'C', 0, 0);
        }
        $this->Ln();
    }

    public function tcoChassisRow($title, $width, $height, $var, $ignoreExisting, $environments, $support_years = 1)
    {
        $hasValue = false;
        foreach ($environments as $environment) {
            if (isset($environment->analysis) && isset($environment->analysis->interchassisResult) && count($environment->analysis->interchassisResult->interconnect_chassis_list) > 0) {
                $hasValue = true;
                break;
            }
        }
        //If there is no value in any environment, we can ignore this line.
        if (!$hasValue)
            return;
        $pageHeight = 260;
        if ($this->GetY() > $pageHeight) {
            $this->AddPage();
        }
        $this->MultiCell(60, $height, $title, 1, 'L', 0, 0);
        foreach ($environments as $environment) {
            if (isset($environment->analysis) && isset($environment->analysis->interchassisResult) && count($environment->analysis->interchassisResult->interconnect_chassis_list) > 0) {
                $this->MultiCell($width, $height, (($environment->is_existing && $ignoreExisting) || $environment->analysis->interchassisResult->$var == 0) ? "N/A" : CurrencyConverter::convertAndFormat(round($environment->analysis->interchassisResult->$var * $support_years)), 1, 'C', 0, 0);
            } else {
                $this->MultiCell($width, $height, 'N/A', 1, 'C', 0, 0);
            }

        }
        $this->Ln();
    }

    public function printSavingsTableLine($exist, $targ, $name)
    {
        $startX = $this->GetX();
        $startY = $this->GetY();
        $diff = $exist - $targ;
        $width = 32;
        //   if($exist == 0 && $diff == 0 && $targ == 0)
        //      return;
        $singleLine = 5;
        $height = $this->MultiCell(62, $singleLine, $name, 0, 'L', 0, 0);
        $this->MultiCell($width, $singleLine, $exist > 0 ? CurrencyConverter::convertAndFormat(round($exist)) : 'N/A', 0, 'C', 0, 0);
        $this->MultiCell($width, $singleLine, $targ > 0 ? CurrencyConverter::convertAndFormat(round($targ)) : 'N/A', 0, 'C', 0, 0);
        if ($exist == 0 && $targ > 0) {
            $this->MultiCell($width, $singleLine, '(' . CurrencyConverter::convertAndFormat(abs(round($exist - $targ))) . ')', 0, 'C', 0, 0);
            $this->MultiCell($width, $singleLine, 'N/A', 0, 'C', 0, 0);
        } elseif ($diff < 0) {
            $this->MultiCell($width, $singleLine, '(' . CurrencyConverter::convertAndFormat(abs(round($exist - $targ))) . ')', 0, 'C', 0, 0);
            $this->MultiCell($width, $singleLine, '(' . round($exist ? (abs(round((1 - $targ / $exist) * 100))) : 100) . '%)', 0, 'C', 0, 0);
        } elseif ($targ == 0 && $exist == 0) {
            $this->MultiCell($width, $singleLine, "N/A", 0, 'C', 0, 0);
            $this->MultiCell($width, $singleLine, "N/A", 0, 'C', 0, 0);
        } else {
            $this->MultiCell($width, $singleLine,  CurrencyConverter::convertAndFormat(round($exist - $targ)), 0, 'C', 0, 0);
            $this->MultiCell($width, $singleLine, round($exist ? (round((1 - $targ / $exist) * 100)) : 100) . "%", 0, 'C', 0, 0);
        }
        $this->SetXY($startX, $startY);
        $this->MultiCell(62, $height * $singleLine + 1, '', 1, 'L', 0, 0);
        $this->MultiCell($width, $height * $singleLine + 1, '', 1, 'C', 0, 0);
        $this->MultiCell($width, $height * $singleLine + 1, '', 1, 'C', 0, 0);
        $this->MultiCell($width, $height * $singleLine + 1, '', 1, 'C', 0, 0);
        $this->MultiCell($width, $height * $singleLine + 1, '', 1, 'C', 0, 0);
        $this->Ln();
    }

    public function fillColor($colors)
    {
        $this->SetFillColor($colors[0], $colors[1], $colors[2]);
    }

    public function textColor($colors)
    {
        $this->SetTextColor($colors[0], $colors[1], $colors[2]);
    }

    /**
     * @param $header
     * @param $environments
     * @return $this
     */
    public function resultTables($environments)
    {
        /** @var Environment|false $existingEnvironment */
        $existingEnvironment = $environments[0] ?? false;
        if (!$existingEnvironment) {
            throw new AnalysisException("No existing env!");
        }
        foreach ($environments as $index => $environment) {
            if ($environment->is_existing || $index == 0 || $environment->isCloud()) {
                continue;
            }

            $this->pdfConsolidation($existingEnvironment->getExistingEnvironmentType())->drawConsolidation($this, $environment, $existingEnvironment);
        }

        return $this;
    }

    /**
     * Stands for "IGNORE WIDTHADDITION MULTICELL"
     * @param $w
     * @param $h
     * @param $txt
     * @param int $border
     * @param string $align
     * @param bool $fill
     * @param int $ln
     * @param string $x
     * @param string $y
     * @param bool $reseth
     * @param int $stretch
     * @param bool $ishtml
     * @param bool $autopadding
     * @param int $maxh
     * @param string $valign
     * @param bool $fitcell
     * @return int
     */
    public function iwMultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false) {
        $orig = $this->__ignoreWidthAddition;
        $this->__ignoreWidthAddition = true;
        $ret = $this->MultiCell($w, $h, $txt, $border, $align, $fill, $ln, $x, $y, $reseth, $stretch, $ishtml, $autopadding, $maxh, $valign, $fitcell);
        $this->__ignoreWidthAddition = $orig;
        return $ret;
    }


    /**
     * This method allows printing text with line breaks.
     * They can be automatic (as soon as the text reaches the right border of the cell) or explicit (via the \n character). As many cells as necessary are output, one below the other.<br />
     * Text can be aligned, centered or justified. The cell block can be framed and the background painted.
     * @param $w (float) Width of cells. If 0, they extend up to the right margin of the page.
     * @param $h (float) Cell minimum height. The cell extends automatically if needed.
     * @param $txt (string) String to print
     * @param $border (mixed) Indicates if borders must be drawn around the cell. The value can be a number:<ul><li>0: no border (default)</li><li>1: frame</li></ul> or a string containing some or all of the following characters (in any order):<ul><li>L: left</li><li>T: top</li><li>R: right</li><li>B: bottom</li></ul> or an array of line styles for each border group - for example: array('LTRB' => array('width' => 2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)))
     * @param $align (string) Allows to center or align the text. Possible values are:<ul><li>L or empty string: left align</li><li>C: center</li><li>R: right align</li><li>J: justification (default value when $ishtml=false)</li></ul>
     * @param $fill (boolean) Indicates if the cell background must be painted (true) or transparent (false).
     * @param $ln (int) Indicates where the current position should go after the call. Possible values are:<ul><li>0: to the right</li><li>1: to the beginning of the next line [DEFAULT]</li><li>2: below</li></ul>
     * @param $x (float) x position in user units
     * @param $y (float) y position in user units
     * @param $reseth (boolean) if true reset the last cell height (default true).
     * @param $stretch (int) font stretch mode: <ul><li>0 = disabled</li><li>1 = horizontal scaling only if text is larger than cell width</li><li>2 = forced horizontal scaling to fit cell width</li><li>3 = character spacing only if text is larger than cell width</li><li>4 = forced character spacing to fit cell width</li></ul> General font stretching and scaling values will be preserved when possible.
     * @param $ishtml (boolean) INTERNAL USE ONLY -- set to true if $txt is HTML content (default = false). Never set this parameter to true, use instead writeHTMLCell() or writeHTML() methods.
     * @param $autopadding (boolean) if true, uses internal padding and automatically adjust it to account for line width.
     * @param $maxh (float) maximum height. It should be >= $h and less then remaining space to the bottom of the page, or 0 for disable this feature. This feature works only when $ishtml=false.
     * @param $valign (string) Vertical alignment of text (requires $maxh = $h > 0). Possible values are:<ul><li>T: TOP</li><li>M: middle</li><li>B: bottom</li></ul>. This feature works only when $ishtml=false and the cell must fit in a single page.
     * @param $fitcell (boolean) if true attempt to fit all the text within the cell by reducing the font size (do not work in HTML mode). $maxh must be greater than 0 and equal to $h.
     * @return int Return the number of cells or 1 for html mode.
     * @public
     * @since 1.3
     * @see SetFont(), SetDrawColor(), SetFillColor(), SetTextColor(), SetLineWidth(), Cell(), Write(), SetAutoPageBreak()
     */
    public function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false) {
        if (isset($this->__widthAddition) && $this->__widthAddition && !$this->__ignoreWidthAddition) {
            $w += floatval($this->__widthAddition);
        }
        $prev_cell_margin = $this->cell_margin;
        $prev_cell_padding = $this->cell_padding;
        // adjust internal padding
        $this->adjustCellPadding($border);
        $mc_padding = $this->cell_padding;
        $mc_margin = $this->cell_margin;
        $this->cell_padding['T'] = 0;
        $this->cell_padding['B'] = 0;
        $this->setCellMargins(0, 0, 0, 0);
        if (TCPDF_STATIC::empty_string($this->lasth) OR $reseth) {
            // reset row height
            $this->resetLastH();
        }
        if (!TCPDF_STATIC::empty_string($y)) {
            $this->SetY($y);
        } else {
            $y = $this->GetY();
        }
        $resth = 0;
        if (($h > 0) AND $this->inPageBody() AND (($y + $h + $mc_margin['T'] + $mc_margin['B']) > $this->PageBreakTrigger)) {
            // spit cell in more pages/columns
            $newh = ($this->PageBreakTrigger - $y);
            $resth = ($h - $newh); // cell to be printed on the next page/column
            $h = $newh;
        }
        // get current page number
        $startpage = $this->page;
        // get current column
        $startcolumn = $this->current_column;
        if (!TCPDF_STATIC::empty_string($x)) {
            $this->SetX($x);
        } else {
            $x = $this->GetX();
        }
        // check page for no-write regions and adapt page margins if necessary
        list($x, $y) = $this->checkPageRegions(0, $x, $y);
        // apply margins
        $oy = $y + $mc_margin['T'];
        if ($this->rtl) {
            $ox = ($this->w - $x - $mc_margin['R']);
        } else {
            $ox = ($x + $mc_margin['L']);
        }
        $this->x = $ox;
        $this->y = $oy;
        // set width
        if (TCPDF_STATIC::empty_string($w) OR ($w <= 0)) {
            if ($this->rtl) {
                $w = ($this->x - $this->lMargin - $mc_margin['L']);
            } else {
                $w = ($this->w - $this->x - $this->rMargin - $mc_margin['R']);
            }
        }
        // store original margin values
        $lMargin = $this->lMargin;
        $rMargin = $this->rMargin;
        if ($this->rtl) {
            $this->rMargin = ($this->w - $this->x);
            $this->lMargin = ($this->x - $w);
        } else {
            $this->lMargin = ($this->x);
            $this->rMargin = ($this->w - $this->x - $w);
        }
        $this->clMargin = $this->lMargin;
        $this->crMargin = $this->rMargin;
        if ($autopadding) {
            // add top padding
            $this->y += $mc_padding['T'];
        }
        if ($ishtml) { // ******* Write HTML text
            $this->writeHTML($txt, true, false, $reseth, true, $align);
            $nl = 1;
        } else { // ******* Write simple text
            $prev_FontSizePt = $this->FontSizePt;
            if ($fitcell) {
                // ajust height values
                $tobottom = ($this->h - $this->y - $this->bMargin - $this->cell_padding['T'] - $this->cell_padding['B']);
                $h = $maxh = max(min($h, $tobottom), min($maxh, $tobottom));
            }
            // vertical alignment
            if ($maxh > 0) {
                // get text height
                $text_height = $this->getStringHeight($w, $txt, $reseth, $autopadding, $mc_padding, $border);
                if ($fitcell AND ($text_height > $maxh) AND ($this->FontSizePt > 1)) {
                    // try to reduce font size to fit text on cell (use a quick search algorithm)
                    $fmin = 1;
                    $fmax = $this->FontSizePt;
                    $diff_epsilon = (1 / $this->k); // one point (min resolution)
                    $maxit = (2 * min(100, max(10, intval($fmax)))); // max number of iterations
                    while ($maxit >= 0) {
                        $fmid = (($fmax + $fmin) / 2);
                        $this->SetFontSize($fmid, false);
                        $this->resetLastH();
                        $text_height = $this->getStringHeight($w, $txt, $reseth, $autopadding, $mc_padding, $border);
                        $diff = ($maxh - $text_height);
                        if ($diff >= 0) {
                            if ($diff <= $diff_epsilon) {
                                break;
                            }
                            $fmin = $fmid;
                        } else {
                            $fmax = $fmid;
                        }
                        --$maxit;
                    }
                    if ($maxit < 0) {
                        // premature exit, we get the minimum font value to fit the cell
                        $this->SetFontSize($fmin);
                        $this->resetLastH();
                        $text_height = $this->getStringHeight($w, $txt, $reseth, $autopadding, $mc_padding, $border);
                    } else {
                        $this->SetFontSize($fmid);
                        $this->resetLastH();
                    }
                }
                if ($text_height < $maxh) {
                    if ($valign == 'M') {
                        // text vertically centered
                        $this->y += (($maxh - $text_height) / 2);
                    } elseif ($valign == 'B') {
                        // text vertically aligned on bottom
                        $this->y += ($maxh - $text_height);
                    }
                }
            }
            $nl = $this->Write($this->lasth, $txt, '', 0, $align, true, $stretch, false, true, $maxh, 0, $mc_margin);
            if ($fitcell) {
                // restore font size
                $this->SetFontSize($prev_FontSizePt);
            }
        }
        if ($autopadding) {
            // add bottom padding
            $this->y += $mc_padding['B'];
        }
        // Get end-of-text Y position
        $currentY = $this->y;
        // get latest page number
        $endpage = $this->page;
        if ($resth > 0) {
            $skip = ($endpage - $startpage);
            $tmpresth = $resth;
            while ($tmpresth > 0) {
                if ($skip <= 0) {
                    // add a page (or trig AcceptPageBreak() for multicolumn mode)
                    $this->checkPageBreak($this->PageBreakTrigger + 1);
                }
                if ($this->num_columns > 1) {
                    $tmpresth -= ($this->h - $this->y - $this->bMargin);
                } else {
                    $tmpresth -= ($this->h - $this->tMargin - $this->bMargin);
                }
                --$skip;
            }
            $currentY = $this->y;
            $endpage = $this->page;
        }
        // get latest column
        $endcolumn = $this->current_column;
        if ($this->num_columns == 0) {
            $this->num_columns = 1;
        }
        // disable page regions check
        $check_page_regions = $this->check_page_regions;
        $this->check_page_regions = false;
        // get border modes
        $border_start = TCPDF_STATIC::getBorderMode($border, $position='start', $this->opencell);
        $border_end = TCPDF_STATIC::getBorderMode($border, $position='end', $this->opencell);
        $border_middle = TCPDF_STATIC::getBorderMode($border, $position='middle', $this->opencell);
        // design borders around HTML cells.
        for ($page = $startpage; $page <= $endpage; ++$page) { // for each page
            $ccode = '';
            $this->setPage($page);
            if ($this->num_columns < 2) {
                // single-column mode
                $this->SetX($x);
                $this->y = $this->tMargin;
            }
            // account for margin changes
            if ($page > $startpage) {
                if (($this->rtl) AND ($this->pagedim[$page]['orm'] != $this->pagedim[$startpage]['orm'])) {
                    $this->x -= ($this->pagedim[$page]['orm'] - $this->pagedim[$startpage]['orm']);
                } elseif ((!$this->rtl) AND ($this->pagedim[$page]['olm'] != $this->pagedim[$startpage]['olm'])) {
                    $this->x += ($this->pagedim[$page]['olm'] - $this->pagedim[$startpage]['olm']);
                }
            }
            if ($startpage == $endpage) {
                // single page
                for ($column = $startcolumn; $column <= $endcolumn; ++$column) { // for each column
                    if ($column != $this->current_column) {
                        $this->selectColumn($column);
                    }
                    if ($this->rtl) {
                        $this->x -= $mc_margin['R'];
                    } else {
                        $this->x += $mc_margin['L'];
                    }
                    if ($startcolumn == $endcolumn) { // single column
                        $cborder = $border;
                        $h = max($h, ($currentY - $oy));
                        $this->y = $oy;
                    } elseif ($column == $startcolumn) { // first column
                        $cborder = $border_start;
                        $this->y = $oy;
                        $h = $this->h - $this->y - $this->bMargin;
                    } elseif ($column == $endcolumn) { // end column
                        $cborder = $border_end;
                        $h = $currentY - $this->y;
                        if ($resth > $h) {
                            $h = $resth;
                        }
                    } else { // middle column
                        $cborder = $border_middle;
                        $h = $this->h - $this->y - $this->bMargin;
                        $resth -= $h;
                    }
                    $ccode .= $this->getCellCode($w, $h, '', $cborder, 1, '', $fill, '', 0, true)."\n";
                } // end for each column
            } elseif ($page == $startpage) { // first page
                for ($column = $startcolumn; $column < $this->num_columns; ++$column) { // for each column
                    if ($column != $this->current_column) {
                        $this->selectColumn($column);
                    }
                    if ($this->rtl) {
                        $this->x -= $mc_margin['R'];
                    } else {
                        $this->x += $mc_margin['L'];
                    }
                    if ($column == $startcolumn) { // first column
                        $cborder = $border_start;
                        $this->y = $oy;
                        $h = $this->h - $this->y - $this->bMargin;
                    } else { // middle column
                        $cborder = $border_middle;
                        $h = $this->h - $this->y - $this->bMargin;
                        $resth -= $h;
                    }
                    $ccode .= $this->getCellCode($w, $h, '', $cborder, 1, '', $fill, '', 0, true)."\n";
                } // end for each column
            } elseif ($page == $endpage) { // last page
                for ($column = 0; $column <= $endcolumn; ++$column) { // for each column
                    if ($column != $this->current_column) {
                        $this->selectColumn($column);
                    }
                    if ($this->rtl) {
                        $this->x -= $mc_margin['R'];
                    } else {
                        $this->x += $mc_margin['L'];
                    }
                    if ($column == $endcolumn) {
                        // end column
                        $cborder = $border_end;
                        $h = $currentY - $this->y;
                        if ($resth > $h) {
                            $h = $resth;
                        }
                    } else {
                        // middle column
                        $cborder = $border_middle;
                        $h = $this->h - $this->y - $this->bMargin;
                        $resth -= $h;
                    }
                    $ccode .= $this->getCellCode($w, $h, '', $cborder, 1, '', $fill, '', 0, true)."\n";
                } // end for each column
            } else { // middle page
                for ($column = 0; $column < $this->num_columns; ++$column) { // for each column
                    $this->selectColumn($column);
                    if ($this->rtl) {
                        $this->x -= $mc_margin['R'];
                    } else {
                        $this->x += $mc_margin['L'];
                    }
                    $cborder = $border_middle;
                    $h = $this->h - $this->y - $this->bMargin;
                    $resth -= $h;
                    $ccode .= $this->getCellCode($w, $h, '', $cborder, 1, '', $fill, '', 0, true)."\n";
                } // end for each column
            }
            if ($cborder OR $fill) {
                $offsetlen = strlen($ccode);
                // draw border and fill
                if ($this->inxobj) {
                    // we are inside an XObject template
                    if (end($this->xobjects[$this->xobjid]['transfmrk']) !== false) {
                        $pagemarkkey = key($this->xobjects[$this->xobjid]['transfmrk']);
                        $pagemark = $this->xobjects[$this->xobjid]['transfmrk'][$pagemarkkey];
                        $this->xobjects[$this->xobjid]['transfmrk'][$pagemarkkey] += $offsetlen;
                    } else {
                        $pagemark = $this->xobjects[$this->xobjid]['intmrk'];
                        $this->xobjects[$this->xobjid]['intmrk'] += $offsetlen;
                    }
                    $pagebuff = $this->xobjects[$this->xobjid]['outdata'];
                    $pstart = substr($pagebuff, 0, $pagemark);
                    $pend = substr($pagebuff, $pagemark);
                    $this->xobjects[$this->xobjid]['outdata'] = $pstart.$ccode.$pend;
                } else {
                    if (end($this->transfmrk[$this->page]) !== false) {
                        $pagemarkkey = key($this->transfmrk[$this->page]);
                        $pagemark = $this->transfmrk[$this->page][$pagemarkkey];
                        $this->transfmrk[$this->page][$pagemarkkey] += $offsetlen;
                    } elseif ($this->InFooter) {
                        $pagemark = $this->footerpos[$this->page];
                        $this->footerpos[$this->page] += $offsetlen;
                    } else {
                        $pagemark = $this->intmrk[$this->page];
                        $this->intmrk[$this->page] += $offsetlen;
                    }
                    $pagebuff = $this->getPageBuffer($this->page);
                    $pstart = substr($pagebuff, 0, $pagemark);
                    $pend = substr($pagebuff, $pagemark);
                    $this->setPageBuffer($this->page, $pstart.$ccode.$pend);
                }
            }
        } // end for each page
        // restore page regions check
        $this->check_page_regions = $check_page_regions;
        // Get end-of-cell Y position
        $currentY = $this->GetY();
        // restore previous values
        if ($this->num_columns > 1) {
            $this->selectColumn();
        } else {
            // restore original margins
            $this->lMargin = $lMargin;
            $this->rMargin = $rMargin;
            if ($this->page > $startpage) {
                // check for margin variations between pages (i.e. booklet mode)
                $dl = ($this->pagedim[$this->page]['olm'] - $this->pagedim[$startpage]['olm']);
                $dr = ($this->pagedim[$this->page]['orm'] - $this->pagedim[$startpage]['orm']);
                if (($dl != 0) OR ($dr != 0)) {
                    $this->lMargin += $dl;
                    $this->rMargin += $dr;
                }
            }
        }
        if ($ln > 0) {
            //Go to the beginning of the next line
            $this->SetY($currentY + $mc_margin['B']);
            if ($ln == 2) {
                $this->SetX($x + $w + $mc_margin['L'] + $mc_margin['R']);
            }
        } else {
            // go left or right by case
            $this->setPage($startpage);
            $this->y = $y;
            $this->SetX($x + $w + $mc_margin['L'] + $mc_margin['R']);
        }
        $this->setContentMark();
        $this->cell_padding = $prev_cell_padding;
        $this->cell_margin = $prev_cell_margin;
        $this->clMargin = $this->lMargin;
        $this->crMargin = $this->rMargin;
        return $nl;
    }
}
