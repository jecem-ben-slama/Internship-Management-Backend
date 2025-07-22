<?php
// backend/Gestionnaire/get_internship_distribution.php

require_once '../db_connect.php';
require_once '../verify_token.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
$response = array();

$userData = verifyJwtToken(); // Get user data from JWT
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can access this

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view internship distributions.']);
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

    // --- 5. Faculty Distribution (CORRECTED) ---
    // Assuming 'nomFaculte' is a column directly in the 'etudiants' table.
    $sql_faculty = "
        SELECT
            etu.nomFaculte AS facultyName, -- Assuming the column is named 'nomFaculte'
            COUNT(s.stageID) AS count
        FROM
            stages s
        JOIN
            etudiants etu ON s.etudiantID = etu.etudiantID
        WHERE
            etu.nomFaculte IS NOT NULL AND etu.nomFaculte != '' -- Exclude null/empty faculty names
        GROUP BY
            etu.nomFaculte
        ORDER BY
            count DESC;
    ";
    $stmt_faculty = $mysqli->prepare($sql_faculty);
    if (!$stmt_faculty) {
        error_log("Database error (faculty distribution): " . $mysqli->error);
        $allDistributions['faculty_distribution'] = [];
    } else {
        $stmt_faculty->execute();
        $result_faculty = $stmt_faculty->get_result();
        $faculty_distribution = [];
        while ($row = $result_faculty->fetch_assoc()) {
            $faculty_distribution[] = [
                'facultyName' => $row['facultyName'],
                'count' => (int)$row['count'],
            ];
        }
        $allDistributions['faculty_distribution'] = $faculty_distribution;
        $stmt_faculty->close();
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


    $response['status'] = 'success';
    $response['data'] = $allDistributions;
    echo json_encode($response);

} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
    echo json_encode($response);
}

$mysqli->close();
exit();
?>