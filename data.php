<?php
// ═══════════════════════════════════════════════════════
// FILE: data.php
// Endpoint tunggal untuk semua data slide.
// Akses: data.php?slide=1
//        data.php?slide=all   ← load semua sekaligus
// ═══════════════════════════════════════════════════════

// ── Konfigurasi Database ──────────────────────────────
define('DB_HOST',    getenv('DB_HOST') ?: 'mysql.railway.internal');
define('DB_NAME',    getenv('DB_NAME') ?: 'railway');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASSWORD') ?: 'lWUAPFctmZkkfMqfLRCXgJkLZsZJkuwc');
define('DB_CHARSET', 'utf8mb4');

// ── CORS & Header (izinkan akses dari file HTML lokal) ──
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

// ── Koneksi PDO ───────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ── Helper: response JSON ─────────────────────────────
function respond(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error(string $msg, int $code = 500): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── Router ────────────────────────────────────────────
$slide = $_GET['slide'] ?? 'all';

try {
    $db = getDB();

    if ($slide === 'all') {
        // Load semua data sekaligus
        respond([
            'slide1' => getSlide1($db),
            'slide2' => getSlide2($db),
            'slide3' => getSlide3($db),
            'slide4' => getSlide4($db),
            'slide5' => getSlide5($db),
            'slide6' => getSlide6($db),
            'slide7' => getSlide7($db),
            'slide8' => getSlide8($db),
            'slide9' => getSlide9($db),
            'slide10' => getSlide10($db),
        ]);
    }

    match ((int)$slide) {
        1 => respond(getSlide1($db)),
        2 => respond(getSlide2($db)),
        3 => respond(getSlide3($db)),
        4 => respond(getSlide4($db)),
        5 => respond(getSlide5($db)),
        6 => respond(getSlide6($db)),
        7 => respond(getSlide7($db)),
        8 => respond(getSlide8($db)),
        9 => respond(getSlide9($db)),
        10 => respond(getSlide10($db)),
        default => error('Slide tidak ditemukan', 404),
    };

} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    error('Server error: ' . $e->getMessage());
}

// ═══════════════════════════════════════════════════════
// FUNGSI DATA PER SLIDE (Sudah Diisi Query)
// ═══════════════════════════════════════════════════════

/**
 * SLIDE 1 — Cover: distribusi risiko untuk donut chart
 * Output: { high, medium, low, total_jobs }
 */
function getSlide1(PDO $db): array {
    $stmt = $db->query("
        SELECT ai_risk_category, COUNT(*) as count 
        FROM jobs 
        WHERE ai_risk_category IS NOT NULL
        GROUP BY ai_risk_category
    ");
    $rows = $stmt->fetchAll();
    
    $result = ['high' => 0, 'medium' => 0, 'low' => 0, 'total_jobs' => 0];
    
    foreach ($rows as $row) {
        $cat = strtolower($row['ai_risk_category']);
        if (strpos($cat, 'high') !== false) {
            $result['high'] += $row['count'];
        } elseif (strpos($cat, 'medium') !== false) {
            $result['medium'] += $row['count'];
        } elseif (strpos($cat, 'low') !== false) {
            $result['low'] += $row['count'];
        }
        $result['total_jobs'] += $row['count'];
    }

    // Convert ke Persentase
    if ($result['total_jobs'] > 0) {
        $result['high'] = round(($result['high'] / $result['total_jobs']) * 100, 2);
        $result['medium'] = round(($result['medium'] / $result['total_jobs']) * 100, 2);
        $result['low'] = round(($result['low'] / $result['total_jobs']) * 100, 2);
    }
    
    return $result;
}

/**
 * SLIDE 2 — Bukti paradoks: Job Openings vs AI Risk (Bar Chart)
 * Output: array of { job_title, ai_risk_pct, job_openings }
 */
function getSlide2(PDO $db): array {
    $stmt = $db->query("
        SELECT 
            job_title, 
            ROUND(AVG(ai_risk_score) * 100, 2) as ai_risk_pct, 
            ROUND(AVG(job_openings), 0) as job_openings 
        FROM jobs 
        GROUP BY job_title 
        ORDER BY ai_risk_pct DESC 
        LIMIT 10
    ");
    return $stmt->fetchAll();
}

// Slide 3 salary distribution per job title (min, max, avg)
function getSlide3(PDO $db): array {
    $stmt = $db->query("
        SELECT
            TRIM(job_title)        AS profession,
            ROUND(MIN(salary), 2)  AS min_salary,
            ROUND(MAX(salary), 2)  AS max_salary,
            ROUND(AVG(salary), 2)  AS avg_salary
        FROM jobs
        WHERE salary IS NOT NULL
          AND job_title IS NOT NULL
          AND TRIM(job_title) != ''
        GROUP BY TRIM(job_title)
        ORDER BY avg_salary DESC
        LIMIT 10
    ");
    return $stmt->fetchAll();
}

// slide 4: salary bucket distribution per job title (high, medium, low)
function getSlide4(PDO $db): array {
    $stmt = $db->query("
        SELECT
            TRIM(job_title)  AS job_title,
            salary_bucket,
            COUNT(*)         AS cnt
        FROM jobs
        WHERE job_title    IS NOT NULL
          AND salary_bucket IS NOT NULL
          AND TRIM(job_title) IN (
              'AI Researcher', 'ML Engineer', 'Product Manager',
              'Cloud Engineer', 'DevOps Engineer', 'Data Scientist',
              'Software Engineer', 'Cybersecurity Analyst',
              'Business Analyst', 'Data Analyst'
          )
        GROUP BY TRIM(job_title), salary_bucket
        ORDER BY TRIM(job_title), salary_bucket
    ");
    $raw = $stmt->fetchAll();

    // Hitung total per job_title dulu
    $totals = [];
    foreach ($raw as $row) {
        $job = $row['job_title'];
        $totals[$job] = ($totals[$job] ?? 0) + (int)$row['cnt'];
    }

    // Reshape ke format { job_title, high_pct, low_pct, medium_pct, total }
    $result = [];
    foreach ($raw as $row) {
        $job    = $row['job_title'];
        $bucket = strtolower($row['salary_bucket']); // 'high', 'low', 'medium'
        $total  = $totals[$job];

        if (!isset($result[$job])) {
            $result[$job] = [
                'job_title'  => $job,
                'high_pct'   => 0.0,
                'low_pct'    => 0.0,
                'medium_pct' => 0.0,
                'total'      => $total,
            ];
        }
        $result[$job][$bucket . '_pct'] = round((int)$row['cnt'] / $total * 100, 1);
    }

    // Sort descending by high_pct
    usort($result, fn($a, $b) => $b['high_pct'] <=> $a['high_pct']);

    return array_values($result);
}

/**
 * SLIDE 5 — Pendidikan vs risiko
 * Output: array of { education_level, high_risk_pct, medium_risk_pct, low_risk_pct }
 */
function getSlide5(PDO $db): array {
    $stmt = $db->query("
        SELECT 
            education_level,
            -- Rata-rata ai_risk_score KHUSUS untuk baris High Risk
            -- (sama persis dengan Tableau AVG(Ai Risk Score) per kategori)
            ROUND(AVG(CASE WHEN ai_risk_category LIKE '%High%'   THEN ai_risk_score END), 4) as high_risk_pct,
            ROUND(AVG(CASE WHEN ai_risk_category LIKE '%Medium%' THEN ai_risk_score END), 4) as medium_risk_pct,
            ROUND(AVG(CASE WHEN ai_risk_category LIKE '%Low%'    THEN ai_risk_score END), 4) as low_risk_pct
        FROM jobs
        WHERE education_level IN ('Bachelor', 'Master', 'PhD', 'Bachelors', 'Masters')
        GROUP BY education_level
        ORDER BY education_level ASC
    ");
    return $stmt->fetchAll();
}

/**
 * SLIDE 6 — Top skills
 * Output: array of { skill_name, demand_score }
 */
function getSlide6(PDO $db): array {
    $stmt = $db->query("
        SELECT 
            primary_skill as skill_name, 
            ROUND(AVG(skill_demand_score), 2) as demand_score 
        FROM jobs 
        GROUP BY primary_skill 
        ORDER BY demand_score DESC 
        LIMIT 10
    ");
    return $stmt->fetchAll();
}

/**
 * SLIDE 7 — Stacked Bar Chart by Job Title
 * Berdasarkan kolom job_survival_class (int):
 *   0 = At Risk
 *   1 = Transitioning  
 *   2 = Thriving
 * 
 * Output: array of {
 *   job_title,
 *   at_risk_count,
 *   transitioning_count,
 *   thriving_count,
 *   total_count,
 *   at_risk_pct,
 *   transitioning_pct,
 *   thriving_pct
 * }
 */
function getSlide7(PDO $db): array {
    $stmt = $db->query("
        SELECT 
            job_title,
            SUM(CASE WHEN job_survival_class = 0 THEN 1 ELSE 0 END) as at_risk_count,
            SUM(CASE WHEN job_survival_class = 1 THEN 1 ELSE 0 END) as transitioning_count,
            SUM(CASE WHEN job_survival_class = 2 THEN 1 ELSE 0 END) as thriving_count,
            COUNT(*) as total_count
        FROM jobs 
        GROUP BY job_title
        ORDER BY job_title ASC
    ");
    $rows = $stmt->fetchAll();
    
    // Hitung persentase untuk setiap kategori
    $result = [];
    foreach ($rows as $row) {
        $total = (int)$row['total_count'];
        $atRisk = (int)$row['at_risk_count'];
        $transitioning = (int)$row['transitioning_count'];
        $thriving = (int)$row['thriving_count'];
        
        $result[] = [
            'job_title' => $row['job_title'],
            'at_risk_count' => $atRisk,
            'transitioning_count' => $transitioning,
            'thriving_count' => $thriving,
            'total_count' => $total,
            'at_risk_pct' => $total > 0 ? round(($atRisk / $total) * 100, 2) : 0,
            'transitioning_pct' => $total > 0 ? round(($transitioning / $total) * 100, 2) : 0,
            'thriving_pct' => $total > 0 ? round(($thriving / $total) * 100, 2) : 0
        ];
    }
    
    return $result;
}

/**
 * SLIDE 8 — Salary by Experience Level
 * Output: array of { experience_level, salary }
 */
function getSlide8(PDO $db): array {
    $stmt = $db->query("
        SELECT 
            experience_level,
            ROUND(salary, 2) as salary
        FROM jobs
        WHERE experience_level IN ('Entry', 'Mid', 'Senior')
          AND salary IS NOT NULL
          AND salary > 0
        ORDER BY 
            CASE experience_level
                WHEN 'Entry'  THEN 1
                WHEN 'Mid'    THEN 2
                WHEN 'Senior' THEN 3
                ELSE 4
            END
    ");
    return $stmt->fetchAll();
}

/**
 * SLIDE 9 — World heatmap
 * Output: array of { country_name, risk_index, avg_salary }
 */
function getSlide9(PDO $db): array {
    // Mapping country names to ISO 3166-1 alpha-3 codes
    $countryCodeMap = [
        'USA' => 'USA',
        'United States' => 'USA',
        'India' => 'IND',
        'Canada' => 'CAN',
        'UK' => 'GBR',
        'United Kingdom' => 'GBR',
        'Germany' => 'DEU',
        'Australia' => 'AUS',
        'China' => 'CHN',
        'Japan' => 'JPN',
        'France' => 'FRA',
        'Brazil' => 'BRA',
        'Netherlands' => 'NLD',
        'Singapore' => 'SGP',
        'South Korea' => 'KOR',
        'Korea' => 'KOR',
        'South Africa' => 'ZAF',
        'Mexico' => 'MEX',
        'Indonesia' => 'IDN',
        'Russia' => 'RUS',
        'Sweden' => 'SWE',
        'Spain' => 'ESP',
        'Italy' => 'ITA',
        'Poland' => 'POL',
        'Switzerland' => 'CHE',
        'Belgium' => 'BEL',
        'Austria' => 'AUT',
        'Norway' => 'NOR',
        'Denmark' => 'DNK',
        'Finland' => 'FIN',
        'Ireland' => 'IRL',
        'New Zealand' => 'NZL',
        'Portugal' => 'PRT',
        'Czech Republic' => 'CZE',
        'Romania' => 'ROU',
        'Hungary' => 'HUN',
        'Greece' => 'GRC',
        'Israel' => 'ISR',
        'UAE' => 'ARE',
        'United Arab Emirates' => 'ARE',
        'Saudi Arabia' => 'SAU',
        'Malaysia' => 'MYS',
        'Philippines' => 'PHL',
        'Thailand' => 'THA',
        'Vietnam' => 'VNM',
        'Argentina' => 'ARG',
        'Chile' => 'CHL',
        'Colombia' => 'COL',
        'Peru' => 'PER',
        'Egypt' => 'EGY',
        'Nigeria' => 'NGA',
        'Kenya' => 'KEN',
        'Pakistan' => 'PAK',
        'Bangladesh' => 'BGD',
        'Turkey' => 'TUR',
        'Ukraine' => 'UKR',
        'Taiwan' => 'TWN',
        'Hong Kong' => 'HKG',
    ];

    // 1. Dapatkan Rata-Rata Gaji Per Negara
    $stmtSal = $db->query("
        SELECT 
            country, 
            AVG(salary) as avg_salary,
            COUNT(*) as job_count
        FROM jobs 
        GROUP BY country
    ");
    $countryStats = $stmtSal->fetchAll();

    // 2. Kalkulasi setara { FIXED [Country], [Job Title] : AVG([Ai Risk Score]) }
    $stmtRisk = $db->query("
        SELECT 
            country, 
            job_title, 
            AVG(ai_risk_score) as avg_risk,
            COUNT(*) as count
        FROM jobs 
        GROUP BY country, job_title
    ");
    $jobRisks = $stmtRisk->fetchAll();

    // Kelompokkan data risk berdasarkan negaranya
    $groupedRisks = [];
    foreach($jobRisks as $row) {
        $groupedRisks[$row['country']][] = $row;
    }

    $result = [];
    foreach($countryStats as $stat) {
        $country = $stat['country'];
        
        $mostRiskJob = '';
        $safestJob = '';
        $maxRisk = -1;
        $minRisk = 999;
        $mostRiskScore = 0;
        $safestScore = 0;

        // 3. Cari nilai MAX dan MIN di dalam negara tersebut 
        // Setara dengan IF = MAX() / MIN() THEN [Job Title]
        if(isset($groupedRisks[$country])) {
            foreach($groupedRisks[$country] as $r) {
                if($r['avg_risk'] > $maxRisk) {
                    $maxRisk = $r['avg_risk'];
                    $mostRiskJob = $r['job_title'];
                    $mostRiskScore = $r['avg_risk'];
                }
                if($r['avg_risk'] < $minRisk) {
                    $minRisk = $r['avg_risk'];
                    $safestJob = $r['job_title'];
                    $safestScore = $r['avg_risk'];
                }
            }
        }

        // Get ISO code
        $isoCode = $countryCodeMap[$country] ?? null;

        $result[] = [
            'country_name' => $country,
            'country_code' => $isoCode,
            'avg_salary' => round($stat['avg_salary'], 2),
            'job_count' => $stat['job_count'],
            'most_risk_job' => $mostRiskJob,
            'most_risk_score' => round($mostRiskScore * 100, 1), // Convert to percentage
            'safest_job' => $safestJob,
            'safest_score' => round($safestScore * 100, 1) // Convert to percentage
        ];
    }
    
    return $result;
}

/**
 * SLIDE 10 — Closing stats
 * Output: object key-value { stat_key: { value, description } }
 */
function getSlide10(PDO $db): array {
    $stmt = $db->query("SELECT COUNT(*) as total_records FROM jobs");
    $total = $stmt->fetchColumn();
    
    return [
        'total_data_analyzed' => $total,
        'message' => 'Di era AI, nilai strategis dan keahlian mendalam adalah pelindung karier terbaik.'
    ];
}
