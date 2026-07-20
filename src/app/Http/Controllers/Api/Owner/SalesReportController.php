<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Owner\SalesReportRequest;
use App\Services\OwnerSalesReportService;
use App\Services\SalesReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SalesReportController extends ApiController
{
    public function __construct(
        private readonly OwnerSalesReportService $reports,
        private readonly SalesReportExportService $exports,
    ) {}

    public function index(SalesReportRequest $request): JsonResponse
    {
        [$start, $end, $channel] = $this->filters($request);
        $query = $this->reports->query($start, $end, $channel);
        $summary = $this->reports->summary(clone $query);
        $perPage = (int) ($request->validated('per_page') ?? 100);
        $paginator = $query->paginate($perPage)->withQueryString();

        return $this->success([
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'channel' => $channel,
            ],
            'summary' => $summary,
            'transactions' => $paginator->getCollection()
                ->map(fn ($order) => $this->reports->mapOrder($order))
                ->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Laporan penjualan berhasil diambil.');
    }

    public function export(SalesReportRequest $request): Response
    {
        [$start, $end, $channel] = $this->filters($request);
        $query = $this->reports->query($start, $end, $channel);
        $summary = $this->reports->summary(clone $query);
        $rows = $this->reports->rows($query);
        $format = $request->validated('format') ?? 'pdf';
        $baseName = 'laporan-penjualan-'.$start->format('Ymd').'-'.$end->format('Ymd');

        if ($format === 'excel') {
            return response($this->exports->excel($start, $end, $channel, $summary, $rows))
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename=\"{$baseName}.xls\"");
        }

        return response($this->exports->pdf($start, $end, $channel, $summary, $rows))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$baseName}.pdf\"");
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?string} */
    private function filters(SalesReportRequest $request): array
    {
        return [
            Carbon::createFromFormat('Y-m-d', $request->validated('start_date')),
            Carbon::createFromFormat('Y-m-d', $request->validated('end_date')),
            $request->validated('channel'),
        ];
    }
}
