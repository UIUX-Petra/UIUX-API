<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Str;

class LabeledDuplicatePairSeeder extends Seeder
{
    public function run(): void
    {
        $allSubjects = Subject::all()->keyBy('name');

        $groupToSubjectMap = [
            'oop_main' => 'Pemrograman Berorientasi Obyek',
            'python_beginner' => 'Dasar Pemrograman',
            'java_dev' => 'JAVA',
            'algo_vs_code' => 'Algoritma dan Pemrograman',
            'data_structure' => 'Struktur Data',
            'rekursi' => 'Algoritma dan Pemrograman',
            'db_admin' => 'Administrasi Basis Data',
            'normalization_db' => 'Basis Data',
            'sql_join' => 'Basis Data',
            'data_warehouse' => 'Data Warehouse',
            'ai_definition' => 'Kecerdasan Buatan',
            'datamin_ml' => 'Data Mining',
            'jst_cv' => 'Jaringan Saraf Tiruan',
            'nlp_intro' => 'Natural Language Processing',
            'tcp_ip' => 'Jaringan Komputer',
            'security_firewall' => 'Manajemen Keamanan Komputer',
            'aok_dsk' => 'Arsitektur dan Organisasi Komputer',
            'cloud_computing' => 'Computing Infrastructure Development',
            'agile_vs_waterfall' => 'Rekayasa Perangkat Lunak',
            'rpl_testing' => 'Rekayasa Perangkat Lunak',
            'erp_concept' => 'Enterprise Resource Planning',
            'adsi_rsi' => 'Analisis dan Desain Sistem Informasi'
        ];

        $labeledPairs = [];
        $processedPairs = [];

        if (!function_exists('sorted_pair_key')) {
            function sorted_pair_key($id1, $id2)
            {
                $ids = [$id1, $id2];
                sort($ids);
                return implode('-', $ids);
            }
        }

        foreach ($groupToSubjectMap as $group_name => $subject_name) {
            $subject_id = $allSubjects[$subject_name]->id ?? null;

            if (!$subject_id) {
                continue;
            }

            $questionsInThisSubject = Question::whereHas('groupQuestion.subject', function ($query) use ($subject_id) {
                $query->where('id', $subject_id);
            })->pluck('id')->toArray();

            if (count($questionsInThisSubject) >= 2) {
                for ($i = 0; $i < count($questionsInThisSubject); $i++) {
                    for ($j = $i + 1; $j < count($questionsInThisSubject); $j++) {
                        $q1_id = $questionsInThisSubject[$i];
                        $q2_id = $questionsInThisSubject[$j];

                        $pairKey = sorted_pair_key($q1_id, $q2_id);
                        if (!in_array($pairKey, $processedPairs)) {
                            $labeledPairs[] = [
                                'id' => (string) Str::uuid(),
                                'question1_id' => $q1_id,
                                'question2_id' => $q2_id,
                                'is_duplicate' => 1,
                                'group_id' => $group_name,
                                'source' => 'seeder_duplicate',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                            $processedPairs[] = $pairKey;
                        }
                    }
                }
            }
        }

        $allQuestionIds = Question::pluck('id')->toArray();
        $numQuestions = count($allQuestionIds);
        $targetNonDuplicates = count($labeledPairs) * 1.5;

        $nonDuplicateCount = 0;
        $maxAttempts = $numQuestions * $numQuestions * 2;

        while ($nonDuplicateCount < $targetNonDuplicates && $maxAttempts > 0) {
            $q1_id = $allQuestionIds[array_rand($allQuestionIds)];
            $q2_id = $allQuestionIds[array_rand($allQuestionIds)];

            if ($q1_id === $q2_id) {
                $maxAttempts--;
                continue;
            }

            $pairKey = sorted_pair_key($q1_id, $q2_id);
            if (in_array($pairKey, $processedPairs)) {
                $maxAttempts--;
                continue;
            }

            $q1_tags = DB::table('subject_questions')->where('question_id', $q1_id)->pluck('tag_id')->toArray();
            $q2_tags = DB::table('subject_questions')->where('question_id', $q2_id)->pluck('tag_id')->toArray();

            if (empty(array_intersect($q1_tags, $q2_tags))) {
                $labeledPairs[] = [
                    'id' => (string) Str::uuid(),
                    'question1_id' => $q1_id,
                    'question2_id' => $q2_id,
                    'is_duplicate' => 0,
                    'group_id' => 'random_non_duplicate',
                    'source' => 'seeder_non_duplicate',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $processedPairs[] = $pairKey;
                $nonDuplicateCount++;
            }
            $maxAttempts--;
        }

        DB::table('labeled_duplicate_pairs')->insertOrIgnore($labeledPairs);
        $this->command->info('Labeled duplicate pairs seeded successfully! Total pairs: ' . count($labeledPairs));
    }
}