<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesReportExportService
{
    /**
     * @param  array<string, int|float>  $summary
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function excel(
        Carbon $start,
        Carbon $end,
        ?string $channel,
        array $summary,
        Collection $rows,
    ): string {
        $xmlRows = [
            $this->excelRow(['LAPORAN PENJUALAN KANZZA FROZEN FOOD'], true),
            $this->excelRow(['Periode', $start->format('d/m/Y').' - '.$end->format('d/m/Y')]),
            $this->excelRow(['Kanal', $this->channelLabel($channel)]),
            $this->excelRow([]),
            $this->excelRow(['RINGKASAN'], true),
            $this->excelRow(['Total Transaksi', $summary['total_transactions']], false, [false, true]),
            $this->excelRow(['Total Pendapatan', $summary['total_revenue']], false, [false, true]),
            $this->excelRow(['Total Item Terjual', $summary['total_items']], false, [false, true]),
            $this->excelRow(['Rata-rata Pesanan', $summary['average_order']], false, [false, true]),
            $this->excelRow([]),
            $this->excelRow([
                'No',
                'Nomor Pesanan',
                'Tanggal Bayar',
                'Kanal',
                'Customer/Kasir',
                'Metode Pembayaran',
                'Status Pesanan',
                'Jumlah Item',
                'Total',
            ], true),
        ];

        foreach ($rows->values() as $index => $row) {
            $xmlRows[] = $this->excelRow([
                $index + 1,
                $row['order_number'],
                $this->dateLabel($row['paid_at']),
                $this->channelLabel($row['channel']),
                $row['customer_name'],
                strtoupper((string) $row['payment_method']),
                $this->statusLabel((string) $row['order_status']),
                $row['total_quantity'],
                $row['grand_total'],
            ], false, [true, false, false, false, false, false, false, true, true]);
        }

        $body = implode('', $xmlRows);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            .'<Styles>'
            .'<Style ss:ID="Default"><Alignment ss:Vertical="Center"/>'
            .'<Font ss:FontName="Calibri" ss:Size="11"/></Style>'
            .'<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/>'
            .'<Interior ss:Color="#9B5EFF" ss:Pattern="Solid"/></Style>'
            .'<Style ss:ID="Number"><NumberFormat ss:Format="#,##0"/></Style>'
            .'</Styles>'
            .'<Worksheet ss:Name="Laporan Penjualan"><Table>'
            .'<Column ss:Width="40"/><Column ss:Width="145"/><Column ss:Width="125"/>'
            .'<Column ss:Width="75"/><Column ss:Width="145"/><Column ss:Width="105"/>'
            .'<Column ss:Width="105"/><Column ss:Width="80"/><Column ss:Width="110"/>'
            .$body
            .'</Table></Worksheet></Workbook>';
    }

    /**
     * @param  array<string, int|float>  $summary
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function pdf(
        Carbon $start,
        Carbon $end,
        ?string $channel,
        array $summary,
        Collection $rows,
    ): string {
        $lines = [
            'LAPORAN PENJUALAN KANZZA FROZEN FOOD',
            'Periode: '.$start->format('d/m/Y').' - '.$end->format('d/m/Y'),
            'Kanal: '.$this->channelLabel($channel),
            '',
            'Total Transaksi : '.$summary['total_transactions'],
            'Total Pendapatan: Rp '.number_format((int) $summary['total_revenue'], 0, ',', '.'),
            'Total Item      : '.$summary['total_items'],
            'Rata-rata Order : Rp '.number_format((int) $summary['average_order'], 0, ',', '.'),
            '',
            str_pad('NO', 4).str_pad('NOMOR PESANAN', 24).str_pad('TANGGAL', 18)
                .str_pad('KANAL', 10).str_pad('ITEM', 7).'TOTAL',
            str_repeat('-', 82),
        ];

        foreach ($rows->values() as $index => $row) {
            $lines[] = str_pad((string) ($index + 1), 4)
                .str_pad($this->limit((string) $row['order_number'], 22), 24)
                .str_pad($this->dateLabel($row['paid_at']), 18)
                .str_pad(strtoupper((string) $row['channel']), 10)
                .str_pad((string) $row['total_quantity'], 7)
                .'Rp '.number_format((int) $row['grand_total'], 0, ',', '.');
        }

        return $this->buildPdf($lines);
    }

    /**
     * @param  list<mixed>  $values
     * @param  list<bool>  $numericColumns
     */
    private function excelRow(
        array $values,
        bool $header = false,
        array $numericColumns = [],
    ): string {
        $cells = '';
        foreach ($values as $index => $value) {
            $numeric = $numericColumns[$index] ?? false;
            $type = $numeric ? 'Number' : 'String';
            $style = $header ? ' ss:StyleID="Header"' : ($numeric ? ' ss:StyleID="Number"' : '');
            $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells .= "<Cell{$style}><Data ss:Type=\"{$type}\">{$escaped}</Data></Cell>";
        }
        return "<Row>{$cells}</Row>";
    }

    /**
     * @param  list<string>  $lines
     */
    private function buildPdf(array $lines): string
    {
        $pages = array_chunk($lines, 44);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $kids = [];
        $nextObject = 4;
        foreach ($pages as $pageLines) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $kids[] = "{$pageObject} 0 R";
            $stream = $this->pdfStream($pageLines);
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                ."/Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObject + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= $maxObject; $number++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        }
        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /** @param  list<string>  $lines */
    private function pdfStream(array $lines): string
    {
        $commands = [];
        foreach ($lines as $index => $line) {
            $fontSize = $index === 0 ? 14 : 8;
            $y = 808 - ($index * 17);
            $text = $this->pdfEscape($line);
            $commands[] = "BT /F1 {$fontSize} Tf 34 {$y} Td ({$text}) Tj ET";
        }
        return implode("\n", $commands);
    }

    private function pdfEscape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'online' => 'Online',
            'cashier' => 'Offline/Kasir',
            default => 'Semua',
        };
    }

    private function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    private function dateLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '-';
        }
        return Carbon::parse($value)->format('d/m/Y H:i');
    }

    private function limit(string $value, int $length): string
    {
        return mb_strimwidth($value, 0, $length, '..');
    }
}
