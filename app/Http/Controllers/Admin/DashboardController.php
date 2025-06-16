<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function getReportStats(Request $request)
    {
        try {
            $period = $request->validate(['period' => 'in:week,month,year'])['period'] ?? 'month';
            $endDate = Carbon::now();
            $startDate = match ($period) {
                'week' => $endDate->copy()->subWeek()->startOfDay(),
                'year' => $endDate->copy()->subYear()->startOfDay(),
                default => $endDate->copy()->subMonth()->startOfDay(),
            };

            $reports = Report::whereBetween('created_at', [$startDate, $endDate])->get();
            $totalReceived = $reports->count();
            $reportsHandled = $reports->where('status', '!=', 'pending')->count();
            $pendingReports = $totalReceived - $reportsHandled;
            $completionRate = $totalReceived > 0 ? round(($reportsHandled / $totalReceived) * 100) : 0;
            
            // kelompokkin berdasarkan tipe laporan
            $typeBreakdown = $reports->groupBy('reportable_type')
                ->map->count()
                ->mapWithKeys(function ($count, $type) {
                    if (empty($type)) {
                        return ['Unknown Type' => $count];
                    }
                    $typeName = class_basename($type);
                    return [$typeName => $count];
                });

            $date_format = match ($period) {
                'week' => 'D, d M', // Hasil: "Mon, 16 Jun", "Tue, 17 Jun"
                'year' => 'M Y',   // Hasil: "Jun 2024", "Jul 2024"
                default => 'd M',  // Hasil: "16 Jun", "17 Jun"
            };
            
            $trend_received = $this->getTrendData($startDate, $endDate, $date_format, 'all');
            $trend_handled = $this->getTrendData($startDate, $endDate, $date_format, 'handled');

            // Mengelompokkan berdasarkan alasan laporan
            $reasonBreakdown = $reports->groupBy('reason')
                ->map(function ($group) {
                    $count = $group->count();
                    $first = $group->first();
                    if (!$first || empty($first->reason)) {
                        return null; 
                    }
                    return [
                        'reason' => $first->reason,
                        'count' => $count,
                    ];
                })
                ->filter() 
                ->sortByDesc('count')
                ->values();

            $responseData = [
                'stats' => [
                    'totalReceived' => $totalReceived,
                    'reportsHandled' => $reportsHandled,
                    'pendingReports' => $pendingReports,
                    'completionRate' => $completionRate,
                ],
                'charts' => [
                    'typeBreakdown' => [
                        'labels' => $typeBreakdown->keys()->all(),
                        'data' => $typeBreakdown->values()->all(),
                    ],
                    'trend' => [
                        'labels' => $trend_received->keys()->all(),
                        'received' => $trend_received->values()->all(),
                        'handled' => $trend_handled->values()->all(),
                    ]
                ],
                'table' => [
                    'reasonBreakdown' => $reasonBreakdown->all(),
                ]
            ];
            
            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json(['message' => 'An internal error occurred while fetching dashboard data.'], 500);
        }
    }
    
    /**
     * ambil data tren
     */
    private function getTrendData(Carbon $start, Carbon $end, string $format, string $type)
    {
        $query = Report::query()->whereBetween('created_at', [$start, $end]);

        if ($type === 'handled') {
            $query->where('status', '!=', 'pending');
        }
        
        $data = $query->get()->groupBy(function($date) use ($format) {
            return Carbon::parse($date->created_at)->format($format);
        })->map->count();
        
        $result = collect();
        $periodIterator = $start->copy();
        
        $isYearlyView = ($format === 'M Y');
        $isWeeklyOrMonthlyView = in_array($format, ['D, d M', 'd M']);

        if ($isYearlyView) {
            while ($periodIterator <= $end) {
                $key = $periodIterator->format($format);
                $result->put($key, $data->get($key, 0));
                $periodIterator->addMonthWithNoOverflow();
            }
        } else if ($isWeeklyOrMonthlyView) {
             while ($periodIterator <= $end) {
                $key = $periodIterator->format($format);
                $result->put($key, $data->get($key, 0));
                $periodIterator->addDay();
            }
        }
        
        return $result;
    }
}
