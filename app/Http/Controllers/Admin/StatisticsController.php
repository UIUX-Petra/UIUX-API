<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Block;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatisticsController extends Controller
{
    public function getBasicStats(Request $request)
    {
        try {
            $validatedData = $request->validate(['period' => 'in:week,month,year']);
            $period = $validatedData['period'] ?? 'month';
            $endDate = Carbon::now();
            $startDate = match ($period) {
                'week' => $endDate->copy()->subWeek()->startOfDay(),
                'year' => $endDate->copy()->subYear()->startOfDay(),
                default => $endDate->copy()->subMonth()->startOfDay(),
            };

            // Statistik Kartu
            $totalUsers = User::count();
            $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
            $blockedUsers = Block::where(function ($query) {
                $query->whereNull('end_time')
                      ->orWhere('end_time', '>', now());
            })->distinct('blocked_user_id')->count('blocked_user_id');
            $newQuestions = Question::whereBetween('created_at', [$startDate, $endDate])->count();

            // Data untuk Grafik Tren
            $format = match($period) {
                'year' => 'M Y',
                default => 'd M'
            };

            $userTrend = $this->generateTrendData(User::class, $startDate, $endDate, $format);
            $questionTrend = $this->generateTrendData(Question::class, $startDate, $endDate, $format);

            $responseData = [
                'stats' => [
                    'totalUsers' => $totalUsers,
                    'newUsers' => $newUsers,
                    'blockedUsers' => $blockedUsers,
                    'newQuestions' => $newQuestions,
                    'periodLabel' => 'This ' . ucfirst($period),
                ],
                'charts' => [
                    'growthTrend' => [
                        'labels' => $userTrend->keys(),
                        'users' => $userTrend->values(),
                        'questions' => $questionTrend->values(),
                    ]
                ]
            ];
            
            return response()->json($responseData);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Invalid period specified.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An internal error occurred.'], 500);
        }
    }

    
    private function generateTrendData(string $model, Carbon $start, Carbon $end, string $format)
    {
        $data = $model::query()
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy(function ($date) use ($format) {
                return Carbon::parse($date->created_at)->format($format);
            })
            ->map->count();


        $result = collect();
        $periodIterator = $start->copy();
        
        $isYearly = ($format === 'M Y');

        if ($isYearly) {
            while ($periodIterator <= $end) {
                $key = $periodIterator->format($format);
                $result->put($key, $data->get($key, 0));
                $periodIterator->addMonthWithNoOverflow();
            }
        } else {
            while ($periodIterator <= $end) {
                $key = $periodIterator->format($format);
                $result->put($key, $data->get($key, 0));
                $periodIterator->addDay();
            }
        }

        return $result;
    }
}