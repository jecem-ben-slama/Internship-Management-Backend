<?php
// backend/Gestionnaire/get_internship_distribution.php

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any premature output
ob_start();

require_once '../db_connect.php';
require_once '../verify_token.php';

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean(); // Clean any output buffer before sending headers
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
$response = array();

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can access this

if (!in_array($userData['role'], $allowedRoles)) {
    ob_end_clean(); // Clean any output buffer
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view internship distributions.']);
    exit();
}

// Ensure $mysqli is connected before proceeding with database operations
if (!isset($mysqli) || $mysqli->connect_error) {
    ob_end_clean();
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $allDistributions = [];

    // --- 1. Internship Status Distribution ---
    $sql_status = "
        SELECT
            statut,
            COUNT(*) AS count
        FROM
            stages
        GROUP BY
            statut
        ORDER BY
            count DESC;
    ";
    $stmt_status = $mysqli->prepare($sql_status);
    if (!$stmt_status) {
        error_log("Database error (status distribution): " . $mysqli->error);
        $allDistributions['status_distribution'] = [];
    } else {
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();
        $status_distribution = [];
        while ($row = $result_status->fetch_assoc()) {
            $status_distribution[] = [
                'status' => $row['statut'],
                'count' => (int)$row['count'],
            ];
        }
        $allDistributions['status_distribution'] = $status_distribution;
        $stmt_status->close();
    }

    // --- 2. Internship Type Distribution ---
    $sql_type = "
        SELECT
            typeStage,
            COUNT(*) AS count
        FROM
            stages
        GROUP BY
            typeStage
        ORDER BY
            count DESC;
    ";
    $stmt_type = $mysqli->prepare($sql_type);
    if (!$stmt_type) {
        error_log("Database error (type distribution): " . $mysqli->error);
        $allDistributions['type_distribution'] = [];
    } else {
        $stmt_type->execute();
        $result_type = $stmt_type->get_result();
        $type_distribution = [];
        while ($row = $result_type->fetch_assoc()) {
            if (!empty($row['typeStage'])) { // Ensure typeStage is not empty/null
                $type_distribution[] = [
                    'type' => $row['typeStage'],
                    'count' => (int)$row['count'],
                ];
            }
        }
        $allDistributions['type_distribution'] = $type_distribution;
        $stmt_type->close();
    }

    // --- 3. Internship Duration Distribution ---
    $sql_duration = "
        SELECT
            CASE
                WHEN DATEDIFF(dateFin, dateDebut) IS NULL THEN 'Undefined'
                WHEN DATEDIFF(dateFin, dateDebut) <= 30 THEN '1 Month or Less'
                WHEN DATEDIFF(dateFin, dateDebut) <= 60 THEN '1-2 Months'
                WHEN DATEDIFF(dateFin, dateDebut) <= 90 THEN '2-3 Months'
                WHEN DATEDIFF(dateFin, dateDebut) <= 120 THEN '3-4 Months'
                ELSE 'Over 4 Months'
            END AS duration_range,
            COUNT(*) AS count
        FROM
            stages
        WHERE
            dateDebut IS NOT NULL AND dateFin IS NOT NULL
        GROUP BY
            duration_range
        ORDER BY
            CASE
                WHEN duration_range = '1 Month or Less' THEN 1
                WHEN duration_range = '1-2 Months' THEN 2
                WHEN duration_range = '2-3 Months' THEN 3
                WHEN duration_range = '3-4 Months' THEN 4
                WHEN duration_range = 'Over 4 Months' THEN 5
                ELSE 6
            END;
    ";
    $stmt_duration = $mysqli->prepare($sql_duration);
    if (!$stmt_duration) {
        error_log("Database error (duration distribution): " . $mysqli->error);
        $allDistributions['duration_distribution'] = [];
    } else {
        $stmt_duration->execute();
        $result_duration = $stmt_duration->get_result();
        $duration_distribution = [];
        while ($row = $result_duration->fetch_assoc()) {
            $duration_distribution[] = [
                'range' => $row['duration_range'],
                'count' => (int)$row['count'],
            ];
        }
        $allDistributions['duration_distribution'] = $duration_distribution;
        $stmt_duration->close();
    }

    // --- 4. Encadrant Workload Distribution ---
    $sql_encadrant = "
        SELECT
            u.username AS encadrantName,
            COUNT(s.stageID) AS internshipCount
        FROM
            stages s
        JOIN
            users u ON s.encadrantProID = u.userID -- Assuming encadrantProID is the primary encadrant
        WHERE
            u.role = 'Encadrant'
        GROUP BY
            u.username
        ORDER BY
            internshipCount DESC;
    ";
    $stmt_encadrant = $mysqli->prepare($sql_encadrant);
    if (!$stmt_encadrant) {
        error_log("Database error (encadrant workload): " . $mysqli->error);
        $allDistributions['encadrant_distribution'] = [];
    } else {
        $stmt_encadrant->execute();
        $result_encadrant = $stmt_encadrant->get_result();
        $encadrant_distribution = [];
        while ($row = $result_encadrant->fetch_assoc()) {
            $encadrant_distribution[] = [
                'encadrantName' => $row['encadrantName'],
                'internshipCount' => (int)$row['internshipCount'],
            ];
        }
        $allDistributions['encadrant_distribution'] = $encadrant_distribution;
        $stmt_encadrant->close();
    }

    // --- 5. Faculty Internship and Student Summary (ENHANCED) ---
    $sql_faculty_summary = "
        SELECT
            etu.nomFaculte AS facultyName,
            COUNT(DISTINCT etu.etudiantID) AS totalStudents,
            COUNT(s.stageID) AS totalInternships,
            COUNT(CASE WHEN s.statut = 'Validé' THEN s.stageID ELSE NULL END) AS validatedInternships
        FROM
            etudiants etu
        LEFT JOIN -- Use LEFT JOIN to include faculties even if they have no internships
            stages s ON etu.etudiantID = s.etudiantID
        WHERE
            etu.nomFaculte IS NOT NULL AND etu.nomFaculte != '' -- Exclude null/empty faculty names
        GROUP BY
            etu.nomFaculte
        ORDER BY
            totalInternships DESC;
    ";
    $stmt_faculty_summary = $mysqli->prepare($sql_faculty_summary);
    if (!$stmt_faculty_summary) {
        error_log("Database error (faculty summary): " . $mysqli->error);
        $allDistributions['faculty_internship_summary'] = [];
    } else {
        $stmt_faculty_summary->execute();
        $result_faculty_summary = $stmt_faculty_summary->get_result();
        $faculty_internship_summary = [];
        while ($row = $result_faculty_summary->fetch_assoc()) {
            $totalInternships = (int)$row['totalInternships'];
            $validatedInternships = (int)$row['validatedInternships'];
            $successRate = ($totalInternships > 0) ? round(($validatedInternships / $totalInternships) * 100, 2) : 0.00;

            $faculty_internship_summary[] = [
                'facultyName' => $row['facultyName'],
                'totalStudents' => (int)$row['totalStudents'],
                'totalInternships' => $totalInternships,
                'validatedInternships' => $validatedInternships,
                'successRate' => $successRate, // Percentage
            ];
        }
        $allDistributions['faculty_internship_summary'] = $faculty_internship_summary;
        $stmt_faculty_summary->close();
    }

    // --- 6. Subject Distribution ---
    $sql_subject = "
        SELECT
            sj.titre AS subjectTitle,
            COUNT(s.stageID) AS count
        FROM
            stages s
        JOIN
            sujetsstage sj ON s.sujetID = sj.sujetID
        GROUP BY
            sj.titre
        ORDER BY
            count DESC;
    ";
    $stmt_subject = $mysqli->prepare($sql_subject);
    if (!$stmt_subject) {
        error_log("Database error (subject distribution): " . $mysqli->error);
        $allDistributions['subject_distribution'] = [];
    } else {
        $stmt_subject->execute();
        $result_subject = $stmt_subject->get_result();
        $subject_distribution = [];
        while ($row = $result_subject->fetch_assoc()) {
            $subject_distribution[] = [
                'subjectTitle' => $row['subjectTitle'],
                'count' => (int)$row['count'],
            ];
        }
        $allDistributions['subject_distribution'] = $subject_distribution;
        $stmt_subject->close();
    }

    // --- 7. Total Financial Expenses (from stages.montantRemuneration) ---
    $sql_total_expenses = "
        SELECT
            SUM(montantRemuneration) AS totalExpenses
        FROM
            stages
        WHERE
            estRemunere = 1
            AND montantRemuneration IS NOT NULL;
    ";
    $stmt_total_expenses = $mysqli->prepare($sql_total_expenses);
    if (!$stmt_total_expenses) {
        error_log("Database error (total expenses): " . $mysqli->error);
        $allDistributions['total_financial_expenses'] = 0.00;
    } else {
        $stmt_total_expenses->execute();
        $result_total_expenses = $stmt_total_expenses->get_result();
        $expenses_data = $result_total_expenses->fetch_assoc();
        $totalExpenses = (float)($expenses_data['totalExpenses'] ?? 0.00);
        $allDistributions['total_financial_expenses'] = $totalExpenses;
        $stmt_total_expenses->close();
    }

    // --- NEW: 8. Total Amount Paid by Year (from stages.montantRemuneration and dateDebut) ---
    $sql_annual_expenses = "
        SELECT
            YEAR(dateDebut) AS year,
            SUM(montantRemuneration) AS annualExpenses
        FROM
            stages
        WHERE
            estRemunere = 1
            AND montantRemuneration IS NOT NULL
            AND dateDebut IS NOT NULL
        GROUP BY
            YEAR(dateDebut)
        ORDER BY
            year ASC;
    ";
    $stmt_annual_expenses = $mysqli->prepare($sql_annual_expenses);
    if (!$stmt_annual_expenses) {
        error_log("Database error (annual expenses): " . $mysqli->error);
        $allDistributions['annual_financial_expenses'] = [];
    } else {
        $stmt_annual_expenses->execute();
        $result_annual_expenses = $stmt_annual_expenses->get_result();
        $annual_expenses_data = [];
        while ($row = $result_annual_expenses->fetch_assoc()) {
            $annual_expenses_data[] = [
                'year' => (int)$row['year'],
                'annualExpenses' => (float)$row['annualExpenses'],
            ];
        }
        $allDistributions['annual_financial_expenses'] = $annual_expenses_data;
        $stmt_annual_expenses->close();
    }


    ob_end_clean(); // Clean any output buffer before final JSON output
    $response['status'] = 'success';
    $response['data'] = $allDistributions;
    echo json_encode($response);

} else {
    ob_end_clean(); // Clean any output buffer
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
    echo json_encode($response);
}

$mysqli->close();
exit();
?>
