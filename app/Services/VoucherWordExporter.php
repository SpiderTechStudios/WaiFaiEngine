<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class VoucherWordExporter
{
    public function filename(): string
    {
        return 'vouchers-'.now()->format('Ymd-His').'.docx';
    }

    /**
     * Build a real .docx (Office Open XML) document without extra dependencies.
     *
     * @param  Collection<int, Voucher>  $vouchers
     */
    public function export(Company $company, Collection $vouchers): string
    {
        $path = tempnam(sys_get_temp_dir(), 'waifai-vouchers-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the voucher document.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            throw new RuntimeException('Could not create the voucher document.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relationships());
        $zip->addFromString('word/document.xml', $this->document($company, $vouchers));
        $zip->close();

        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    /**
     * @param  Collection<int, Voucher>  $vouchers
     */
    private function document(Company $company, Collection $vouchers): string
    {
        $currency = (string) config('platform.currency', 'TZS');
        $generatedAt = now()->format('d M Y H:i');

        $header = $this->row([
            $this->cell('Code', true, 1600),
            $this->cell('Status', true, 1200),
            $this->cell('Package', true, 2200),
            $this->cell('Validity', true, 1500),
            $this->cell('Price', true, 1400),
            $this->cell('Router', true, 2600),
            $this->cell('Expires', true, 2400),
            $this->cell('Note', true, 2498),
        ], true);

        $rows = '';

        foreach ($vouchers as $voucher) {
            $plan = $voucher->internetPlan;
            $price = $plan?->price;

            $rows .= $this->row([
                $this->cell((string) $voucher->code, false, 1600, mono: true),
                $this->cell(ucfirst((string) $voucher->status), false, 1200),
                $this->cell($plan?->name ?? '—', false, 2200),
                $this->cell($this->validity($plan), false, 1500),
                $this->cell($price === null ? '—' : $currency.' '.number_format((float) $price), false, 1400),
                $this->cell($voucher->router?->name ?? '—', false, 2600),
                $this->cell($voucher->expires_at?->format('d M Y H:i') ?? 'Never', false, 2400),
                $this->cell((string) ($voucher->note ?? ''), false, 2498),
            ]);
        }

        $borders = '<w:tblBorders>'
            .$this->border('top')
            .$this->border('left')
            .$this->border('bottom')
            .$this->border('right')
            .$this->border('insideH')
            .$this->border('insideV')
            .'</w:tblBorders>';

        $table = '<w:tbl>'
            .'<w:tblPr><w:tblW w:w="15398" w:type="dxa"/><w:tblLayout w:type="fixed"/>'.$borders.'</w:tblPr>'
            .$header
            .$rows
            .'</w:tbl>';

        $body = $this->paragraph('Vouchers', ['bold' => true, 'size' => 32, 'align' => 'center'])
            .$this->paragraph((string) $company->name, ['bold' => true, 'size' => 24, 'align' => 'center', 'after' => 60])
            .$this->paragraph(
                'Generated '.$generatedAt.' • '.$vouchers->count().' voucher(s) • Currency: '.$currency,
                ['align' => 'center', 'after' => 240],
            )
            .$table
            .'<w:p/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body
            .'<w:sectPr>'
            .'<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
            .'<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="0" w:footer="0" w:gutter="0"/>'
            .'</w:sectPr>'
            .'</w:body></w:document>';
    }

    private function validity(?InternetPlan $plan): string
    {
        if (! $plan || ! $plan->duration) {
            return '—';
        }

        $unit = rtrim(strtolower((string) $plan->duration_unit), 's');
        $duration = (int) $plan->duration;

        return $duration.' '.$unit.($duration === 1 ? '' : 's');
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function row(array $cells, bool $header = false): string
    {
        return '<w:tr>'.($header ? '<w:trPr><w:tblHeader/></w:trPr>' : '').implode('', $cells).'</w:tr>';
    }

    private function cell(string $text, bool $header = false, int $width = 0, bool $mono = false): string
    {
        $widthXml = $width > 0 ? '<w:tcW w:w="'.$width.'" w:type="dxa"/>' : '';
        $shade = $header ? '<w:shd w:val="clear" w:color="auto" w:fill="E8F1F2"/>' : '';
        $font = $mono ? '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>' : '';
        $bold = $header || $mono ? '<w:b/>' : '';

        return '<w:tc><w:tcPr>'.$widthXml.$shade.'<w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:spacing w:after="0"/></w:pPr>'
            .'<w:r><w:rPr>'.$font.$bold.'<w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr>'
            .'<w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r></w:p></w:tc>';
    }

    /**
     * @param  array{bold?: bool, size?: int, align?: string, after?: int}  $options
     */
    private function paragraph(string $text, array $options = []): string
    {
        $bold = ! empty($options['bold']) ? '<w:b/>' : '';
        $size = isset($options['size'])
            ? '<w:sz w:val="'.$options['size'].'"/><w:szCs w:val="'.$options['size'].'"/>'
            : '';
        $align = isset($options['align']) ? '<w:jc w:val="'.$options['align'].'"/>' : '';
        $spacing = '<w:spacing w:after="'.($options['after'] ?? 0).'"/>';

        return '<w:p><w:pPr>'.$spacing.$align.'</w:pPr>'
            .'<w:r><w:rPr>'.$bold.$size.'</w:rPr><w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r></w:p>';
    }

    private function border(string $side): string
    {
        return '<w:'.$side.' w:val="single" w:sz="4" w:space="0" w:color="B7C4C6"/>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';
    }

    private function relationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }
}
