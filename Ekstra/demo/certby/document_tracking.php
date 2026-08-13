<?php

require_once 'config.php';
requireLogin();

if (isset($_POST['ajax']) && $_POST['ajax'] == 'update_status') {
    header('Content-Type: application/json');
    
    $documentId = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
    $newStatus = isset($_POST['newStatus']) ? $_POST['newStatus'] : '';
    
    if ($documentId > 0 && !empty($newStatus)) {
        try {
            $pdo = getConnection();
           
            $statusMapping = [
                'active' => 'active',
                'passive' => 'inactive', 
                'suspended' => 'suspended',
                'cancelled' => 'cancelled',
                'updated' => 'updated'
            ];
            
            if (!isset($statusMapping[$newStatus])) {
                echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
                exit();
            }
            
            $sql = "UPDATE certifications SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$statusMapping[$newStatus], $documentId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            } else {
                echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
            }
            
        } catch (Exception $e) {
            error_log('Status update error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
    }
    
    exit();
}

$userData = getUserData($_SESSION['user_id']);

$isReadOnlyUser = !empty($userData) && (($userData['role'] ?? '') !== 'operator');

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

$cert_type_filter = isset($_GET['cert_type']) ? sanitizeInput($_GET['cert_type']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitizeInput($_GET['sort_by']) : 'expiry_date_asc';
$company_search = isset($_GET['company_search']) ? sanitizeInput($_GET['company_search']) : '';
$document_number_search = isset($_GET['document_number']) ? sanitizeInput($_GET['document_number']) : '';
$consultant_search = isset($_GET['consultant_search']) ? sanitizeInput($_GET['consultant_search']) : '';
$day_filter = isset($_GET['day_filter']) ? sanitizeInput($_GET['day_filter']) : '';


function getInspectionDisplayDate($inspectionDates, $inspectionStatus, $type) {
    $calculatedDate = $type == 1 ? $inspectionDates['inspection1'] : $inspectionDates['inspection2'];
    $statusData = $type == 1 ? $inspectionStatus['inspection_1'] : $inspectionStatus['inspection_2'];
     
    if ($statusData['status'] == 'tamamlandi') {
        return [
            'date' => 'tamamlandı',
            'info' => 'tamamlandı',
            'class' => 'date-active'
        ];
    }
    
    if ($statusData['status'] == 'completed' && $statusData['completed_date']) {
        return [
            'date' => date('d.m.Y', strtotime($statusData['completed_date'])),
            'info' => 'tamamlandı',
            'class' => 'date-active'
        ];
    }
    
    if ($statusData['status'] == 'planlandi' && $statusData['planned_date']) {
        $plannedDate = new DateTime($statusData['planned_date']);
        $now = new DateTime();
        $diff = $now->diff($plannedDate);
        $days = $diff->days;
        
        if ($plannedDate >= $now) {
            $dayText = $days . ' gün kaldı';
        } else {
            $dayText = $days . ' gün geçmiş';
        }
        
        return [
            'date' => date('d.m.Y', strtotime($statusData['planned_date'])),
            'info' => 'Planlandı (' . $dayText . ')',
            'class' => $plannedDate >= $now ? 'date-upcoming' : 'date-danger'
        ];
    }
    
    if ($statusData['status'] == 'iptal') {
        return [
            'date' => $calculatedDate ? $calculatedDate->format('d.m.Y') : '-',
            'info' => 'İptal Edildi',
            'class' => 'date-cancelled'
        ];
    }
    
    if ($calculatedDate) {
        $now = new DateTime();
        $diff = $now->diff($calculatedDate);
        $days = $diff->days;
        
        if ($calculatedDate < $now) {
            return [
                'date' => $calculatedDate->format('d.m.Y'),
                'info' => $days . ' gün geçmiş',
                'class' => 'date-danger'
            ];
        } else {
            return [
                'date' => $calculatedDate->format('d.m.Y'),
                'info' => $days . ' gün kaldı',
                'class' => 'date-upcoming'
            ];
        }
    }
    
    return [
        'date' => '-',
        'info' => '',
        'class' => ''
    ];
}

function calculateInspectionDates($issueDate, $expiryDate, $inspectionCount = 2) {
    $issue = new DateTime($issueDate);
    $expiry = new DateTime($expiryDate);
    
    $inspection1Date = null;
    $inspection2Date = null;
    
    $totalDays = $issue->diff($expiry)->days;
    
    if ($inspectionCount == 2) {
        $firstInspectionDays = floor($totalDays / 3);
        $secondInspectionDays = floor($totalDays * 2 / 3);
        
        $inspection1Date = clone $issue;
        $inspection1Date->add(new DateInterval('P' . $firstInspectionDays . 'D'));
        
        $inspection2Date = clone $issue;
        $inspection2Date->add(new DateInterval('P' . $secondInspectionDays . 'D'));
        
    } elseif ($inspectionCount == 1) {
        $firstInspectionDays = floor($totalDays / 2);
        
        $inspection1Date = clone $issue;
        $inspection1Date->add(new DateInterval('P' . $firstInspectionDays . 'D'));
        
        $inspection2Date = null; 
        
    } elseif ($inspectionCount == 0) {
        $inspection1Date = null;
        $inspection2Date = null;
    }
    
    return [
        'inspection1' => $inspection1Date,
        'inspection2' => $inspection2Date
    ];
}

function getInspectionStatus($documentId) {
    try {
        $pdo = getConnection();
        $sql = "SELECT 
                    inspection_1_status, 
                    inspection_2_status 
                FROM certifications 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$documentId]);
        $certification = $stmt->fetch();
        
        if (!$certification) {
            return [
                'inspection_1' => ['status' => 'bekliyor', 'completed_date' => null, 'planned_date' => null],
                'inspection_2' => ['status' => 'bekliyor', 'completed_date' => null, 'planned_date' => null]
            ];
        }
        
        $sql = "SELECT 
                    p.audit_start_date as planned_date,
                    ci.completion_date as completed_date
                FROM plans p
                LEFT JOIN completed_inspections ci ON p.id = ci.plan_id
                WHERE p.certification_id = ?
                ORDER BY p.audit_start_date ASC
                LIMIT 2";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$documentId]);
        $inspectionRecords = $stmt->fetchAll();
        
        $recordsData = [];
        $index = 1;
        foreach ($inspectionRecords as $record) {
            $recordsData[$index] = [
                'planned_date' => $record['planned_date'],
                'completed_date' => $record['completed_date']
            ];
            $index++;
        }
        
        $result = [
            'inspection_1' => [
                'status' => $certification['inspection_1_status'] ?? 'bekliyor',
                'completed_date' => isset($recordsData[1]) ? $recordsData[1]['completed_date'] : null,
                'planned_date' => isset($recordsData[1]) ? $recordsData[1]['planned_date'] : null
            ],
            'inspection_2' => [
                'status' => $certification['inspection_2_status'] ?? 'bekliyor',
                'completed_date' => isset($recordsData[2]) ? $recordsData[2]['completed_date'] : null,
                'planned_date' => isset($recordsData[2]) ? $recordsData[2]['planned_date'] : null
            ]
        ];
        
        if ($result['inspection_1']['status'] === 'tamamlandi' && !$result['inspection_1']['completed_date']) {
            $result['inspection_1']['completed_date'] = date('Y-m-d');
        }
        
        if ($result['inspection_2']['status'] === 'tamamlandi' && !$result['inspection_2']['completed_date']) {
            $result['inspection_2']['completed_date'] = date('Y-m-d');
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Tetkik durumu hatası: " . $e->getMessage());
        return [
            'inspection_1' => ['status' => 'bekliyor', 'completed_date' => null, 'planned_date' => null],
            'inspection_2' => ['status' => 'bekliyor', 'completed_date' => null, 'planned_date' => null]
        ];
    }
}

function getDocuments($cert_type_filter = '', $sort_by = 'expiry_date_asc', $company_search = '', $document_number_search = '', $consultant_search = '', $day_filter = '') {
    try {
        $pdo = getConnection();
        
        $where_conditions = ["c.status IN ('active', 'inactive', 'suspended', 'cancelled', 'updated')"];
        $params = [];
        
        if (!empty($cert_type_filter)) {
            $where_conditions[] = "dt.id = ?";
            $params[] = intval($cert_type_filter);
        }
        
        if (!empty($company_search)) {
            $where_conditions[] = "(comp.trade_name LIKE ? OR comp.short_name LIKE ?)";
            $params[] = "%$company_search%";
            $params[] = "%$company_search%";
        }
        
        if (!empty($document_number_search)) {
            $where_conditions[] = "c.document_number LIKE ?";
            $params[] = "%$document_number_search%";
        }
        
        if (!empty($consultant_search)) {
            $where_conditions[] = "(cons.company_short_name LIKE ? OR cons.company_full_name LIKE ? OR cons.consultant_name LIKE ?)";
            $params[] = "%$consultant_search%";
            $params[] = "%$consultant_search%";
            $params[] = "%$consultant_search%";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
         
        $sql = "SELECT DISTINCT
            c.id,
            COALESCE(comp.trade_name, comp.short_name) as company_name,
            dt.name as cert_type,
            dt.standard,
            c.document_number as cert_number,
            c.issue_date as start_date,
            DATE_SUB(c.expiry_date, INTERVAL 1 DAY) as expiry_date,
            c.status as cert_status,
            c.scope,
            comp.contact_email,
            CASE 
                WHEN DATE_SUB(c.expiry_date, INTERVAL 1 DAY) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring'
                ELSE 'active'
            END as status_color,
            DATEDIFF(DATE_SUB(c.expiry_date, INTERVAL 1 DAY), CURDATE()) as days_remaining,
            dt.interim_audit_count as inspection_count,
            cons.id as consultant_id,
            cons.company_short_name as consultant_company_short_name,
            cons.company_full_name as consultant_company_full_name,
            cons.consultant_name as consultant_name,
            cons.consultant_email as consultant_email,
            cons.consultant_phone as consultant_phone,
            cons.company_email as consultant_company_email,
            cons.company_phone as consultant_company_phone,
            comp.contact_phone as company_contact_phone,
            comp.trade_name as trade_name,
            comp.short_name as short_name,
            dt.name as doc_type_name
        FROM certifications c
        JOIN companies comp ON c.company_id = comp.id
        JOIN document_types dt ON c.document_type_id = dt.id
        LEFT JOIN consultants cons ON c.consultant_id = cons.id
        WHERE $where_clause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
         
        foreach ($documents as &$doc) {
            try {
                $doc['inspection_status'] = getInspectionStatus($doc['id']);
            } catch (Exception $e) {
                error_log("Tetkik durumu alınamadı (ID: {$doc['id']}): " . $e->getMessage());
                $doc['inspection_status'] = [
                    'inspection_1' => ['status' => 'bekliyor', 'completed_date' => null, 'planned_date' => null],
                    'inspection_2' => ['status' => 'bekliyor', 'completed_date' => null, 'planned_date' => null]
                ];
            }
            
            $inspectionDates = calculateInspectionDates($doc['start_date'], $doc['expiry_date'], $doc['inspection_count']);
            $closestDays = null;
            $now = new DateTime();
            
            $expiryDate = new DateTime($doc['expiry_date']);
            $expiryDiff = $now->diff($expiryDate);
            $expiryDays = $expiryDate >= $now ? $expiryDiff->days : -$expiryDiff->days;
            $closestDays = $expiryDays;
            
            if ($inspectionDates['inspection1']) {
                $inspection1Status = $doc['inspection_status']['inspection_1'];
                if ($inspection1Status['status'] == 'planlandi' && $inspection1Status['planned_date']) {
                    $plannedDate = new DateTime($inspection1Status['planned_date']);
                    $plannedDiff = $now->diff($plannedDate);
                    $plannedDays = $plannedDate >= $now ? $plannedDiff->days : -$plannedDiff->days;
                    if ($closestDays === null || abs($plannedDays) < abs($closestDays)) {
                        $closestDays = $plannedDays;
                    }
                } elseif ($inspection1Status['status'] == 'bekliyor') {
                    $inspection1Diff = $now->diff($inspectionDates['inspection1']);
                    $inspection1Days = $inspectionDates['inspection1'] >= $now ? $inspection1Diff->days : -$inspection1Diff->days;
                    if ($closestDays === null || abs($inspection1Days) < abs($closestDays)) {
                        $closestDays = $inspection1Days;
                    }
                }
            }
            
            if ($inspectionDates['inspection2']) {
                $inspection2Status = $doc['inspection_status']['inspection_2'];
                if ($inspection2Status['status'] == 'planlandi' && $inspection2Status['planned_date']) {
                    $plannedDate = new DateTime($inspection2Status['planned_date']);
                    $plannedDiff = $now->diff($plannedDate);
                    $plannedDays = $plannedDate >= $now ? $plannedDiff->days : -$plannedDiff->days;
                    if ($closestDays === null || abs($plannedDays) < abs($closestDays)) {
                        $closestDays = $plannedDays;
                    }
                } elseif ($inspection2Status['status'] == 'bekliyor') {
                    $inspection2Diff = $now->diff($inspectionDates['inspection2']);
                    $inspection2Days = $inspectionDates['inspection2'] >= $now ? $inspection2Diff->days : -$inspection2Diff->days;
                    if ($closestDays === null || abs($inspection2Days) < abs($closestDays)) {
                        $closestDays = $inspection2Days;
                    }
                }
            }
            
            $doc['closest_days'] = $closestDays;
        }
        
    
        if (!empty($day_filter) && is_numeric($day_filter)) {
            $dayLimit = intval($day_filter);
            $documents = array_filter($documents, function($doc) use ($dayLimit) {
                $days = $doc['closest_days'];
                if ($days === null) return false;
                return $days >= 0 && $days <= $dayLimit;
            });
        }
         
        usort($documents, function($a, $b) use ($sort_by) {
         
            $statusA = $a['cert_status'] === 'inactive' ? 1 : 0;
            $statusB = $b['cert_status'] === 'inactive' ? 1 : 0;
            if ($statusA !== $statusB) return $statusA - $statusB;
             
            switch ($sort_by) {
                case 'expiry_date_asc':
                    return strtotime($a['expiry_date']) - strtotime($b['expiry_date']);
                case 'expiry_date_desc':
                    return strtotime($b['expiry_date']) - strtotime($a['expiry_date']);
                case 'company_name_asc':
                    $valA = strtolower($a['trade_name'] . ' ' . $a['short_name']);
                    $valB = strtolower($b['trade_name'] . ' ' . $b['short_name']);
                    return strcmp($valA, $valB);
                case 'company_name_desc':
                    $valA = strtolower($a['trade_name'] . ' ' . $a['short_name']);
                    $valB = strtolower($b['trade_name'] . ' ' . $b['short_name']);
                    return strcmp($valB, $valA);
                case 'issue_date_asc':
                    return strtotime($a['start_date']) - strtotime($b['start_date']);
                case 'issue_date_desc':
                    return strtotime($b['start_date']) - strtotime($a['start_date']);
                case 'cert_type_asc':
                    return strcmp(strtolower($a['doc_type_name']), strtolower($b['doc_type_name']));
                case 'cert_type_desc':
                    return strcmp(strtolower($b['doc_type_name']), strtolower($a['doc_type_name']));
                case 'closest_date_asc':
                default:
                    $daysA = $a['closest_days'];
                    $daysB = $b['closest_days'];
                    if ($daysA === null && $daysB === null) return 0;
                    if ($daysA === null) return 1;
                    if ($daysB === null) return -1;
                    if ($daysA < 0 && $daysB >= 0) return -1;
                    if ($daysA >= 0 && $daysB < 0) return 1;
                    if ($daysA < 0 && $daysB < 0) return $daysB - $daysA;
                    return $daysA - $daysB;
            }
        });
        
        return array_values($documents);
        
    } catch (Exception $e) {
        error_log("Belge listesi hatası: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

function getCertTypes() {
    try {
        $pdo = getConnection();
        $sql = "SELECT id, name, standard FROM document_types ORDER BY name";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Belge türleri hatası: " . $e->getMessage());
        return [];
    }
}

function getCompanies() {
    try {
        $pdo = getConnection();
        $sql = "SELECT DISTINCT comp.id, COALESCE(comp.trade_name, comp.short_name) as company_name 
                FROM companies comp 
                JOIN certifications c ON comp.id = c.company_id 
                ORDER BY company_name";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Firmalar hatası: " . $e->getMessage());
        return [];
    }
}

function getConsultants() {
    try {
        $pdo = getConnection();
        $sql = "SELECT 
                    id, 
                    company_short_name as full_name,
                    company_email as email,
                    consultant_name,
                    consultant_email
                FROM consultants 
                WHERE status = 'active'
                ORDER BY company_short_name";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Danışmanlar hatası: " . $e->getMessage());
        return [];
    }
}

function getDocumentNumbers($search = '') {
    try {
        $pdo = getConnection();
        $where_clause = '';
        $params = [];
        
        if (!empty($search)) {
            $where_clause = "WHERE c.document_number LIKE ?";
            $params[] = "%$search%";
        }
        
        $sql = "SELECT DISTINCT c.document_number 
                FROM certifications c 
                $where_clause
                ORDER BY c.document_number 
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (Exception $e) {
        error_log("Belge numaraları hatası: " . $e->getMessage());
        return [];
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    header('Content-Type: application/json');
    try {
        $company_search = isset($_GET['company_search']) ? sanitizeInput($_GET['company_search']) : '';
        $cert_type_filter = isset($_GET['cert_type']) ? sanitizeInput($_GET['cert_type']) : '';
        $document_number_search = isset($_GET['document_number']) ? sanitizeInput($_GET['document_number']) : '';
        $consultant_search = isset($_GET['consultant_search']) ? sanitizeInput($_GET['consultant_search']) : '';
        $sort_by = isset($_GET['sort_by']) ? sanitizeInput($_GET['sort_by']) : 'expiry_date_asc';
        $day_filter = isset($_GET['day_filter']) ? sanitizeInput($_GET['day_filter']) : '';

        $documents = getDocuments($cert_type_filter, $sort_by, $company_search, $document_number_search, $consultant_search, $day_filter);

        $stats = [
            'total' => count($documents),
            'expiring' => 0,
            'expired' => 0,
            'active' => 0
        ];

        $html = '';
        if (count($documents) > 0) {
            foreach ($documents as $doc) {
                if (in_array($doc['cert_status'], ['active', 'updated'])) {
                    if ($doc['days_remaining'] < 0) {
                        $stats['expired']++;
                    } elseif ($doc['days_remaining'] <= 30) {
                        $stats['expiring']++;
                    } else {
                        $stats['active']++;
                    }
                }

                $inspectionDates = calculateInspectionDates($doc['start_date'], $doc['expiry_date'], $doc['inspection_count']);
                
                $html .= '<tr>';
                $html .= '<td>';
                $html .= '<div class="company-name">' . htmlspecialchars($doc['company_name']) . '</div>';
                $html .= '<div class="cert-type">' . htmlspecialchars($doc['standard']) . '</div>';
                $html .= '</td>';
                $html .= '<td><div class="cert-type">' . htmlspecialchars($doc['cert_type']) . '</div></td>';
                $html .= '<td><div class="cert-number">' . htmlspecialchars($doc['cert_number']) . '</div></td>';
                $html .= '<td><div class="date-cell"><div class="date-main">' . date('d.m.Y', strtotime($doc['start_date'])) . '</div></div></td>';                                

                $html .= '<td><div class="date-cell">';
                $inspectionDisplay1 = getInspectionDisplayDate($inspectionDates, $doc['inspection_status'], 1);

                if ($inspectionDisplay1['date'] == 'tamamlandı') {
                    $html .= '<div class="date-main">tamamlandı</div>';
                    $html .= '<div class="date-info ' . $inspectionDisplay1['class'] . '">tamamlandı</div>';
                } elseif ($inspectionDisplay1['date'] != '-') {
                    $html .= '<a href="examination.php?doc_id=' . $doc['id'] . '&type=1" class="inspection-link"' . ($isReadOnlyUser ? ' onclick="return handleRestricted(event)"' : '') . '>';
                    $html .= '<div class="date-main">' . $inspectionDisplay1['date'] . '</div>';
                    if ($inspectionDisplay1['info']) {
                        $html .= '<div class="date-info ' . $inspectionDisplay1['class'] . '">' . $inspectionDisplay1['info'] . '</div>';
                    }
                    $html .= '</a>';
                } else {
                    $html .= '<div class="date-main">-</div>';
                }
                $html .= '</div></td>';
                $html .= '<td><div class="date-cell">';
                $inspectionDisplay2 = getInspectionDisplayDate($inspectionDates, $doc['inspection_status'], 2);
                if ($inspectionDisplay2['date'] == 'tamamlandı') {
                    $html .= '<div class="date-main">tamamlandı</div>';
                    $html .= '<div class="date-info ' . $inspectionDisplay2['class'] . '">tamamlandı</div>';
                } elseif ($inspectionDisplay2['date'] != '-') {
                    $html .= '<a href="examination.php?doc_id=' . $doc['id'] . '&type=2" class="inspection-link"' . ($isReadOnlyUser ? ' onclick="return handleRestricted(event)"' : '') . '>';
                    $html .= '<div class="date-main">' . $inspectionDisplay2['date'] . '</div>';
                    if ($inspectionDisplay2['info']) {
                        $html .= '<div class="date-info ' . $inspectionDisplay2['class'] . '">' . $inspectionDisplay2['info'] . '</div>';
                    }
                    $html .= '</a>';
                } else {
                    $html .= '<div class="date-main">-</div>';
                }
                $html .= '</div></td>';
                $html .= '<td><div class="date-cell">';
                $html .= '<div class="date-main">' . date('d.m.Y', strtotime($doc['expiry_date'])) . '</div>';
                if ($doc['days_remaining'] !== null) {
                    $statusClass = '';
                    if ($doc['days_remaining'] < 0) $statusClass = 'date-danger';
                    elseif ($doc['days_remaining'] <= 30) $statusClass = 'date-warning';
                    else $statusClass = 'date-active';
                    
                    $html .= '<div class="date-info ' . $statusClass . '">';
                    $inspectionCount = isset($doc['inspection_count']) ? (int)$doc['inspection_count'] : 0;
                    $inspection1Status = isset($doc['inspection_status']['inspection_1']['status']) ? $doc['inspection_status']['inspection_1']['status'] : 'bekliyor';
                    $inspection2Status = isset($doc['inspection_status']['inspection_2']['status']) ? $doc['inspection_status']['inspection_2']['status'] : 'bekliyor';
                    $requiredCompleted = false;
                    if ($inspectionCount >= 2) {
                        $requiredCompleted = ($inspection1Status === 'tamamlandi' && $inspection2Status === 'tamamlandi');
                    } elseif ($inspectionCount === 1) {
                        $requiredCompleted = ($inspection1Status === 'tamamlandi');
                    }
                    if ($requiredCompleted) {
                        $html .= 'tamamlandı';
                    } else {
                        if ($doc['days_remaining'] < 0) {
                            $html .= abs($doc['days_remaining']) . ' gün geçmiş';
                        } else {
                            $html .= $doc['days_remaining'] . ' gün kaldı';
                        }
                    }
                    $html .= '</div>';
                }
                $html .= '</div></td>';
                $html .= '<td>';
                $statusClass = '';
                $statusText = '';
                
                switch($doc['cert_status']) {
                    case 'active':
                        if ($doc['days_remaining'] < 0) {
                            $statusClass = 'date-expired';
                            $statusText = 'Süresi Geçmiş';
                        } elseif ($doc['days_remaining'] <= 30) {
                            $statusClass = 'date-warning';
                            $statusText = 'Süresi Yaklaşıyor';
                        } else {
                            $statusClass = 'date-active';
                            $statusText = 'Aktif';
                        }
                        break;
                    case 'inactive':
                        $statusClass = 'date-passive';
                        $statusText = 'Pasif';
                        break;
                    case 'suspended':
                        $statusClass = 'date-suspended';
                        $statusText = 'Askıda';
                        break;
                    case 'cancelled':
                        $statusClass = 'date-cancelled';
                        $statusText = 'İptal';
                        break;
                    case 'updated':
                        $statusClass = 'date-updated';
                        $statusText = 'Güncelleme';
                        break;
                    default:
                        $statusClass = 'date-active';
                        $statusText = 'Aktif';
                }
                
                $html .= '<div class="date-info ' . $statusClass . ' clickable-status" '
                      . 'onclick="showStatusDropdown(this, ' . $doc['id'] . ', \'' . $doc['cert_status'] . '\')">'
                      . $statusText . '</div>';
                $html .= '</td>';
                $html .= '<td>';
                if ($doc['contact_email']) {
                    $html .= '<a href="contact.php?email=' . urlencode($doc['contact_email']) . '&company=' . urlencode($doc['company_name']) . '" class="contact-button"' . ($isReadOnlyUser ? ' onclick="return handleRestricted(event)"' : '') . '>İletişim</a>';
                } else {
                    $html .= '<span style="color: #999;">-</span>';
                }
                $html .= '</td>';
                $html .= '<td>';
                if ($doc['consultant_id']) {
                    $consultantData = [
                        'id' => $doc['consultant_id'],
                        'name' => $doc['consultant_company_short_name'],
                        'company_name' => $doc['consultant_company_full_name'],
                        'consultant_name' => $doc['consultant_name'],
                        'consultant_email' => $doc['consultant_email'],
                        'consultant_phone' => $doc['consultant_phone'],
                        'company_email' => $doc['consultant_company_email'],
                        'company_phone' => $doc['consultant_company_phone']
                    ];
                    $html .= '<div class="consultant-info" onclick="' . ($isReadOnlyUser ? 'return handleRestricted(event)' : 'showConsultantPopup(' . htmlspecialchars(json_encode($consultantData)) . ')') . '">';
                    $html .= '<span class="consultant-button">👤 Danışman</span>';
                    $html .= '</div>';
                } else {
                    $html .= '<span style="color: #999;">-</span>';
                }
                $html .= '</td>';

                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="10" class="no-data"><div class="no-data-icon">📋</div><h3>Belge bulunamadı</h3><p>Arama kriterlerinize uygun belge bulunamadı.</p></td></tr>';
        }

        echo json_encode(['html' => $html, 'count' => count($documents), 'stats' => $stats]);
    } catch (Exception $e) {
        error_log("Ajax search error: " . $e->getMessage());
        echo json_encode(['html' => '<tr><td colspan="10" class="no-data">Bir hata oluştu.</td></tr>', 'count' => 0, 'stats' => ['total' => 0, 'expiring' => 0, 'expired' => 0, 'active' => 0]]);
    }
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'search_documents') {
    header('Content-Type: application/json');
    try {
        $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
        $documentNumbers = getDocumentNumbers($search);
        echo json_encode($documentNumbers);
    } catch (Exception $e) {
        error_log("Ajax search documents error: " . $e->getMessage());
        echo json_encode([]);
    }
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'search_companies') {
    header('Content-Type: application/json');
    try {
        $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
        $companies = getCompanies();
        if (!empty($search)) {
            $companies = array_filter($companies, function($company) use ($search) {
                return stripos($company['company_name'], $search) !== false;
            });
        }
        echo json_encode(array_values($companies));
    } catch (Exception $e) {
        error_log("Ajax search companies error: " . $e->getMessage());
        echo json_encode([]);
    }
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'search_consultants') {
    header('Content-Type: application/json');
    try {
        $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
        $consultants = getConsultants();
        if (!empty($search)) {
            $consultants = array_filter($consultants, function($consultant) use ($search) {
                return stripos($consultant['full_name'], $search) !== false;
            });
        }
        echo json_encode(array_values($consultants));
    } catch (Exception $e) {
        error_log("Ajax search consultants error: " . $e->getMessage());
        echo json_encode([]);
    }
    exit();
}

$documents = getDocuments($cert_type_filter, $sort_by, $company_search, $document_number_search, $consultant_search, $day_filter);
$cert_types = getCertTypes();
$companies = getCompanies();
$consultants = getConsultants();

if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    try {
        $day_filter = isset($_GET['day_filter']) ? sanitizeInput($_GET['day_filter']) : '';
        
        $documents = getDocuments($cert_type_filter, $sort_by, $company_search, $document_number_search, $consultant_search, $day_filter);
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="belge_listesi_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo "\xEF\xBB\xBF";
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Firma Adı</th>';
        echo '<th>Belge Türü</th>';
        echo '<th>Standart</th>';
        echo '<th>Belge No</th>';
        echo '<th>Başlangıç</th>';
        echo '<th>Tetkik 1</th>';
        echo '<th>Tetkik 2</th>';
        echo '<th>Bitiş Tarihi</th>';
        echo '<th>Durum</th>';
        echo '<th>En Yakın Tarih (Gün)</th>';
        echo '<th>Kapsam</th>';
        echo '<th>İletişim</th>';
        echo '<th>Danışman</th>';
        echo '</tr>';
        
        foreach ($documents as $doc) {
            $inspectionDates = calculateInspectionDates($doc['start_date'], $doc['expiry_date'], $doc['inspection_count']);
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($doc['company_name']) . '</td>';
            echo '<td>' . htmlspecialchars($doc['cert_type']) . '</td>';
            echo '<td>' . htmlspecialchars($doc['standard']) . '</td>';
            echo '<td>' . htmlspecialchars($doc['cert_number']) . '</td>';
            echo '<td>' . htmlspecialchars($doc['start_date']) . '</td>';
            
            $inspectionDisplay1 = getInspectionDisplayDate($inspectionDates, $doc['inspection_status'], 1);
            $inspectionText1 = '';
            if ($inspectionDisplay1['date'] == 'tamamlandı') {
                $inspectionText1 = 'tamamlandı';
            } elseif ($inspectionDisplay1['info']) {
                if (strpos($inspectionDisplay1['info'], 'tamamlandı') !== false) {
                    $inspectionText1 = 'tamamlandı';
                } elseif (strpos($inspectionDisplay1['info'], 'Planlandı') !== false) {
                    $inspectionText1 = $inspectionDisplay1['info'];
                } elseif (strpos($inspectionDisplay1['info'], 'İptal') !== false) {
                    $inspectionText1 = 'İptal';
                } else {
                    $inspectionText1 = $inspectionDisplay1['date'];
                }
            } else {
                $inspectionText1 = $inspectionDisplay1['date'];
            }
            echo '<td>' . htmlspecialchars($inspectionText1) . '</td>';

            $inspectionDisplay2 = getInspectionDisplayDate($inspectionDates, $doc['inspection_status'], 2);
            $inspectionText2 = '';
            if ($inspectionDisplay2['date'] == 'tamamlandı') {
                $inspectionText2 = 'tamamlandı';
            } elseif ($inspectionDisplay2['info']) {
                if (strpos($inspectionDisplay2['info'], 'tamamlandı') !== false) {
                    $inspectionText2 = 'tamamlandı';
                } elseif (strpos($inspectionDisplay2['info'], 'Planlandı') !== false) {
                    $inspectionText2 = $inspectionDisplay2['info'];
                } elseif (strpos($inspectionDisplay2['info'], 'İptal') !== false) {
                    $inspectionText2 = 'İptal';
                } else {
                    $inspectionText2 = $inspectionDisplay2['date'];
                }
            } else {
                $inspectionText2 = $inspectionDisplay2['date'];
            }
            echo '<td>' . htmlspecialchars($inspectionText2) . '</td>';
            
            echo '<td>' . htmlspecialchars($doc['expiry_date']) . '</td>';
            echo '<td>' . htmlspecialchars($doc['cert_status']) . '</td>';
            
            $closestDaysText = '';
            if (isset($doc['closest_days']) && $doc['closest_days'] !== null) {
                $days = $doc['closest_days'];
                if ($days < 0) {
                    $closestDaysText = abs($days) . ' gün geçmiş';
                } elseif ($days == 0) {
                    $closestDaysText = 'Bugün';
                } else {
                    $closestDaysText = $days . ' gün kaldı';
                }
            } else {
                $closestDaysText = '-';
            }
            echo '<td>' . htmlspecialchars($closestDaysText) . '</td>';
            
            echo '<td>' . htmlspecialchars($doc['scope'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($doc['contact_email'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($doc['consultant_company_short_name'] ?? '-') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } catch (Exception $e) {
        error_log("Excel export error: " . $e->getMessage());
        echo "Excel export hatası oluştu.";
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme - Belge Takibi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            height: 50px;
        }

        .logo img {
            height: 300%;
            width: auto;
            max-height: 500px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            background: rgba(102, 126, 234, 0.1);
        }

        .nav-links a:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3em;
            font-weight: 800;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
            font-weight: 500;
        }

        .controls-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 100;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .filter-group select,
        .filter-group input {
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .searchable-select {
            position: relative;
        }

        .searchable-select input {
            width: 100%;
            cursor: pointer;
        }

        .searchable-select .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e1e8ed;
            border-top: none;
            border-radius: 0 0 12px 12px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 99999;
            display: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }

        .searchable-select .dropdown-list.show {
            display: block;
            z-index: 99999;
        }

        .searchable-select .dropdown-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }

        .searchable-select .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .searchable-select .dropdown-item.selected {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .filter-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: linear-gradient(45deg, #48bb78, #38a169);
            color: white;
        }
        

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(72, 187, 120, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #718096, #4a5568);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(113, 128, 150, 0.3);
        }

        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table th {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 18px 12px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .data-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
            vertical-align: top;
        }

        .data-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .date-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .date-main {
            font-weight: 600;
            color: #333;
        }

        .date-info {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: 500;
        }

        .date-active {
            background: #c6f6d5;
            color: #276749;
        }

        .date-warning {
            background: #feebc8;
            color: #c05621;
        }

        .date-danger {
            background: #fed7d7;
            color: #c53030;
        }

        .date-expired {
            background: #e2e8f0;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .date-expired:hover {
            background: #cbd5e0;
            transform: translateY(-1px);
        }

        .date-passive {
            background: #fff3cd;
            color: #856404;
        }

        .date-suspended {
            background: #f8d7da;
            color: #721c24;
        }

        .date-cancelled {
            background: #e2e8f0;
            color: #4a5568;
        }

        .date-updated {
            background: #d1ecf1;
            color: #0c5460;
        }

        .clickable-status {
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease;
        }

        .clickable-status:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .date-upcoming {
            background: #e6fffa;
            color: #285e61;
        }

        .contact-button, .consultant-button {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .contact-button:hover, .consultant-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .consultant-info {
            cursor: pointer;
        }

        .inspection-link {
            color: inherit;
            text-decoration: none;
            display: block;
        }

        .inspection-link:hover {
            opacity: 0.8;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-data-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-data h3 {
            margin-bottom: 10px;
            color: #555;
        }

        .company-name {
            font-weight: 600;
            color: #333;
        }

        .cert-type {
            font-weight: 500;
            color: #667eea;
        }

        .cert-number {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-dropdown {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            min-width: 300px;
            max-width: 400px;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-item.current {
            background-color: #e3f2fd;
            border-left: 3px solid #2196f3;
        }

        .status-icon {
            font-size: 16px;
            margin-right: 12px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .dropdown-item strong {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #333;
        }

        .dropdown-item small {
            display: block;
            font-size: 12px;
            line-height: 1.4;
            color: #666;
            font-weight: normal;
        }

        .date-info.updating {
            background-color: #ccc !important;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .consultant-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            animation: fadeInModal 0.3s ease-out;
        }

        .consultant-popup.show {
            display: flex;
        }

        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .consultant-popup-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: slideInModal 0.3s ease-out;
            position: relative;
        }

        @keyframes slideInModal {
            from { transform: translateY(-50px) scale(0.9); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .consultant-popup-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .consultant-popup-header .icon {
            font-size: 2em;
            margin-right: 15px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .consultant-popup-header h3 {
            color: #333;
            font-size: 1.5em;
            margin: 0;
        }

        .consultant-info-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .consultant-info-item .label {
            font-weight: 600;
            color: #555;
            min-width: 80px;
            margin-right: 15px;
        }

        .consultant-info-item .value {
            color: #333;
            flex: 1;
        }

        .consultant-popup-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .consultant-close-btn {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e9ecef;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .consultant-close-btn:hover {
            background: #e9ecef;
            color: #495057;
        }

        .consultant-mail-btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .consultant-mail-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
            
            .data-table {
                font-size: 12px;
            }
            
            .data-table th,
            .data-table td {
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="logo">
                    <h2 style="margin:0;">Belgelendirme</h2>
                </div>
                <div class="nav-links">
                    <a href="dashboard.php">🏠 Ana Sayfa</a>
                    
                    <a href="mail_templates.php"<?php echo $isReadOnlyUser ? ' onclick="return handleRestricted(event)"' : ''; ?>>✉️ Mail Şablonları</a>
                    <a href="dashboard.php?logout=1">🚪 Çıkış</a>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="total-docs"><?php echo count($documents); ?></div>
                <div class="stat-label">Toplam Belge</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="expiring-docs">
                    <?php 
                    $expiring = array_filter($documents, function($doc) {
                        return $doc['days_remaining'] > 0 && $doc['days_remaining'] <= 30 && in_array($doc['cert_status'], ['active', 'updated']);
                    });
                    echo count($expiring);
                    ?>
                </div>
                <div class="stat-label">30 Gün İçinde</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="expired-docs">
                    <?php 
                    $expired = array_filter($documents, function($doc) {
                        return $doc['days_remaining'] < 0 && in_array($doc['cert_status'], ['active', 'updated']);
                    });
                    echo count($expired);
                    ?>
                </div>
                <div class="stat-label">Geçmiş</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="active-docs">
                    <?php 
                    $active = array_filter($documents, function($doc) {
                        return $doc['days_remaining'] > 30 && in_array($doc['cert_status'], ['active', 'updated']);
                    });
                    echo count($active);
                    ?>
                </div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>

        <div class="controls-section">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Firma Adı</label>
                    <div class="searchable-select">
                        <input type="text" id="company-search" placeholder="Firma adı yazarak arayın..." autocomplete="off" value="<?php echo htmlspecialchars($company_search); ?>">
                        <div class="dropdown-list" id="company-dropdown">
                            <?php foreach ($companies as $company): ?>
                                <div class="dropdown-item" data-value="<?php echo htmlspecialchars($company['company_name']); ?>">
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Belge Türü</label>
                    <div class="searchable-select">
                        <input type="text" id="cert-type-search" placeholder="Belge türü yazarak arayın..." autocomplete="off" 
                               value="<?php 
                               if ($cert_type_filter) {
                                   foreach ($cert_types as $type) {
                                       if ($type['id'] == $cert_type_filter) {
                                           echo htmlspecialchars($type['name']);
                                           break;
                                       }
                                   }
                               }
                               ?>">
                        <input type="hidden" id="cert-type-value" value="<?php echo htmlspecialchars($cert_type_filter); ?>">
                        <div class="dropdown-list" id="cert-type-dropdown">
                            <?php foreach ($cert_types as $type): ?>
                                <div class="dropdown-item" data-value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Belge No</label>
                    <div class="searchable-select">
                        <input type="text" id="document-number-search" placeholder="Belge numarası yazarak arayın..." autocomplete="off" value="<?php echo htmlspecialchars($document_number_search); ?>">
                        <div class="dropdown-list" id="document-number-dropdown">
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Danışman</label>
                    <div class="searchable-select">
                        <input type="text" id="consultant-search" placeholder="Danışman adı yazarak arayın..." autocomplete="off" value="<?php echo htmlspecialchars($consultant_search); ?>">
                        <div class="dropdown-list" id="consultant-dropdown">
                            <?php foreach ($consultants as $consultant): ?>
                                <div class="dropdown-item" data-value="<?php echo htmlspecialchars($consultant['full_name']); ?>">
                                    <?php echo htmlspecialchars($consultant['full_name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Gün Filtresi</label>
                    <input type="number" id="day-filter" placeholder="Gün sayısı" min="0" value="<?php echo htmlspecialchars($day_filter); ?>">
                </div>

                <div class="filter-group">
                    <label>Sıralama</label>
                    <select id="sort-select">
                        <option value="closest_date_asc" <?php echo ($sort_by == 'closest_date_asc') ? 'selected' : ''; ?>>En Yakın Tarih</option>
                        <option value="expiry_date_asc" <?php echo ($sort_by == 'expiry_date_asc') ? 'selected' : ''; ?>>En Yakın Bitiş</option>
                        <option value="expiry_date_desc" <?php echo ($sort_by == 'expiry_date_desc') ? 'selected' : ''; ?>>En Uzak Bitiş</option>
                        <option value="company_name_asc" <?php echo ($sort_by == 'company_name_asc') ? 'selected' : ''; ?>>Firma Adı (A-Z)</option>
                        <option value="company_name_desc" <?php echo ($sort_by == 'company_name_desc') ? 'selected' : ''; ?>>Firma Adı (Z-A)</option>
                        <option value="cert_type_asc" <?php echo ($sort_by == 'cert_type_asc') ? 'selected' : ''; ?>>Belge Türü (A-Z)</option>
                        <option value="cert_type_desc" <?php echo ($sort_by == 'cert_type_desc') ? 'selected' : ''; ?>>Belge Türü (Z-A)</option>
                        <option value="issue_date_asc" <?php echo ($sort_by == 'issue_date_asc') ? 'selected' : ''; ?>>Veriliş Tarihi (Eskiden Yeniye)</option>
                        <option value="issue_date_desc" <?php echo ($sort_by == 'issue_date_desc') ? 'selected' : ''; ?>>Veriliş Tarihi (Yeniden Eskiye)</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn btn-secondary" onclick="clearFilters()">🔄 Filtreleri Temizle</button>
                <a href="javascript:void(0)" onclick="exportToExcel()" class="btn btn-success">📊 Excel'e Aktar</a>
            </div>
        </div>

        <div class="table-container">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Firma & Standart</th>
                            <th>Belge Türü</th>
                            <th>Belge No</th>
                            <th>Başlangıç</th>
                            <th>1. Tetkik</th>
                            <th>2. Tetkik</th>
                            <th>Bitiş Tarihi</th>
                            <th>Durum</th>
                            <th>İletişim</th>
                            <th>Danışman</th>
                        </tr>
                    </thead>
                    <tbody id="documents-table">
                        <?php if (count($documents) > 0): ?>
                            <?php foreach ($documents as $doc): ?>
                                <?php $inspectionDates = calculateInspectionDates($doc['start_date'], $doc['expiry_date'], $doc['inspection_count']); ?>
                                <tr>
                                    <td>
                                        <div class="company-name"><?php echo htmlspecialchars($doc['company_name']); ?></div>
                                        <div class="cert-type"><?php echo htmlspecialchars($doc['standard']); ?></div>
                                    </td>
                                    <td>
                                        <div class="cert-type"><?php echo htmlspecialchars($doc['cert_type']); ?></div>
                                    </td>
                                    <td>
                                        <div class="cert-number"><?php echo htmlspecialchars($doc['cert_number']); ?></div>
                                    </td>
                                    <td>
                                        <div class="date-cell">
                                            <div class="date-main"><?php echo date('d.m.Y', strtotime($doc['start_date'])); ?></div>
                                        </div>
                                    </td>                                

                                    <td>
                                        <div class="date-cell">
                                            <?php 
                                            $inspectionDisplay1 = getInspectionDisplayDate($inspectionDates, $doc['inspection_status'], 1);
                                            if ($inspectionDisplay1['date'] == 'tamamlandı'): 
                                            ?>
                                                <div class="date-main">tamamlandı</div>
                                                <div class="date-info <?php echo $inspectionDisplay1['class']; ?>">tamamlandı</div>
                                            <?php elseif ($inspectionDisplay1['date'] != '-'): 
                                            ?>
                                                <a href="examination.php?doc_id=<?php echo $doc['id']; ?>&type=1" class="inspection-link"<?php echo $isReadOnlyUser ? ' onclick="return handleRestricted(event)"' : ''; ?>>
                                                    <div class="date-main"><?php echo $inspectionDisplay1['date']; ?></div>
                                                    <?php if ($inspectionDisplay1['info']): ?>
                                                        <div class="date-info <?php echo $inspectionDisplay1['class']; ?>">
                                                            <?php echo $inspectionDisplay1['info']; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <div class="date-main">-</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="date-cell">
                                            <?php 
                                            $inspectionDisplay2 = getInspectionDisplayDate($inspectionDates, $doc['inspection_status'], 2);
                                            if ($inspectionDisplay2['date'] == 'tamamlandı'): 
                                            ?>
                                                <div class="date-main">tamamlandı</div>
                                                <div class="date-info <?php echo $inspectionDisplay2['class']; ?>">tamamlandı</div>
                                            <?php elseif ($inspectionDisplay2['date'] != '-'): 
                                            ?>
                                                <a href="examination.php?doc_id=<?php echo $doc['id']; ?>&type=2" class="inspection-link"<?php echo $isReadOnlyUser ? ' onclick="return handleRestricted(event)"' : ''; ?>>
                                                    <div class="date-main"><?php echo $inspectionDisplay2['date']; ?></div>
                                                    <?php if ($inspectionDisplay2['info']): ?>
                                                        <div class="date-info <?php echo $inspectionDisplay2['class']; ?>">
                                                            <?php echo $inspectionDisplay2['info']; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <div class="date-main">-</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="date-cell">
                                            <div class="date-main"><?php echo date('d.m.Y', strtotime($doc['expiry_date'])); ?></div>
                                            <?php if ($doc['days_remaining'] !== null): ?>
                                                <?php 
                                                $statusClass = '';
                                                if ($doc['days_remaining'] < 0) $statusClass = 'date-danger';
                                                elseif ($doc['days_remaining'] <= 30) $statusClass = 'date-warning';
                                                else $statusClass = 'date-active';
                                                ?>
                                                <div class="date-info <?php echo $statusClass; ?>">
                                                    <?php if ($doc['days_remaining'] < 0): ?>
                                                        <?php echo abs($doc['days_remaining']); ?> gün geçmiş
                                                    <?php else: ?>
                                                        <?php echo $doc['days_remaining']; ?> gün kaldı
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusText = '';
                                        
                                        switch($doc['cert_status']) {
                                            case 'active':
                                                if ($doc['days_remaining'] < 0) {
                                                    $statusClass = 'date-expired';
                                                    $statusText = 'Süresi Geçmiş';
                                                } elseif ($doc['days_remaining'] <= 30) {
                                                    $statusClass = 'date-warning';
                                                    $statusText = 'Süresi Yaklaşıyor';
                                                } else {
                                                    $statusClass = 'date-active';
                                                    $statusText = 'Aktif';
                                                }
                                                break;
                                            case 'inactive':
                                                $statusClass = 'date-passive';
                                                $statusText = 'Pasif';
                                                break;
                                            case 'suspended':
                                                $statusClass = 'date-suspended';
                                                $statusText = 'Askıda';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'date-cancelled';
                                                $statusText = 'İptal';
                                                break;
                                            case 'updated':
                                                $statusClass = 'date-updated';
                                                $statusText = 'Güncelleme';
                                                break;
                                            default:
                                                $statusClass = 'date-active';
                                                $statusText = 'Aktif';
                                        }
                                        ?>
                                        <div class="date-info <?php echo $statusClass; ?> clickable-status"
                                             onclick="showStatusDropdown(this, <?php echo $doc['id']; ?>, '<?php echo $doc['cert_status']; ?>')">
                                            <?php echo $statusText; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($doc['contact_email']): ?>
                                            <a href="contact.php?email=<?php echo urlencode($doc['contact_email']); ?>&company=<?php echo urlencode($doc['company_name']); ?>" class="contact-button"<?php echo $isReadOnlyUser ? ' onclick="return handleRestricted(event)"' : ''; ?>>
                                                İletişim
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($doc['consultant_id']): ?>
                                            <?php
                                            $consultantData = [
                                                'id' => $doc['consultant_id'],
                                                'name' => $doc['consultant_company_short_name'],
                                                'company_name' => $doc['consultant_company_full_name'],
                                                'consultant_name' => $doc['consultant_name'],
                                                'consultant_email' => $doc['consultant_email'],
                                                'consultant_phone' => $doc['consultant_phone'],
                                                'company_email' => $doc['consultant_company_email'],
                                                'company_phone' => $doc['consultant_company_phone']
                                            ];
                                            ?>
                                            <div class="consultant-info" onclick="<?php echo $isReadOnlyUser ? 'return handleRestricted(event)' : 'showConsultantPopup(' . htmlspecialchars(json_encode($consultantData)) . ')'; ?>">
                                                <span class="consultant-button">👤 Danışman</span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-data">
                                    <div class="no-data-icon">📋</div>
                                    <h3>Belge bulunamadı</h3>
                                    <p>Arama kriterlerinize uygun belge bulunamadı.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="statusDropdown" class="status-dropdown" style="display: none;">
        <div class="dropdown-item" data-status="active">
            <span class="status-icon">✓</span>
            <div>
                <strong>Aktif</strong>
                <small>Sertifika Geçerli</small>
            </div>
        </div>
        <div class="dropdown-item" data-status="passive">
            <span class="status-icon">⏸</span>
            <div>
                <strong>Pasif</strong>
                <small>Sertifika Ara Tetkik tarihi geçmiş ve sertifika geçerlilik dönemi içinde henüz ara tetkik planlanmamış</small>
            </div>
        </div>
        <div class="dropdown-item" data-status="suspended">
            <span class="status-icon">⚠</span>
            <div>
                <strong>Askıda</strong>
                <small>Sertifika için Ara Tetkik yaptırılmamış ve sertifika programına uygun hareket edilmemiş</small>
            </div>
        </div>
        <div class="dropdown-item" data-status="cancelled">
            <span class="status-icon">✗</span>
            <div>
                <strong>İptal</strong>
                <small>Uygunsuzluk Nedeniyle İptal/Müşteri İsteği İle iptal</small>
            </div>
        </div>
        <div class="dropdown-item" data-status="updated">
            <span class="status-icon">🔄</span>
            <div>
                <strong>Güncelleme</strong>
                <small>Sertifika geçerlilik süresinde bir nedenden (ünvan ve seviye değişikliği vb) dolayı güncellenmiş</small>
            </div>
        </div>
    </div>

    <div id="consultantPopup" class="consultant-popup">
        <div class="consultant-popup-content">
            <div class="consultant-popup-header">
                <span class="icon">👤</span>
                <h3>Danışman Bilgileri</h3>
            </div>
            <div id="consultantInfo"></div>
            <div class="consultant-popup-actions">
                <button class="consultant-close-btn" onclick="hideConsultantPopup()">Kapat</button>
                <a href="#" id="consultantCompanyMailBtn" class="consultant-mail-btn" style="display: none;">Firma Mail Gönder</a>
                <a href="#" id="consultantPersonMailBtn" class="consultant-mail-btn" style="display: none;">Danışman Mail Gönder</a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let searchTimeout;
        let currentRequest;
        let documentSearchTimeout;

        function performSearch() {
            const companySearch = document.getElementById('company-search').value.trim();
            const certTypeSearch = document.getElementById('cert-type-search').value.trim();
            const documentNumber = document.getElementById('document-number-search').value.trim();
            const consultantSearch = document.getElementById('consultant-search').value.trim();
            const sortBy = document.getElementById('sort-select').value;
            const dayFilter = document.getElementById('day-filter').value;
            
            let certTypeId = document.getElementById('cert-type-value').value || '';
            
            if (!certTypeId && certTypeSearch) {
                const certTypeItems = document.querySelectorAll('#cert-type-dropdown .dropdown-item');
                certTypeItems.forEach(item => {
                    if (item.textContent.trim() === certTypeSearch) {
                        certTypeId = item.dataset.value;
                        document.getElementById('cert-type-value').value = certTypeId;
                    }
                });
            }
            
            if (currentRequest && currentRequest.readyState !== 4) {
                currentRequest.abort();
            }
            
            document.getElementById('documents-table').innerHTML = `
                <tr>
                    <td colspan="10" class="loading">
                        <div class="loading-spinner"></div>
                        <p>Aranıyor...</p>
                    </td>
                </tr>
            `;
            
            currentRequest = new XMLHttpRequest();
            const params = new URLSearchParams({
                ajax: 'search',
                company_search: companySearch,
                cert_type: certTypeId,
                document_number: documentNumber,
                consultant_search: consultantSearch,
                sort_by: sortBy,
                day_filter: dayFilter
            });
            
            currentRequest.open('GET', `?${params.toString()}`, true);
            
            currentRequest.onreadystatechange = function() {
                if (currentRequest.readyState === 4) {
                    if (currentRequest.status === 200) {
                        try {
                            const response = JSON.parse(currentRequest.responseText);
                            document.getElementById('documents-table').innerHTML = response.html;
                            updateStats(response.stats);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            document.getElementById('documents-table').innerHTML = `
                                <tr>
                                    <td colspan="10" class="no-data">
                                        <div class="no-data-icon">⚠️</div>
                                        <h3>Hata oluştu</h3>
                                        <p>Arama sırasında bir hata oluştu.</p>
                                    </td>
                                </tr>
                            `;
                        }
                    } else if (currentRequest.status !== 0) {
                        document.getElementById('documents-table').innerHTML = `
                            <tr>
                                <td colspan="10" class="no-data">
                                    <div class="no-data-icon">⚠️</div>
                                    <h3>Bağlantı hatası</h3>
                                    <p>Sunucu ile bağlantı kurulamadı.</p>
                                </td>
                            </tr>
                        `;
                    }
                    currentRequest = null;
                }
            };
            
            currentRequest.send();
        }

        function initSearchableSelect(inputId, dropdownId, onSelectCallback = null) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            
            if (!input || !dropdown) {
                console.error(`Element not found: ${inputId} or ${dropdownId}`);
                return;
            }
            
            const items = dropdown.querySelectorAll('.dropdown-item');
            
            input.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-list').forEach(dd => {
                    if (dd !== dropdown) {
                        dd.classList.remove('show');
                    }
                });
                dropdown.classList.add('show');
                
                items.forEach(item => {
                    item.style.display = 'block';
                });
            });
            
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                let hasResults = false;
                
                if (inputId === 'document-number-search') {
                    clearTimeout(documentSearchTimeout);
                    if (searchTerm.length >= 1) {
                        documentSearchTimeout = setTimeout(() => searchDocumentNumbers(searchTerm), 300);
                    } else {
                        dropdown.classList.remove('show');
                    }
                    
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(onSelectCallback || performSearch, 500);
                    return;
                }
                
                items.forEach(item => {
                    const text = item.textContent.toLowerCase().trim();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'block';
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                dropdown.classList.add('show');
                
                if (onSelectCallback) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(onSelectCallback, 300);
                }
            });
            
            items.forEach(item => {
                item.addEventListener('click', function() {
                    input.value = this.textContent.trim();
                    dropdown.classList.remove('show');
                    
                    if (inputId === 'cert-type-search') {
                        document.getElementById('cert-type-value').value = this.dataset.value;
                    }
                    
                    if (onSelectCallback) {
                        onSelectCallback();
                    }
                });
            });
            
            document.addEventListener('click', function(e) {
                if (!input.parentElement.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }

        function searchDocumentNumbers(query) {
            if (query.length < 1) {
                document.getElementById('document-number-dropdown').classList.remove('show');
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('GET', `?ajax=search_documents&search=${encodeURIComponent(query)}`, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const suggestions = JSON.parse(xhr.responseText);
                        const dropdown = document.getElementById('document-number-dropdown');
                        
                        if (suggestions.length > 0) {
                            let html = '';
                            suggestions.forEach(function(docNumber) {
                                html += `<div class="dropdown-item" onclick="selectDocumentNumber('${docNumber}')">${docNumber}</div>`;
                            });
                            dropdown.innerHTML = html;
                            dropdown.classList.add('show');
                        } else {
                            dropdown.classList.remove('show');
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                    }
                }
            };
            xhr.send();
        }

        function selectDocumentNumber(docNumber) {
            document.getElementById('document-number-search').value = docNumber;
            document.getElementById('document-number-dropdown').classList.remove('show');
            performSearch();
        }

        function updateStats(stats) {
            document.getElementById('total-docs').textContent = stats.total;
            document.getElementById('expiring-docs').textContent = stats.expiring;
            document.getElementById('expired-docs').textContent = stats.expired;
            document.getElementById('active-docs').textContent = stats.active;
        }

        function clearFilters() {
            document.getElementById('company-search').value = '';
            document.getElementById('cert-type-search').value = '';
            document.getElementById('cert-type-value').value = '';
            document.getElementById('document-number-search').value = '';
            document.getElementById('consultant-search').value = '';
            document.getElementById('day-filter').value = '';
            
            document.getElementById('sort-select').value = 'closest_date_asc';
            
            document.querySelectorAll('.dropdown-list').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            
            performSearch();
        }

        function exportToExcel() {
            const companySearch = document.getElementById('company-search').value;
            const certTypeValue = document.getElementById('cert-type-value').value;
            const documentNumber = document.getElementById('document-number-search').value;
            const consultantSearch = document.getElementById('consultant-search').value;
            const sortBy = document.getElementById('sort-select').value;
            const dayFilter = document.getElementById('day-filter').value;
            
            const params = new URLSearchParams({
                export: 'excel',
                company_search: companySearch,
                cert_type: certTypeValue,
                document_number: documentNumber,
                consultant_search: consultantSearch,
                sort_by: sortBy,
                day_filter: dayFilter
            });
            
            window.location.href = '?' + params.toString();
        }

        let currentDropdown = null;
        let currentDocumentId = null;

        function showStatusDropdown(element, documentId, currentStatus) {
            hideStatusDropdown();
            
            const dropdown = document.getElementById('statusDropdown');
            currentDropdown = element;
            currentDocumentId = documentId;
            
            const items = dropdown.querySelectorAll('.dropdown-item');
            items.forEach(item => {
                item.classList.remove('current');
                if (item.dataset.status === currentStatus) {
                    item.classList.add('current');
                }
            });
            
            dropdown.style.display = 'block';
            
            const rect = element.getBoundingClientRect();
            const dropdownHeight = dropdown.offsetHeight;
            const viewportHeight = window.innerHeight;
            
            if (rect.bottom + dropdownHeight > viewportHeight) {
                dropdown.style.top = (rect.top + window.scrollY - dropdownHeight) + 'px';
            } else {
                dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
            }
            
            dropdown.style.left = rect.left + 'px';
            
            setTimeout(() => {
                document.addEventListener('click', handleOutsideClick);
                dropdown.addEventListener('click', handleDropdownClick);
            }, 0);
        }

        function hideStatusDropdown() {
            const dropdown = document.getElementById('statusDropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdown.removeEventListener('click', handleDropdownClick);
            }
            document.removeEventListener('click', handleOutsideClick);
            currentDropdown = null;
            currentDocumentId = null;
        }

        function handleDropdownClick(event) {
            event.stopPropagation();
            const item = event.target.closest('.dropdown-item');
            if (item && !item.classList.contains('current')) {
                const newStatus = item.dataset.status;
                updateDocumentStatus(currentDocumentId, newStatus);
            }
        }

        function handleOutsideClick(event) {
            const dropdown = document.getElementById('statusDropdown');
            if (dropdown && !dropdown.contains(event.target) && event.target !== currentDropdown) {
                hideStatusDropdown();
            }
        }

        function updateDocumentStatus(documentId, newStatus) {
            if (currentDropdown) {
                currentDropdown.textContent = 'Güncelleniyor...';
                currentDropdown.classList.add('updating');
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                hideStatusDropdown();
                                performSearch();
                            } else {
                                alert('Güncelleme başarısız: ' + response.message);
                                resetStatusDisplay();
                            }
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            alert('Bir hata oluştu');
                            resetStatusDisplay();
                        }
                    } else {
                        alert('Sunucu hatası');
                        resetStatusDisplay();
                    }
                }
            };
            
            const postData = 'ajax=update_status&document_id=' + encodeURIComponent(documentId) + 
                             '&newStatus=' + encodeURIComponent(newStatus);
            
            xhr.send(postData);
        }

        function resetStatusDisplay() {
            if (currentDropdown) {
                currentDropdown.classList.remove('updating');
                hideStatusDropdown();
            }
        }

        function showConsultantPopup(consultantData) {
            const popup = document.getElementById('consultantPopup');
            const infoDiv = document.getElementById('consultantInfo');
            const companyMailBtn = document.getElementById('consultantCompanyMailBtn');
            const personMailBtn = document.getElementById('consultantPersonMailBtn');
            
            let infoHtml = `
                <div class="consultant-info-item">
                    <span class="label">Firma Kısa Adı:</span>
                    <span class="value">${consultantData.name}</span>
                </div>
            `;
            
            if (consultantData.company_name) {
                infoHtml += `
                    <div class="consultant-info-item">
                        <span class="label">Firma Uzun Adı:</span>
                        <span class="value">${consultantData.company_name}</span>
                    </div>
                `;
            }
            
            if (consultantData.consultant_name) {
                infoHtml += `
                    <div class="consultant-info-item">
                        <span class="label">Danışman Adı:</span>
                        <span class="value">${consultantData.consultant_name}</span>
                    </div>
                `;
            }
            
            if (consultantData.company_email) {
                infoHtml += `
                    <div class="consultant-info-item">
                        <span class="label">Firma E-posta:</span>
                        <span class="value">${consultantData.company_email}</span>
                    </div>
                `;
                companyMailBtn.href = `contact.php?email=${encodeURIComponent(consultantData.company_email)}&company=${encodeURIComponent(consultantData.name)}`;
                companyMailBtn.style.display = 'inline-block';
            } else {
                companyMailBtn.style.display = 'none';
            }
            
            if (consultantData.consultant_email) {
                infoHtml += `
                    <div class="consultant-info-item">
                        <span class="label">Danışman E-posta:</span>
                        <span class="value">${consultantData.consultant_email}</span>
                    </div>
                `;
                personMailBtn.href = `contact.php?email=${encodeURIComponent(consultantData.consultant_email)}&company=${encodeURIComponent(consultantData.name)}`;
                personMailBtn.style.display = 'inline-block';
            } else {
                personMailBtn.style.display = 'none';
            }

            if (consultantData.company_phone) {
                infoHtml += `
                    <div class="consultant-info-item">
                        <span class="label">Firma Telefonu:</span>
                        <span class="value">${consultantData.company_phone}</span>
                    </div>
                `;
            }

            if (consultantData.consultant_phone) {
                infoHtml += `
                    <div class="consultant-info-item">
                        <span class="label">Danışman Telefonu:</span>
                        <span class="value">${consultantData.consultant_phone}</span>
                    </div>
                `;
            }
            
            if (!consultantData.company_email && !consultantData.consultant_email) {
                infoHtml += `
                    <div class="consultant-info-item">
                        <span class="label">E-posta:</span>
                        <span class="value">Belirtilmemiş</span>
                    </div>
                `;
            }
            
            infoDiv.innerHTML = infoHtml;
            popup.classList.add('show');
        }

        function hideConsultantPopup() {
            const popup = document.getElementById('consultantPopup');
            popup.classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const requiredElements = [
                'company-search', 'company-dropdown',
                'cert-type-search', 'cert-type-dropdown', 'cert-type-value',
                'consultant-search', 'consultant-dropdown',
                'document-number-search', 'document-number-dropdown',
                'day-filter'
            ];
            
            let missingElements = [];
            requiredElements.forEach(id => {
                if (!document.getElementById(id)) {
                    missingElements.push(id);
                }
            });
            
            if (missingElements.length > 0) {
                console.error('Missing elements:', missingElements);
                return;
            }
            
            initSearchableSelect('company-search', 'company-dropdown', performSearch);
            
            initSearchableSelect('cert-type-search', 'cert-type-dropdown', function() {
                const selectedText = document.getElementById('cert-type-search').value;
                const certTypeItems = document.querySelectorAll('#cert-type-dropdown .dropdown-item');
                let selectedId = '';
                
                certTypeItems.forEach(item => {
                    if (item.textContent.trim() === selectedText) {
                        selectedId = item.dataset.value;
                    }
                });
                
                document.getElementById('cert-type-value').value = selectedId;
                performSearch();
            });
            
            initSearchableSelect('consultant-search', 'consultant-dropdown', performSearch);
            
            initSearchableSelect('document-number-search', 'document-number-dropdown', performSearch);
            
            document.getElementById('day-filter').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 500);
            });

            document.getElementById('sort-select').addEventListener('change', performSearch);
            
            const consultantPopup = document.getElementById('consultantPopup');
            if (consultantPopup) {
                consultantPopup.addEventListener('click', function(e) {
                    if (e.target === this) {
                        hideConsultantPopup();
                    }
                });
            }
            
            document.getElementById('company-search').focus();
        });

        function handleRestricted(e) {
            alert('Bu işlem için yetkiniz yok.');
            if (e && e.preventDefault) e.preventDefault();
            return false;
        }

        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
        }
    </script>
</body>
</html>