<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = User::pluck('id')->toArray();
        $questionsData = [
           ['group' => 'oop_main', 'title' => 'Konsep dasar PBO', 'question' => 'Jelaskan pilar-pilar utama dalam Pemrograman Berorientasi Obyek: Encapsulation, Inheritance, Polymorphism.'],
            ['group' => 'oop_main', 'title' => 'Apa itu Object Oriented Programming?', 'question' => 'Tolong berikan pengertian tentang paradigma OOP dan keunggulannya.'],
            ['group' => 'oop_main', 'title' => 'Pilar Utama Pemrograman Berorientasi Objek', 'question' => 'Sebutkan dan jelaskan pilar-pilar utama dalam PBO.'],
            ['group' => 'oop_main', 'title' => 'Fundamental OOP', 'question' => 'Apa saja konsep fundamental yang harus dipahami dalam pemrograman berorientasi objek?'],

            ['group' => 'python_beginner', 'title' => 'Belajar Python untuk Pemula', 'question' => 'Langkah-langkah untuk memulai belajar bahasa pemrograman Python bagi pemula.'],
            ['group' => 'python_beginner', 'title' => 'Memulai pemrograman Python', 'question' => 'Saya ingin belajar Python, bagaimana cara memulainya dari nol?'],
            ['group' => 'python_beginner', 'title' => 'Tips belajar coding Python', 'question' => 'Ada tips untuk belajar coding dengan Python agar cepat bisa?'],

            ['group' => 'java_dev', 'title' => 'Pengembangan Aplikasi Java', 'question' => 'Bagaimana cara membuat aplikasi desktop menggunakan bahasa Java?'],
            ['group' => 'java_dev', 'title' => 'Membangun program dengan JAVA', 'question' => 'Saya ingin membuat program dengan JAVA, apa saja yang perlu disiapkan?'],

            ['group' => 'algo_vs_code', 'title' => 'Perbedaan Algoritma dan Pemrograman', 'question' => 'Apa bedanya algoritma dengan pemrograman? Bisakah diberikan contoh?'],
            ['group' => 'algo_vs_code', 'title' => 'Beda Algoritma vs Kode Program', 'question' => 'Mohon jelaskan perbedaan mendasar antara algoritma dan kode program.'],
            
            ['group' => 'data_structure', 'title' => 'Pentingnya Struktur Data', 'question' => 'Mengapa struktur data itu penting dalam pengembangan software?'],
            ['group' => 'data_structure', 'title' => 'Peran Struktur Data dalam Algoritma', 'question' => 'Bagaimana struktur data mempengaruhi efisiensi sebuah algoritma?'],
            ['group' => 'data_structure', 'title' => 'Fungsi Struktur Data', 'question' => 'Apa fungsi utama dari struktur data dalam pemrograman?'],
            
            ['group' => 'rekursi', 'title' => 'Konsep Fungsi Rekursif', 'question' => 'Jelaskan apa itu fungsi rekursif dan berikan contoh sederhananya dalam pemrograman.'],
            ['group' => 'rekursi', 'title' => 'Pemrograman dengan Rekursi', 'question' => 'Bagaimana cara kerja dari sebuah fungsi rekursif? Kapan sebaiknya digunakan?'],
            
            ['group' => 'db_admin', 'title' => 'Administrasi Basis Data: Pengelolaan DB', 'question' => 'Saya ingin tahu tentang bagaimana mengelola basis data, termasuk backup dan recovery.'],
            ['group' => 'db_admin', 'title' => 'Bagaimana mengelola DB?', 'question' => 'Jelaskan proses administrasi basis data, apa saja tugas seorang DBA?'],
            
            ['group' => 'normalization_db', 'title' => 'Normalisasi Database', 'question' => 'Apa tujuan normalisasi dalam perancangan basis data dan jelaskan bentuk 1NF, 2NF, 3NF.'],
            ['group' => 'normalization_db', 'title' => 'Pentingnya normalisasi data', 'question' => 'Mengapa kita perlu melakukan normalisasi pada tabel-tabel di database?'],
            ['group' => 'normalization_db', 'title' => 'Proses Normalisasi 1NF, 2NF, 3NF', 'question' => 'Tolong jelaskan langkah-langkah untuk mencapai bentuk normal ketiga (3NF).'],
            
            ['group' => 'sql_join', 'title' => 'Cara JOIN tabel di SQL', 'question' => 'Bagaimana cara menggabungkan dua tabel atau lebih menggunakan perintah JOIN dalam SQL?'],
            ['group' => 'sql_join', 'title' => 'Perintah JOIN pada Basis Data', 'question' => 'Tolong jelaskan berbagai jenis JOIN (INNER, LEFT, RIGHT) dalam basis data relasional.'],
            
            ['group' => 'data_warehouse', 'title' => 'Pengenalan Data Warehouse', 'question' => 'Apa bedanya Data Warehouse dengan database transaksional (OLTP)?'],
            ['group' => 'data_warehouse', 'title' => 'Konsep dasar Data Warehousing', 'question' => 'Jelaskan konsep dan arsitektur dari sebuah data warehouse untuk business intelligence.'],
            
            ['group' => 'ai_definition', 'title' => 'Apa definisi AI?', 'question' => 'Tolong berikan pengertian tentang kecerdasan buatan, dan contoh aplikasinya.'],
            ['group' => 'ai_definition', 'title' => 'Pengertian Artificial Intelligence', 'question' => 'Jelaskan secara singkat apa itu AI dan manfaatnya.'],
            
            ['group' => 'datamin_ml', 'title' => 'Algoritma Clustering di Data Mining', 'question' => 'Apa saja contoh algoritma clustering yang sering digunakan dalam data mining?'],
            ['group' => 'datamin_ml', 'title' => 'Unsupervised Learning: Clustering', 'question' => 'Bagaimana cara kerja metode unsupervised learning seperti clustering untuk menemukan pola?'],
            
            ['group' => 'jst_cv', 'title' => 'Aplikasi JST di Computer Vision', 'question' => 'Bagaimana Jaringan Saraf Tiruan digunakan untuk tugas pengenalan objek pada gambar?'],
            ['group' => 'jst_cv', 'title' => 'Peran CNN dalam Computer Vision', 'question' => 'Jelaskan arsitektur Convolutional Neural Network (CNN) untuk klasifikasi gambar.'],
            ['group' => 'jst_cv', 'title' => 'Deep Learning untuk gambar', 'question' => 'Teknik deep learning apa yang cocok untuk kasus computer vision?'],

            ['group' => 'nlp_intro', 'title' => 'Pengenalan Natural Language Processing', 'question' => 'Apa yang dimaksud dengan NLP dan apa saja aplikasinya?'],
            ['group' => 'nlp_intro', 'title' => 'Dasar-dasar NLP', 'question' => 'Bagaimana komputer bisa memproses bahasa manusia? Jelaskan konsep dasar NLP.'],
            
            ['group' => 'tcp_ip', 'title' => 'Model TCP/IP', 'question' => 'Jelaskan lapisan-lapisan (layers) pada model referensi TCP/IP.'],
            ['group' => 'tcp_ip', 'title' => 'Apa itu TCP/IP?', 'question' => 'Fungsi dari setiap layer pada arsitektur jaringan TCP/IP itu apa saja?'],

            ['group' => 'security_firewall', 'title' => 'Fungsi Firewall', 'question' => 'Apa fungsi utama firewall dalam keamanan jaringan komputer?'],
            ['group' => 'security_firewall', 'title' => 'Cara kerja firewall untuk proteksi jaringan', 'question' => 'Bagaimana mekanisme kerja firewall dalam melindungi jaringan dari ancaman eksternal?'],
            
            ['group' => 'aok_dsk', 'title' => 'Struktur Dasar Komputer', 'question' => 'Jelaskan komponen utama dalam Arsitektur dan Organisasi Komputer.'],
            ['group' => 'aok_dsk', 'title' => 'Pengenalan Arsitektur Komputer', 'question' => 'Apa saja yang dipelajari dalam mata kuliah Dasar Sistem Komputer?'],

            ['group' => 'cloud_computing', 'title' => 'Pengenalan Cloud Computing', 'question' => 'Jelaskan model layanan cloud computing (IaaS, PaaS, SaaS).'],
            ['group' => 'cloud_computing', 'title' => 'Model Layanan Cloud', 'question' => 'Apa perbedaan IaaS, PaaS, dan SaaS?'],
            ['group' => 'cloud_computing', 'title' => 'Dasar Komputasi Awan', 'question' => 'Konsep dasar dari cloud computing itu seperti apa?'],

            ['group' => 'agile_vs_waterfall', 'title' => 'Beda Agile dan Waterfall', 'question' => 'Apa perbedaan mendasar antara metodologi Agile dengan Waterfall dalam manajemen proyek?'],
            ['group' => 'agile_vs_waterfall', 'title' => 'Agile vs Waterfall di RPL', 'question' => 'Kapan sebaiknya menggunakan Agile dan kapan menggunakan Waterfall untuk proyek RPL?'],
            
            ['group' => 'rpl_testing', 'title' => 'Metode Pengujian Perangkat Lunak', 'question' => 'Jelaskan perbedaan antara metode pengujian black box dan white box.'],
            ['group' => 'rpl_testing', 'title' => 'Black Box vs White Box Testing', 'question' => 'Apa beda mendasar dari pengujian black box dengan white box dalam konteks RPL?'],
            
            ['group' => 'erp_concept', 'title' => 'Konsep dasar ERP', 'question' => 'Apa yang dimaksud dengan Enterprise Resource Planning dan apa modul utamanya?'],
            ['group' => 'erp_concept', 'title' => 'Pengenalan Enterprise Resource Planning', 'question' => 'Jelaskan tentang sistem ERP dan bagaimana cara kerjanya mengintegrasikan proses bisnis.'],
            
            ['group' => 'adsi_rsi', 'title' => 'Tugas seorang System Analyst', 'question' => 'Apa saja tanggung jawab utama dari seorang analis sistem dalam siklus hidup pengembangan sistem?'],
            ['group' => 'adsi_rsi', 'title' => 'Peran Analis Sistem', 'question' => 'Jelaskan peran seorang system analyst dalam proyek rekayasa sistem informasi.'],


            ['group' => 'hard_negative_jarkom_1', 'title' => 'Jaringan Komputer (JARKOM)', 'question' => 'Apa saja jenis-jenis jaringan komputer yang umum digunakan?'],
            ['group' => 'hard_negative_jarkom_2', 'title' => 'Jaringan Komputer Dasar', 'question' => 'Bagaimana cara kerja protokol TCP/IP dalam jaringan komputer?'],
            ['group' => 'hard_negative_jarkom_3', 'title' => 'Jaringan Komputer', 'question' => 'Apa itu topologi jaringan?'],

            // unique questions 
            ['group' => 'unique_1', 'title' => 'Prinsip Desain UI/UX', 'question' => 'Apa saja prinsip-prinsip yang harus diperhatikan saat merancang antarmuka pengguna yang baik?'],
            ['group' => 'unique_2', 'title' => 'Apa itu Virtual Reality?', 'question' => 'Jelaskan teknologi di balik Virtual Reality dan perbedaannya dengan Augmented Reality.'],
            ['group' => 'unique_3', 'title' => 'Pengantar Kriptografi', 'question' => 'Apa perbedaan antara enkripsi simetris dan asimetris?'],
            ['group' => 'unique_4', 'title' => 'Konsep Big Data', 'question' => 'Jelaskan 3V (Volume, Velocity, Variety) dalam konteks Big Data.'],
            ['group' => 'unique_5', 'title' => 'Dasar-dasar HTML & CSS', 'question' => 'Apa fungsi HTML dan CSS dalam pembuatan halaman web?'],
            ['group' => 'unique_6', 'title' => 'Apa itu API?', 'question' => 'Jelaskan apa itu Application Programming Interface (API) dan berikan contoh penggunaannya.'],
            ['group' => 'unique_7', 'title' => 'Manajemen Memori di Sistem Operasi', 'question' => 'Bagaimana sistem operasi mengatur alokasi memori untuk berbagai proses?'],
            ['group' => 'unique_8', 'title' => 'Apa itu Logika Fuzzy?', 'question' => 'Berikan contoh penerapan sistem logika fuzzy dalam kehidupan sehari-hari.'],
            ['group' => 'unique_9', 'title' => 'Konsep Riset Operasi', 'question' => 'Apa tujuan dari Riset Operasi dan contoh masalah yang bisa diselesaikan?'],
            ['group' => 'unique_10', 'title' => 'Akuntansi Biaya vs Keuangan', 'question' => 'Apa perbedaan utama antara akuntansi biaya dengan akuntansi keuangan?'],
            ['group' => 'unique_11', 'title' => 'Apa itu Supply Chain Management?', 'question' => 'Jelaskan komponen-komponen utama dalam SCM.'],
            ['group' => 'unique_12', 'title' => 'Teori Bahasa dan Automata', 'question' => 'Mengapa Teori Bahasa dan Automata penting dalam ilmu komputer?'],
            ['group' => 'unique_13', 'title' => 'Dasar-dasar Desain Game', 'question' => 'Apa saja elemen-elemen fundamental dalam sebuah desain game?'],
            ['group' => 'unique_14', 'title' => 'Pengantar Sistem Informasi Geografis', 'question' => 'Bagaimana cara kerja SIG dalam memetakan data geografis?'],
            ['group' => 'unique_15', 'title' => 'Etika dalam Penggunaan TI', 'question' => 'Sebutkan beberapa isu etika yang relevan dalam dunia teknologi informasi saat ini.'],
        ];

        // Tambahan random question
        $currentQuestionCount = count($questionsData);
        $targetQuestionCount = 250;
        if ($currentQuestionCount < $targetQuestionCount) {
            $allTagNames = Subject::pluck('name')->toArray(); 
            $baseTitles = ['Analisis Lanjutan', 'Studi Kasus', 'Implementasi Praktis', 'Perbandingan Teknologi', 'Tinjauan Konsep', 'Tantangan dalam', 'Masa Depan dari'];

            for ($i = 1; $i <= ($targetQuestionCount - $currentQuestionCount); $i++) {
                $questionsData[] = [
                    'group' => 'random_group_' . $i,
                    'title' => $baseTitles[array_rand($baseTitles)] . ' ' . $allTagNames[array_rand($allTagNames)],
                    'question' => 'Ini adalah pembahasan mendalam dan pertanyaan acak yang berhubungan dengan topik ini.',
                ];
            }
        }

        $createdQuestions = [];
        foreach ($questionsData as $data) {
            Question::create([
                'title' => $data['title'],
                'question' => $data['question'],
                'user_id' => $userIds[array_rand($userIds)],
                'image' => (rand(0, 10) < 2) ? 'images/sample_image_' . rand(1, 10) . '.jpg' : null,
                'vote' => rand(0, 250),
                'view' => rand(50, 3000),
            ]);

            $createdQuestions[] = [
                'title'    => $data['title'],
                'question' => $data['question'],
                'group'    => $data['group']
            ];
        }

        $pairs = [];
        $positivePairs = 0;
        $negativePairs = 0;

        $groupedQuestions = [];
        foreach ($createdQuestions as $q) {
            $groupedQuestions[$q['group']][] = [
                'title' => $q['title'],
                'question' => $q['question']
            ];
        }

        // Buat pasangan positif (is_duplicate = 1) 
        foreach ($groupedQuestions as $group_id => $groupData) { 
            if (count($groupData) > 1) {
                for ($i = 0; $i < count($groupData); $i++) {
                    for ($j = $i + 1; $j < count($groupData); $j++) {
                        $pairs[] = [
                            $groupData[$i]['title'],
                            $groupData[$i]['question'],
                            $groupData[$j]['title'],
                            $groupData[$j]['question'],
                            1,
                            $group_id 
                        ];
                        $positivePairs++;
                    }
                }
            }
        }

        // Buat pasangan negatif (is_duplicate = 0)
        $uniqueTexts = array_values($createdQuestions);
        $maxAttempts = $positivePairs * 10;
        $attempt = 0;
        $targetNegativePairs = $positivePairs * 2;
        while ($negativePairs < $targetNegativePairs && $attempt < $maxAttempts) {
            $idx1 = rand(0, count($uniqueTexts) - 1);
            $idx2 = rand(0, count($uniqueTexts) - 1);
            if ($idx1 !== $idx2 && $uniqueTexts[$idx1]['group'] !== $uniqueTexts[$idx2]['group']) {
                $pairs[] = [
                    $uniqueTexts[$idx1]['title'],
                    $uniqueTexts[$idx1]['question'],
                    $uniqueTexts[$idx2]['title'],
                    $uniqueTexts[$idx2]['question'],
                    0,
                    'non_duplicate_pair_' . $negativePairs 
                ];
                $negativePairs++;
            }
            $attempt++;
        }

        $path = storage_path('app/ml_data');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path . '/labeled_pairs.csv';
        $file = fopen($filePath, 'w');

        fputcsv($file, ['q1_title', 'q1_question', 'q2_title', 'q2_question', 'is_duplicate', 'group_id']);
        shuffle($pairs);
        foreach ($pairs as $pair) {
            fputcsv($file, $pair);
        }
        fclose($file);

        $this->command->info("Success! 'labeled_pairs.csv' has been generated at '{$filePath}'.");
        $this->command->info("Total pairs: " . count($pairs) . " ({$positivePairs} positive, {$negativePairs} negative).");
    }
}
