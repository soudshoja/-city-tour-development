<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentProcessingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErrorDashboardController extends Controller
{
    /**
     * Display error analytics dashboard (ERR-05)
     */
    public function index(Request $request)
    {
        $timeRange = $request->input('range', '24h'); // 24h, 7d, 30d

        return view('admin.error-dashboard.index', [
            'timeRange' => $timeRange,
        ]);
    }

    /**
     * Get error metrics data as JSON for charts (ERR-05)
     */
    public function metrics(Request $request)
    {
        $timeRange = $request->input('range', '24h');
        $now = now();

        // Determine time boundaries
        switch ($timeRange) {
            case '7d':
                $startDate = $now->copy()->subDays(7);
                break;
            case '30d':
                $startDate = $now->copy()->subDays(30);
                break;
            case '24h':
            default:
                $startDate = $now->copy()->subHours(24);
                break;
        }

        // Summary statistics
        $totalProcessed = DocumentProcessingLog::where('created_at', '>=', $startDate)->count();
        $totalFailed = DocumentProcessingLog::where('created_at', '>=', $startDate)
            ->where('status', 'failed')
            ->count();
        $totalCompleted = DocumentProcessingLog::where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->count();

        $failureRate = $totalProcessed > 0 ? round(($totalFailed / $totalProcessed) * 100, 2) : 0;
        $successRate = 100 - $failureRate;

        // Average processing time (only completed documents)
        $avgProcessingTime = DocumentProcessingLog::where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->whereNotNull('processing_duration_ms')
            ->avg('processing_duration_ms');

        $avgProcessingTimeSeconds = $avgProcessingTime ? round($avgProcessingTime / 1000, 2) : 0;

        // Error trend data (time series)
        $errorTrend = $this->getErrorTrend($startDate, $timeRange);

        // Error type distribution
        $errorTypeDistribution = DocumentProcessingLog::where('created_at', '>=', $startDate)
            ->where('status', 'failed')
            ->selectRaw('error_code, COUNT(*) as count')
            ->groupBy('error_code')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'error_code' => $item->error_code ?? 'UNKNOWN',
                    'count' => $item->count,
                ];
            });

        // Per-supplier error rates
        $supplierErrors = DocumentProcessingLog::where('created_at', '>=', $startDate)
            ->selectRaw('supplier_id,
                COUNT(*) as total,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            ->groupBy('supplier_id')
            ->havingRaw('failed > 0')
            ->orderBy('failed', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $errorRate = $item->total > 0 ? round(($item->failed / $item->total) * 100, 2) : 0;
                return [
                    'supplier_id' => $item->supplier_id,
                    'total' => $item->total,
                    'failed' => $item->failed,
                    'error_rate' => $errorRate,
                ];
            });

        // Per-document-type error rates
        $documentTypeErrors = DocumentProcessingLog::where('created_at', '>=', $startDate)
            ->selectRaw('document_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            ->groupBy('document_type')
            ->havingRaw('failed > 0')
            ->orderBy('failed', 'desc')
            ->get()
            ->map(function ($item) {
                $errorRate = $item->total > 0 ? round(($item->failed / $item->total) * 100, 2) : 0;
                return [
                    'document_type' => $item->document_type,
                    'total' => $item->total,
                    'failed' => $item->failed,
                    'error_rate' => $errorRate,
                ];
            });

        // Recent errors
        $recentErrors = DocumentProcessingLog::where('status', 'failed')
            ->with('company')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'document_id' => $log->document_id,
                    'company_name' => $log->company->name ?? 'N/A',
                    'supplier_id' => $log->supplier_id,
                    'document_type' => $log->document_type,
                    'error_code' => $log->error_code,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at->toIso8601String(),
                    'created_at_human' => $log->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'summary' => [
                'total_processed' => $totalProcessed,
                'total_failed' => $totalFailed,
                'total_completed' => $totalCompleted,
                'failure_rate' => $failureRate,
                'success_rate' => $successRate,
                'avg_processing_time_seconds' => $avgProcessingTimeSeconds,
            ],
            'error_trend' => $errorTrend,
            'error_type_distribution' => $errorTypeDistribution,
            'supplier_errors' => $supplierErrors,
            'document_type_errors' => $documentTypeErrors,
            'recent_errors' => $recentErrors,
        ]);
    }

    /**
     * Get error trend data for charting
     */
    protected function getErrorTrend($startDate, $timeRange)
    {
        // Group by hour for 24h, by day for 7d/30d
        $groupFormat = $timeRange === '24h' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';

        $trend = DocumentProcessingLog::where('created_at', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(created_at, '{$groupFormat}') as period,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->groupBy('period')
            ->orderBy('period', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'total' => $item->total,
                    'failed' => $item->failed,
                    'completed' => $item->completed,
                    'failure_rate' => $item->total > 0 ? round(($item->failed / $item->total) * 100, 2) : 0,
                ];
            });

        return $trend;
    }
}
