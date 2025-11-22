<?php
/**
 * Balance Builder: Computes account balances grouped by student + grade
 * Returns an array of balance records where each student-grade pair is a separate row.
 * No database schema changes required.
 */

function gradeKeyForDom($g) {
    // Normalize and produce a safe key for DOM attributes and ids like "grade_4" or "kinder_1"
    $k = normalizeGradeKey($g);
    $k = preg_replace('/\s+/', '_', $k);
    $k = preg_replace('/[^a-z0-9_]+/', '', $k);
    return ($k === '' ? 'grade' : $k);
}

function buildPerGradeBalances($conn, $studentNameExpr, $gradeExpr, $hasGradeColumn, $gradeColumnName, $gradeFeeMap) {
    $balances = [];
    $errorMsg = '';

    $gradeColumnCandidates = ['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'];

    $feesExists = tableExists($conn, 'fees');
    $paymentsExists = tableExists($conn, 'payments');
    $feesHasAmount = $feesExists && columnExists($conn, 'fees', 'amount');
    $feesHasStudentId = $feesExists && columnExists($conn, 'fees', 'student_id');
    $feesHasCategory = $feesExists && columnExists($conn, 'fees', 'category');

    $feeGradeColumn = null;
    if ($feesExists) {
        foreach ($gradeColumnCandidates as $candidate) {
            if (columnExists($conn, 'fees', $candidate)) {
                $feeGradeColumn = $candidate;
                break;
            }
        }
    }

    $paymentGradeColumn = null;
    if ($paymentsExists) {
        foreach ($gradeColumnCandidates as $candidate) {
            if (columnExists($conn, 'payments', $candidate)) {
                $paymentGradeColumn = $candidate;
                break;
            }
        }
    }
    $paymentHasFeeId = $paymentsExists && columnExists($conn, 'payments', 'fee_id');

    $gradeFeeMapNormalized = [];
    foreach ($gradeFeeMap as $label => $feeValue) {
        $gradeFeeMapNormalized[normalizeGradeKey($label)] = (float)$feeValue;
    }
    $gradeOrder = array_keys($gradeFeeMapNormalized);
    $gradeOrderIndex = array_flip($gradeOrder);

    $studentData = [];
    $totalPaymentsOverall = [];
    $unassignedPayments = [];
    $feeInfoById = [];

    $ensureStudent = function(int $sid) use (&$studentData) {
        if (!isset($studentData[$sid])) {
            $studentData[$sid] = [
                'name' => "Student {$sid}",
                'current_grade' => '',
                'current_grade_norm' => '',
                'grades' => []
            ];
        }
    };

    $ensureGrade = function(int $sid, string $gradeLabel) use (&$studentData, $ensureStudent) {
        $gradeNorm = normalizeGradeKey($gradeLabel);
        if ($gradeNorm === '') {
            return null;
        }

        $ensureStudent($sid);
        $displayLabel = trim($gradeLabel) !== '' ? trim($gradeLabel) : ucwords(str_replace('_', ' ', $gradeNorm));

        if (!isset($studentData[$sid]['grades'][$gradeNorm])) {
            $studentData[$sid]['grades'][$gradeNorm] = [
                'label' => $displayLabel,
                'total_fees' => 0.0,
                'total_payments' => 0.0,
                'payments_known' => 0.0,
            ];
        } elseif ($displayLabel !== '' && trim($studentData[$sid]['grades'][$gradeNorm]['label']) === '') {
            $studentData[$sid]['grades'][$gradeNorm]['label'] = $displayLabel;
        }

        return $gradeNorm;
    };

    $sqlStudents = "
        SELECT s.id AS student_id,
               {$studentNameExpr} AS student_name,
               {$gradeExpr} AS current_grade
        FROM students s
        ORDER BY student_name ASC
    ";
    try {
        $rsStudents = $conn->query($sqlStudents);
    } catch (mysqli_sql_exception $e) {
        $errorMsg .= 'Query failed (students): ' . $e->getMessage() . ' ';
        $rsStudents = false;
    }

    if ($rsStudents) {
        while ($studentRow = $rsStudents->fetch_assoc()) {
            $sid = (int)$studentRow['student_id'];
            $ensureStudent($sid);

            $studentData[$sid]['name'] = (string)$studentRow['student_name'];
            $currentGrade = trim((string)$studentRow['current_grade']);
            $currentGradeNorm = normalizeGradeKey($currentGrade);

            $studentData[$sid]['current_grade'] = $currentGrade;
            $studentData[$sid]['current_grade_norm'] = $currentGradeNorm;

            if ($currentGradeNorm !== '') {
                $ensureGrade($sid, $currentGrade);
            }
        }
    }

    if ($feesExists && $feesHasAmount && $feesHasStudentId) {
        $gradeFieldSelect = $feeGradeColumn ? "TRIM(IFNULL(f.`{$feeGradeColumn}`, '')) AS grade_label" : "'' AS grade_label";
        $feeCategoryCondition = $feesHasCategory ? " AND (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship'))" : '';
        $sqlFees = "
            SELECT f.id,
                   f.student_id,
                   {$gradeFieldSelect},
                   f.amount
            FROM fees f
            WHERE 1 = 1 {$feeCategoryCondition}
        ";
        try {
            $rsFees = $conn->query($sqlFees);
        } catch (mysqli_sql_exception $e) {
            $errorMsg .= 'Query failed (fees): ' . $e->getMessage() . ' ';
            $rsFees = false;
        }

        if ($rsFees) {
            while ($feeRow = $rsFees->fetch_assoc()) {
                $sid = (int)$feeRow['student_id'];
                $ensureStudent($sid);

                $rawGrade = $feeGradeColumn ? trim((string)$feeRow['grade_label']) : $studentData[$sid]['current_grade'];
                if ($rawGrade === '' && $studentData[$sid]['current_grade'] !== '') {
                    $rawGrade = $studentData[$sid]['current_grade'];
                }

                $gradeNorm = $ensureGrade($sid, $rawGrade);
                if ($gradeNorm === null) {
                    continue;
                }

                $studentData[$sid]['grades'][$gradeNorm]['total_fees'] += (float)$feeRow['amount'];
                $feeInfoById[(int)$feeRow['id']] = [
                    'student_id' => $sid,
                    'grade_norm' => $gradeNorm,
                    'grade_label' => $studentData[$sid]['grades'][$gradeNorm]['label']
                ];
            }
        }
    }

    if ($paymentsExists) {
        $selectGradeMarker = $paymentGradeColumn ? ", TRIM(IFNULL(p.`{$paymentGradeColumn}`, '')) AS grade_marker" : ", '' AS grade_marker";
        $selectFeeId = $paymentHasFeeId ? ", p.fee_id" : ", NULL AS fee_id";
        $sqlPayments = "
            SELECT p.id,
                   p.student_id,
                   p.amount
                   {$selectFeeId}
                   {$selectGradeMarker}
            FROM payments p
        ";
        try {
            $rsPayments = $conn->query($sqlPayments);
        } catch (mysqli_sql_exception $e) {
            $errorMsg .= 'Query failed (payments): ' . $e->getMessage() . ' ';
            $rsPayments = false;
        }

        if ($rsPayments) {
            while ($payRow = $rsPayments->fetch_assoc()) {
                $sid = (int)$payRow['student_id'];
                $amount = (float)$payRow['amount'];
                $feeId = $paymentHasFeeId ? (int)$payRow['fee_id'] : 0;
                $gradeFromPayment = $paymentGradeColumn ? trim((string)$payRow['grade_marker']) : '';

                $gradeNorm = null;
                if ($gradeFromPayment !== '') {
                    $gradeNorm = $ensureGrade($sid, $gradeFromPayment);
                } elseif ($feeId > 0 && isset($feeInfoById[$feeId])) {
                    $feeMeta = $feeInfoById[$feeId];
                    $ensureGrade($sid, $feeMeta['grade_label']);
                    $gradeNorm = $feeMeta['grade_norm'];
                }

                $totalPaymentsOverall[$sid] = ($totalPaymentsOverall[$sid] ?? 0.0) + $amount;

                if ($gradeNorm !== null && isset($studentData[$sid]['grades'][$gradeNorm])) {
                    $studentData[$sid]['grades'][$gradeNorm]['total_payments'] += $amount;
                    $studentData[$sid]['grades'][$gradeNorm]['payments_known'] += $amount;
                } else {
                    $unassignedPayments[$sid] = ($unassignedPayments[$sid] ?? 0.0) + $amount;
                }
            }
        }
    }

    foreach ($studentData as $sid => &$data) {
        $grades =& $data['grades'];

        if (empty($grades)) {
            if ($data['current_grade_norm'] !== '') {
                $ensureGrade($sid, $data['current_grade']);
                $grades =& $data['grades'];
            }
            if (empty($grades)) {
                continue;
            }
        }

        foreach ($grades as $gradeNorm => &$detail) {
            if ($detail['total_fees'] == 0.0 && isset($gradeFeeMapNormalized[$gradeNorm])) {
                $detail['total_fees'] = (float)$gradeFeeMapNormalized[$gradeNorm];
            }
            if (!isset($detail['payments_known'])) {
                $detail['payments_known'] = 0.0;
            }
        }
        unset($detail);

        $gradeKeys = array_keys($grades);
        usort($gradeKeys, function($a, $b) use ($gradeOrderIndex, $grades) {
            $rankA = $gradeOrderIndex[$a] ?? PHP_INT_MAX;
            $rankB = $gradeOrderIndex[$b] ?? PHP_INT_MAX;
            if ($rankA === $rankB) {
                return strnatcasecmp($grades[$a]['label'], $grades[$b]['label']);
            }
            return $rankA <=> $rankB;
        });

        $knownSum = 0.0;
        foreach ($gradeKeys as $gradeNorm) {
            $knownSum += (float)$grades[$gradeNorm]['payments_known'];
        }
        $overallPaid = $totalPaymentsOverall[$sid] ?? $knownSum;
        $unassigned = max(0.0, $overallPaid - $knownSum);

        if ($unassigned > 0) {
            foreach ($gradeKeys as $gradeNorm) {
                $fees = (float)$grades[$gradeNorm]['total_fees'];
                $payments = (float)$grades[$gradeNorm]['total_payments'];
                $capacity = max(0.0, $fees - $payments);
                $allocation = min($unassigned, $capacity);
                if ($allocation > 0) {
                    $grades[$gradeNorm]['total_payments'] += $allocation;
                    $unassigned -= $allocation;
                }
                if ($unassigned <= 0) {
                    break;
                }
            }
        }

        if ($unassigned > 0) {
            $targetNorm = $data['current_grade_norm'] !== '' && isset($grades[$data['current_grade_norm']])
                ? $data['current_grade_norm']
                : (end($gradeKeys) ?: null);

            if ($targetNorm !== null && isset($grades[$targetNorm])) {
                $grades[$targetNorm]['total_payments'] += $unassigned;
            }
            $unassigned = 0.0;
        }

        $carryAmount = 0.0;
        foreach ($gradeKeys as $gradeNorm) {
            $fees = (float)$grades[$gradeNorm]['total_fees'];
            $payments = (float)$grades[$gradeNorm]['total_payments'];
            $balance = round($fees - $payments, 2);
            $grades[$gradeNorm]['balance'] = $balance;

            if ($gradeNorm !== $data['current_grade_norm'] && $balance > 0) {
                $carryAmount += $balance;
            }
        }

        if ($carryAmount > 0 && $data['current_grade_norm'] !== '' && isset($grades[$data['current_grade_norm']])) {
            $grades[$data['current_grade_norm']]['total_fees'] = round(
                (float)$grades[$data['current_grade_norm']]['total_fees'] + $carryAmount,
                2
            );
            $grades[$data['current_grade_norm']]['balance'] = round(
                $grades[$data['current_grade_norm']]['total_fees'] - $grades[$data['current_grade_norm']]['total_payments'],
                2
            );

            foreach ($gradeKeys as $gradeNorm) {
                if ($gradeNorm === $data['current_grade_norm']) {
                    continue;
                }
                if ($grades[$gradeNorm]['balance'] > 0) {
                    $grades[$gradeNorm]['balance'] = 0.0;
                    $grades[$gradeNorm]['moved_to_current'] = true;
                }
            }
        }

        foreach ($gradeKeys as $gradeNorm) {
            $detail = $grades[$gradeNorm];
            $gradeLabel = trim($detail['label']) !== '' ? trim($detail['label']) : ucwords(str_replace('_', ' ', $gradeNorm));
            $gradeKey = gradeKeyForDom($gradeLabel);

            $totalFeesValue = $detail['total_fees'];
            if ($totalFeesValue == 0.0 && !isset($gradeFeeMapNormalized[$gradeNorm])) {
                $totalFeesValue = null;
            }

            $balanceValue = $totalFeesValue === null ? null : round((float)$detail['balance'], 2);

            $balances[] = [
                'student_id' => $sid,
                'student_name' => $data['name'],
                'grade' => $gradeLabel,
                'grade_key' => $gradeKey,
                'total_fees' => $totalFeesValue === null ? null : round((float)$totalFeesValue, 2),
                'total_payments' => round((float)$detail['total_payments'], 2),
                'balance' => $balanceValue,
                'moved_to_current' => !empty($detail['moved_to_current']),
            ];
        }
    }
    unset($data);

    return ['balances' => $balances, 'errorMsg' => $errorMsg];
}

// Include helper functions if not already loaded
if (!function_exists('tableExists')) {
    function tableExists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$table}'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('columnExists')) {
    function columnExists($conn, $table, $column) {
        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('normalizeGradeKey')) {
    function normalizeGradeKey($g) {
        $g = strtolower(trim((string)$g));
        $g = preg_replace('/[^a-z0-9 ]+/', ' ', $g);
        $g = preg_replace('/\s+/', ' ', $g);
        return trim($g);
    }
}

if (!function_exists('getFeeForGrade')) {
    function getFeeForGrade($gradeVal, $gradeFeeMap) {
        $g = normalizeGradeKey($gradeVal);
        if ($g === '') return null;

        if (preg_match('/\b(?:k|kg|kinder|kindergarten)\s*([12])\b/i', $g, $m)) {
            $key = 'kinder ' . intval($m[1]);
            if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
        }

        if (preg_match('/\b(?:g|gr|grade)\s*([1-6])\b/i', $g, $m)) {
            $key = 'grade ' . intval($m[1]);
            if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
        }

        if (preg_match('/\b([1-6])\b/', $g, $m)) {
            $key = 'grade ' . intval($m[1]);
            if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
        }

        if (isset($gradeFeeMap[$g])) return $gradeFeeMap[$g];

        return null;
    }
}

if (!function_exists('isGradeProvided')) {
    function isGradeProvided($gradeVal) {
        $g = normalizeGradeKey($gradeVal);
        if ($g === '') return false;
        $sentinels = ['not set', 'not-set', 'n/a', 'na', 'none', 'unknown', 'null', '-', '—', 'notset'];
        return !in_array($g, $sentinels, true);
    }
}
?>
