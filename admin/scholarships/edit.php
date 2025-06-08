<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

$app_code = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($app_code <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch comprehensive application data
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        ad.*,
        ea.edua_code, ea.educ_id, 
        ec.educ_name,
        s.sch_code, s.sch_name, s.sch_address, s.sch_year_grad, s.sch_grade_avg, s.sch_rank,
        fb.fam_code, fb.fam_relation, fb.fam_is_alive, fb.fam_name, fb.fam_addr, 
        fb.fam_occup, fb.fam_office_addr, fb.fam_educ_attain, fb.fam_ethno_group, 
        fb.fam_annual_income, fb.fam_year_itr,
        sib.sib_code, sib.sib_last_name, sib.sib_first_name, sib.sib_middle_initial,
        sch.scholar_code, sch.scholar_name, sch.scholar_course, sch.scholar_year,
        sp.pref_code, sp.pref_name, sp.pref_addr, sp.pref_degree, sp.pref_remarks
    FROM applicant a
    LEFT JOIN administrator adm ON a.admin_code = adm.admin_code
    LEFT JOIN address ad ON a.app_code = ad.app_code
    LEFT JOIN education_attainment ea ON a.app_code = ea.app_code
    LEFT JOIN education_category ec ON ea.educ_id = ec.educ_id
    LEFT JOIN school s ON ea.edua_code = s.edua_code
    LEFT JOIN family_background fb ON a.app_code = fb.app_code
    LEFT JOIN sibling sib ON a.app_code = sib.app_code
    LEFT JOIN scholarship sch ON sib.sib_code = sch.sib_code
    LEFT JOIN school_preference sp ON a.app_code = sp.app_code
    WHERE a.app_code = :app_code
");
$stmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
$stmt->execute();
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    $_SESSION['error_message'] = "Application not found.";
    header("Location: index.php");
    exit();
}

// Fetch education categories
$educStmt = $pdo->query("SELECT * FROM education_category ORDER BY educ_id");
$educationCategories = $educStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all family members
$familyStmt = $pdo->prepare("SELECT * FROM family_background WHERE app_code = :app_code ORDER BY fam_relation");
$familyStmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
$familyStmt->execute();
$familyMembers = $familyStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize family members by relation
$family = ['Father' => null, 'Mother' => null, 'Guardian' => null];
foreach ($familyMembers as $member) {
    $family[$member['fam_relation']] = $member;
}

// Fetch all siblings
$siblingStmt = $pdo->prepare("
    SELECT sib.*, sch.scholar_name, sch.scholar_course, sch.scholar_year, sch.scholar_code
    FROM sibling sib 
    LEFT JOIN scholarship sch ON sib.sib_code = sch.sib_code 
    WHERE sib.app_code = :app_code ORDER BY sib.sib_code
");
$siblingStmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
$siblingStmt->execute();
$siblings = $siblingStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all education records
$educationStmt = $pdo->prepare("
    SELECT ea.*, ec.educ_name, s.sch_name, s.sch_address, s.sch_year_grad, s.sch_grade_avg, s.sch_rank, s.sch_code
    FROM education_attainment ea
    LEFT JOIN education_category ec ON ea.educ_id = ec.educ_id
    LEFT JOIN school s ON ea.edua_code = s.edua_code
    WHERE ea.app_code = :app_code
    ORDER BY ec.educ_name
");
$educationStmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
$educationStmt->execute();
$educationRecords = $educationStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize education records by category
$education = [];
foreach ($educationRecords as $record) {
    $education[$record['educ_name']] = $record;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();

        // Personal Information
        $updateApplicant = $pdo->prepare("
            UPDATE applicant SET
                app_last_name = :last_name,
                app_first_name = :first_name,
                app_middle_initial = :middle_initial,
                app_date_of_birth = :dob,
                app_place_of_birth = :pob,
                app_gender = :gender,
                app_civil_status = :civil_status,
                app_ethno_group = :ethno_group,
                app_phone_number = :phone_number,
                app_email = :email
            WHERE app_code = :app_code
        ");
        $updateApplicant->execute([
            ':last_name' => trim($_POST["last_name"]),
            ':first_name' => trim($_POST["first_name"]),
            ':middle_initial' => trim($_POST["middle_initial"]),
            ':dob' => $_POST["dob"],
            ':pob' => trim($_POST["pob"]),
            ':gender' => $_POST["gender"],
            ':civil_status' => trim($_POST["civil_status"]),
            ':ethno_group' => trim($_POST["ethno_group"]),
            ':phone_number' => trim($_POST["phone_number"]),
            ':email' => trim($_POST["email"]),
            ':app_code' => $app_code
        ]);

        // Update Address
        if (!empty($applicant['addr_code'])) {
            $updateAddress = $pdo->prepare("
                UPDATE address SET
                    addr_mail = :addr_mail,
                    addr_permanent = :addr_permanent,
                    addr_house_and_street = :addr_house_and_street,
                    addr_barangay = :addr_barangay,
                    addr_municipality = :addr_municipality,
                    addr_province = :addr_province
                WHERE app_code = :app_code
            ");
            $updateAddress->execute([
                ':addr_mail' => trim($_POST["addr_mail"]),
                ':addr_permanent' => trim($_POST["addr_permanent"]),
                ':addr_house_and_street' => trim($_POST["addr_house_and_street"]),
                ':addr_barangay' => trim($_POST["addr_barangay"]),
                ':addr_municipality' => trim($_POST["addr_municipality"]),
                ':addr_province' => trim($_POST["addr_province"]),
                ':app_code' => $app_code
            ]);
        }

        // Update Family Background - Handle all family members
        $familyRelations = ['Father', 'Mother', 'Guardian'];
        foreach ($familyRelations as $relation) {
            $prefix = strtolower($relation);
            
            if (isset($_POST[$prefix . '_name']) && !empty(trim($_POST[$prefix . '_name']))) {
                $familyData = [
                    ':fam_relation' => $relation,
                    ':fam_is_alive' => isset($_POST[$prefix . '_is_alive']) ? ($_POST[$prefix . '_is_alive'] === "1") : false,
                    ':fam_name' => trim($_POST[$prefix . '_name']),
                    ':fam_addr' => trim($_POST[$prefix . '_addr']),
                    ':fam_occup' => trim($_POST[$prefix . '_occup']),
                    ':fam_office_addr' => trim($_POST[$prefix . '_office_addr']),
                    ':fam_educ_attain' => trim($_POST[$prefix . '_educ_attain']),
                    ':fam_ethno_group' => trim($_POST[$prefix . '_ethno_group']),
                    ':fam_annual_income' => !empty($_POST[$prefix . '_annual_income']) ? floatval(str_replace(',', '', $_POST[$prefix . '_annual_income'])) : null,
                    ':fam_year_itr' => !empty($_POST[$prefix . '_year_itr']) ? intval($_POST[$prefix . '_year_itr']) : null,
                    ':app_code' => $app_code
                ];

                // Check if family member exists
                if ($family[$relation]) {
                    // Update existing family member
                    $updateFamily = $pdo->prepare("
                        UPDATE family_background SET
                            fam_is_alive = :fam_is_alive,
                            fam_name = :fam_name,
                            fam_addr = :fam_addr,
                            fam_occup = :fam_occup,
                            fam_office_addr = :fam_office_addr,
                            fam_educ_attain = :fam_educ_attain,
                            fam_ethno_group = :fam_ethno_group,
                            fam_annual_income = :fam_annual_income,
                            fam_year_itr = :fam_year_itr
                        WHERE fam_code = :fam_code
                    ");
                    $familyData[':fam_code'] = $family[$relation]['fam_code'];
                    unset($familyData[':fam_relation'], $familyData[':app_code']);
                    $updateFamily->execute($familyData);
                } else {
                    // Insert new family member
                    $insertFamily = $pdo->prepare("
                        INSERT INTO family_background 
                        (app_code, fam_relation, fam_is_alive, fam_name, fam_addr, fam_occup, fam_office_addr, fam_educ_attain, fam_ethno_group, fam_annual_income, fam_year_itr)
                        VALUES (:app_code, :fam_relation, :fam_is_alive, :fam_name, :fam_addr, :fam_occup, :fam_office_addr, :fam_educ_attain, :fam_ethno_group, :fam_annual_income, :fam_year_itr)
                    ");
                    $insertFamily->execute($familyData);
                }
            }
        }

        // Update Education Records - Handle all education categories
        foreach ($educationCategories as $category) {
            $categoryName = strtolower(str_replace(['/', ' ', '-'], '_', $category['educ_name']));
            
            if (isset($_POST[$categoryName . '_school_name']) && !empty(trim($_POST[$categoryName . '_school_name']))) {
                $schoolData = [
                    ':sch_name' => trim($_POST[$categoryName . '_school_name']),
                    ':sch_address' => trim($_POST[$categoryName . '_school_address']),
                    ':sch_year_grad' => !empty($_POST[$categoryName . '_year_grad']) ? intval($_POST[$categoryName . '_year_grad']) : null,
                    ':sch_grade_avg' => !empty($_POST[$categoryName . '_grade_avg']) ? floatval($_POST[$categoryName . '_grade_avg']) : null,
                    ':sch_rank' => trim($_POST[$categoryName . '_rank'])
                ];

                // Check if education record exists for this category
                if (isset($education[$category['educ_name']])) {
                    // Update existing education record
                    $updateSchool = $pdo->prepare("
                        UPDATE school SET
                            sch_name = :sch_name,
                            sch_address = :sch_address,
                            sch_year_grad = :sch_year_grad,
                            sch_grade_avg = :sch_grade_avg,
                            sch_rank = :sch_rank
                        WHERE sch_code = :sch_code
                    ");
                    $schoolData[':sch_code'] = $education[$category['educ_name']]['sch_code'];
                    $updateSchool->execute($schoolData);
                } else {
                    // Create new education attainment and school record
                    $insertEducationAttainment = $pdo->prepare("
                        INSERT INTO education_attainment (app_code, educ_id) 
                        VALUES (:app_code, :educ_id)
                    ");
                    $insertEducationAttainment->execute([
                        ':app_code' => $app_code,
                        ':educ_id' => $category['educ_id']
                    ]);
                    
                    $edua_code = $pdo->lastInsertId();
                    
                    $insertSchool = $pdo->prepare("
                        INSERT INTO school (edua_code, sch_name, sch_address, sch_year_grad, sch_grade_avg, sch_rank)
                        VALUES (:edua_code, :sch_name, :sch_address, :sch_year_grad, :sch_grade_avg, :sch_rank)
                    ");
                    $schoolData[':edua_code'] = $edua_code;
                    $insertSchool->execute($schoolData);
                }
            }
        }

        // Update Siblings - Handle multiple siblings
        $maxSiblings = 5; // Adjust as needed
        
        for ($i = 1; $i <= $maxSiblings; $i++) {
            if (isset($_POST['sibling_' . $i . '_last_name']) && !empty(trim($_POST['sibling_' . $i . '_last_name']))) {
                $siblingData = [
                    ':sib_last_name' => trim($_POST['sibling_' . $i . '_last_name']),
                    ':sib_first_name' => trim($_POST['sibling_' . $i . '_first_name']),
                    ':sib_middle_initial' => trim($_POST['sibling_' . $i . '_middle_initial'])
                ];

                if (isset($siblings[$i-1])) {
                    // Update existing sibling
                    $updateSibling = $pdo->prepare("
                        UPDATE sibling SET
                            sib_last_name = :sib_last_name,
                            sib_first_name = :sib_first_name,
                            sib_middle_initial = :sib_middle_initial
                        WHERE sib_code = :sib_code
                    ");
                    $siblingData[':sib_code'] = $siblings[$i-1]['sib_code'];
                    $updateSibling->execute($siblingData);
                    
                    // Update scholarship information if exists
                    if (isset($_POST['sibling_' . $i . '_scholar_name']) && !empty(trim($_POST['sibling_' . $i . '_scholar_name']))) {
                        $scholarData = [
                            ':scholar_name' => trim($_POST['sibling_' . $i . '_scholar_name']),
                            ':scholar_course' => trim($_POST['sibling_' . $i . '_scholar_course']),
                            ':scholar_year' => trim($_POST['sibling_' . $i . '_scholar_year'])
                        ];

                        if (!empty($siblings[$i-1]['scholar_code'])) {
                            $updateScholarship = $pdo->prepare("
                                UPDATE scholarship SET
                                    scholar_name = :scholar_name,
                                    scholar_course = :scholar_course,
                                    scholar_year = :scholar_year
                                WHERE scholar_code = :scholar_code
                            ");
                            $scholarData[':scholar_code'] = $siblings[$i-1]['scholar_code'];
                            $updateScholarship->execute($scholarData);
                        } else {
                            $insertScholarship = $pdo->prepare("
                                INSERT INTO scholarship (sib_code, scholar_name, scholar_course, scholar_year)
                                VALUES (:sib_code, :scholar_name, :scholar_course, :scholar_year)
                            ");
                            $scholarData[':sib_code'] = $siblings[$i-1]['sib_code'];
                            $insertScholarship->execute($scholarData);
                        }
                    }
                } else {
                    // Insert new sibling
                    $insertSibling = $pdo->prepare("
                        INSERT INTO sibling (app_code, sib_last_name, sib_first_name, sib_middle_initial)
                        VALUES (:app_code, :sib_last_name, :sib_first_name, :sib_middle_initial)
                    ");
                    $siblingData[':app_code'] = $app_code;
                    $insertSibling->execute($siblingData);
                    
                    $sib_code = $pdo->lastInsertId();
                    
                    // Insert scholarship information if provided
                    if (isset($_POST['sibling_' . $i . '_scholar_name']) && !empty(trim($_POST['sibling_' . $i . '_scholar_name']))) {
                        $insertScholarship = $pdo->prepare("
                            INSERT INTO scholarship (sib_code, scholar_name, scholar_course, scholar_year)
                            VALUES (:sib_code, :scholar_name, :scholar_course, :scholar_year)
                        ");
                        $insertScholarship->execute([
                            ':sib_code' => $sib_code,
                            ':scholar_name' => trim($_POST['sibling_' . $i . '_scholar_name']),
                            ':scholar_course' => trim($_POST['sibling_' . $i . '_scholar_course']),
                            ':scholar_year' => trim($_POST['sibling_' . $i . '_scholar_year'])
                        ]);
                    }
                }
            }
        }

        // Update School Preference
        if (!empty($applicant['pref_code'])) {
            $updatePreference = $pdo->prepare("
                UPDATE school_preference SET
                    pref_name = :pref_name,
                    pref_addr = :pref_addr,
                    pref_degree = :pref_degree,
                    pref_remarks = :pref_remarks
                WHERE pref_code = :pref_code
            ");
            $updatePreference->execute([
                ':pref_name' => trim($_POST["pref_name"]),
                ':pref_addr' => trim($_POST["pref_addr"]),
                ':pref_degree' => trim($_POST["pref_degree"]),
                ':pref_remarks' => trim($_POST["pref_remarks"]),
                ':pref_code' => $applicant['pref_code']
            ]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Application updated successfully.";
        header("Location: view.php?id=" . $app_code);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating application: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Scholarship Application - NCIP Admin</title>
    <link rel="icon" href="../../front end/images/ncip-logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
            border-radius: 10px 10px 0 0 !important;
        }
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 0;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .required {
            color: #dc3545;
        }
        .is-invalid {
            border-color: #dc3545 !important;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
        }
        .btn-group-actions {
            gap: 10px;
        }
        .sticky-top-custom {
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        .subsection-header {
            background-color: #e9ecef;
            padding: 10px 15px;
            margin: 15px -15px;
            font-weight: 600;
            color: #495057;
        }
        .sibling-group, .education-group {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .remove-btn {
            float: right;
            margin-top: -5px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 sticky-top-custom bg-light p-3 rounded">
                <div>
                    <h1 class="h2 mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Application</h1>
                    <small class="text-muted">Application ID: <?php echo htmlspecialchars($app_code); ?></small>
                </div>
                <div class="btn-group-actions d-flex">
                    <button type="submit" form="editForm" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                    <a href="view.php?id=<?php echo $app_code; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Cancel
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <form method="POST" id="editForm">
                <!-- Personal Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="section-title"><i class="bi bi-person me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required 
                                       value="<?php echo htmlspecialchars($applicant['app_last_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required 
                                       value="<?php echo htmlspecialchars($applicant['app_first_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="middle_initial" class="form-label">Middle Initial</label>
                                <input type="text" class="form-control" id="middle_initial" name="middle_initial" maxlength="1" 
                                       value="<?php echo htmlspecialchars($applicant['app_middle_initial'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="dob" class="form-label">Date of Birth <span class="required">*</span></label>
                                <input type="date" class="form-control" id="dob" name="dob" required 
                                       value="<?php echo htmlspecialchars($applicant['app_date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="pob" class="form-label">Place of Birth <span class="required">*</span></label>
                                <input type="text" class="form-control" id="pob" name="pob" required 
                                       value="<?php echo htmlspecialchars($applicant['app_place_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="gender" class="form-label">Gender <span class="required">*</span></label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php if(($applicant['app_gender'] ?? '') == 'Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if(($applicant['app_gender'] ?? '') == 'Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if(($applicant['app_gender'] ?? '') == 'Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="civil_status" class="form-label">Civil Status</label>
                                <select class="form-select" id="civil_status" name="civil_status">
                                    <option value="">Select Civil Status</option>
                                    <option value="Single" <?php if(($applicant['app_civil_status'] ?? '') == 'Single') echo 'selected'; ?>>Single</option>
                                    <option value="Married" <?php if(($applicant['app_civil_status'] ?? '') == 'Married') echo 'selected'; ?>>Married</option>
                                    <option value="Divorced" <?php if(($applicant['app_civil_status'] ?? '') == 'Divorced') echo 'selected'; ?>>Divorced</option>
                                    <option value="Widowed" <?php if(($applicant['app_civil_status'] ?? '') == 'Widowed') echo 'selected'; ?>>Widowed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="ethno_group" class="form-label">Ethnic Group <span class="required">*</span></label>
                                <input type="text" class="form-control" id="ethno_group" name="ethno_group" required 
                                       value="<?php echo htmlspecialchars($applicant['app_ethno_group'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" 
                                       value="<?php echo htmlspecialchars($applicant['app_phone_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($applicant['app_email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="section-title"><i class="bi bi-geo-alt me-2"></i>Address Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="addr_mail" class="form-label">Mailing Address</label>
                                <input type="text" class="form-control" id="addr_mail" name="addr_mail" 
                                       value="<?php echo htmlspecialchars($applicant['addr_mail'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="addr_permanent" class="form-label">Permanent Address</label>
                                <input type="text" class="form-control" id="addr_permanent" name="addr_permanent" 
                                       value="<?php echo htmlspecialchars($applicant['addr_permanent'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="addr_house_and_street" class="form-label">House Number & Street</label>
                                <input type="text" class="form-control" id="addr_house_and_street" name="addr_house_and_street" 
                                       value="<?php echo htmlspecialchars($applicant['addr_house_and_street'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="addr_barangay" class="form-label">Barangay</label>
                                <input type="text" class="form-control" id="addr_barangay" name="addr_barangay" 
                                       value="<?php echo htmlspecialchars($applicant['addr_barangay'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="addr_municipality" class="form-label">Municipality/City</label>
                                <input type="text" class="form-control" id="addr_municipality" name="addr_municipality" 
                                       value="<?php echo htmlspecialchars($applicant['addr_municipality'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="addr_province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="addr_province" name="addr_province" 
                                       value="<?php echo htmlspecialchars($applicant['addr_province'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Education Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="section-title"><i class="bi bi-mortarboard me-2"></i>Education Information</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($educationCategories as $category): 
                            $categoryName = strtolower(str_replace(['/', ' ', '-'], '_', $category['educ_name']));
                            $educRecord = $education[$category['educ_name']] ?? null;
                        ?>
                        <div class="education-group">
                            <h6 class="text-primary mb-3"><?php echo htmlspecialchars($category['educ_name']); ?></h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="<?php echo $categoryName; ?>_school_name" class="form-label">School Name</label>
                                    <input type="text" class="form-control" 
                                           id="<?php echo $categoryName; ?>_school_name" 
                                           name="<?php echo $categoryName; ?>_school_name" 
                                           value="<?php echo htmlspecialchars($educRecord['sch_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="<?php echo $categoryName; ?>_school_address" class="form-label">School Address</label>
                                    <input type="text" class="form-control" 
                                           id="<?php echo $categoryName; ?>_school_address" 
                                           name="<?php echo $categoryName; ?>_school_address" 
                                           value="<?php echo htmlspecialchars($educRecord['sch_address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="<?php echo $categoryName; ?>_year_grad" class="form-label">Year Graduated</label>
                                    <input type="number" class="form-control" 
                                           id="<?php echo $categoryName; ?>_year_grad" 
                                           name="<?php echo $categoryName; ?>_year_grad" 
                                           min="1900" max="<?php echo date('Y'); ?>" 
                                           value="<?php echo htmlspecialchars($educRecord['sch_year_grad'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="<?php echo $categoryName; ?>_grade_avg" class="form-label">Grade Average</label>
                                    <input type="number" step="0.01" class="form-control" 
                                           id="<?php echo $categoryName; ?>_grade_avg" 
                                           name="<?php echo $categoryName; ?>_grade_avg" 
                                           min="70" max="100" 
                                           value="<?php echo htmlspecialchars($educRecord['sch_grade_avg'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="<?php echo $categoryName; ?>_rank" class="form-label">Class Rank</label>
                                    <input type="text" class="form-control" 
                                           id="<?php echo $categoryName; ?>_rank" 
                                           name="<?php echo $categoryName; ?>_rank" 
                                           value="<?php echo htmlspecialchars($educRecord['sch_rank'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Family Background -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="section-title"><i class="bi bi-people me-2"></i>Family Background</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach (['Father', 'Mother', 'Guardian'] as $relation): 
                            $prefix = strtolower($relation);
                            $familyMember = $family[$relation];
                        ?>
                        <div class="subsection-header"><?php echo $relation; ?> Information</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="<?php echo $prefix; ?>_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" 
                                       id="<?php echo $prefix; ?>_name" 
                                       name="<?php echo $prefix; ?>_name" 
                                       value="<?php echo htmlspecialchars($familyMember['fam_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="<?php echo $prefix; ?>_is_alive" class="form-label">Status</label>
                                <select class="form-select" id="<?php echo $prefix; ?>_is_alive" name="<?php echo $prefix; ?>_is_alive">
                                    <option value="1" <?php if(($familyMember['fam_is_alive'] ?? false)) echo 'selected'; ?>>Living</option>
                                    <option value="0" <?php if(!($familyMember['fam_is_alive'] ?? true)) echo 'selected'; ?>>Deceased</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="<?php echo $prefix; ?>_addr" class="form-label">Address</label>
                                <input type="text" class="form-control" 
                                       id="<?php echo $prefix; ?>_addr" 
                                       name="<?php echo $prefix; ?>_addr" 
                                       value="<?php echo htmlspecialchars($familyMember['fam_addr'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="<?php echo $prefix; ?>_occup" class="form-label">Occupation</label>
                                <input type="text" class="form-control" 
                                       id="<?php echo $prefix; ?>_occup" 
                                       name="<?php echo $prefix; ?>_occup" 
                                       value="<?php echo htmlspecialchars($familyMember['fam_occup'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="<?php echo $prefix; ?>_office_addr" class="form-label">Office Address</label>
                                <input type="text" class="form-control" 
                                       id="<?php echo $prefix; ?>_office_addr" 
                                       name="<?php echo $prefix; ?>_office_addr" 
                                       value="<?php echo htmlspecialchars($familyMember['fam_office_addr'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="<?php echo $prefix; ?>_educ_attain" class="form-label">Educational Attainment</label>
                                <select class="form-select" id="<?php echo $prefix; ?>_educ_attain" name="<?php echo $prefix; ?>_educ_attain">
                                    <option value="">Select Education Level</option>
                                    <?php foreach($educationCategories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['educ_name']); ?>" 
                                                <?php if(($familyMember['fam_educ_attain'] ?? '') == $cat['educ_name']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($cat['educ_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="<?php echo $prefix; ?>_ethno_group" class="form-label">Ethnic Group</label>
                                <input type="text" class="form-control" 
                                       id="<?php echo $prefix; ?>_ethno_group" 
                                       name="<?php echo $prefix; ?>_ethno_group" 
                                       value="<?php echo htmlspecialchars($familyMember['fam_ethno_group'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="<?php echo $prefix; ?>_annual_income" class="form-label">Annual Income (₱)</label>
                                <input type="text" class="form-control" 
                                       id="<?php echo $prefix; ?>_annual_income" 
                                       name="<?php echo $prefix; ?>_annual_income" 
                                       value="<?php echo htmlspecialchars($familyMember['fam_annual_income'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="<?php echo $prefix; ?>_year_itr" class="form-label">ITR Year</label>
                                <input type="number" class="form-control" 
                                       id="<?php echo $prefix; ?>_year_itr" 
                                       name="<?php echo $prefix; ?>_year_itr" 
                                       min="1900" max="<?php echo date('Y'); ?>" 
                                       value="<?php echo htmlspecialchars($familyMember['fam_year_itr'] ?? ''); ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sibling Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="section-title"><i class="bi bi-person-plus me-2"></i>Sibling Information</h5>
                    </div>
                    <div class="card-body">
                        <div id="siblings-container">
                            <?php 
                            $maxSiblings = max(5, count($siblings)); // Show at least 5 sibling forms
                            for ($i = 1; $i <= $maxSiblings; $i++): 
                                $sibling = isset($siblings[$i-1]) ? $siblings[$i-1] : null;
                            ?>
                            <div class="sibling-group">
                                <h6 class="text-primary mb-3">Sibling <?php echo $i; ?></h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="sibling_<?php echo $i; ?>_last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" 
                                               id="sibling_<?php echo $i; ?>_last_name" 
                                               name="sibling_<?php echo $i; ?>_last_name" 
                                               value="<?php echo htmlspecialchars($sibling['sib_last_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sibling_<?php echo $i; ?>_first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" 
                                               id="sibling_<?php echo $i; ?>_first_name" 
                                               name="sibling_<?php echo $i; ?>_first_name" 
                                               value="<?php echo htmlspecialchars($sibling['sib_first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sibling_<?php echo $i; ?>_middle_initial" class="form-label">Middle Initial</label>
                                        <input type="text" class="form-control" 
                                               id="sibling_<?php echo $i; ?>_middle_initial" 
                                               name="sibling_<?php echo $i; ?>_middle_initial" 
                                               maxlength="1" 
                                               value="<?php echo htmlspecialchars($sibling['sib_middle_initial'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sibling_<?php echo $i; ?>_scholar_name" class="form-label">Scholarship Name</label>
                                        <input type="text" class="form-control" 
                                               id="sibling_<?php echo $i; ?>_scholar_name" 
                                               name="sibling_<?php echo $i; ?>_scholar_name" 
                                               value="<?php echo htmlspecialchars($sibling['scholar_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sibling_<?php echo $i; ?>_scholar_course" class="form-label">Course</label>
                                        <input type="text" class="form-control" 
                                               id="sibling_<?php echo $i; ?>_scholar_course" 
                                               name="sibling_<?php echo $i; ?>_scholar_course" 
                                               value="<?php echo htmlspecialchars($sibling['scholar_course'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sibling_<?php echo $i; ?>_scholar_year" class="form-label">Year Level</label>
                                        <select class="form-select" 
                                                id="sibling_<?php echo $i; ?>_scholar_year" 
                                                name="sibling_<?php echo $i; ?>_scholar_year">
                                            <option value="">Select Year</option>
                                            <option value="1st Year" <?php if(($sibling['scholar_year'] ?? '') == '1st Year') echo 'selected'; ?>>1st Year</option>
                                            <option value="2nd Year" <?php if(($sibling['scholar_year'] ?? '') == '2nd Year') echo 'selected'; ?>>2nd Year</option>
                                            <option value="3rd Year" <?php if(($sibling['scholar_year'] ?? '') == '3rd Year') echo 'selected'; ?>>3rd Year</option>
                                            <option value="4th Year" <?php if(($sibling['scholar_year'] ?? '') == '4th Year') echo 'selected'; ?>>4th Year</option>
                                            <option value="5th Year" <?php if(($sibling['scholar_year'] ?? '') == '5th Year') echo 'selected'; ?>>5th Year</option>
                                            <option value="Graduate" <?php if(($sibling['scholar_year'] ?? '') == 'Graduate') echo 'selected'; ?>>Graduate</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- School Preference -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="section-title"><i class="bi bi-building me-2"></i>School Preference</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="pref_name" class="form-label">Preferred School Name</label>
                                <input type="text" class="form-control" id="pref_name" name="pref_name" 
                                       value="<?php echo htmlspecialchars($applicant['pref_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="pref_addr" class="form-label">School Address</label>
                                <input type="text" class="form-control" id="pref_addr" name="pref_addr" 
                                       value="<?php echo htmlspecialchars($applicant['pref_addr'] ?? ''); ?>">
                            </div>
                          <div class="col-md-6">
                                <label for="pref_degree" class="form-label">Preferred Degree/Course</label>
                                <input type="text" class="form-control" id="pref_degree" name="pref_degree" 
                                       value="<?php echo htmlspecialchars($applicant['pref_degree'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="pref_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="pref_remarks" name="pref_remarks" rows="3"><?php echo htmlspecialchars($applicant['pref_remarks'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body text-center">
                        <div class="btn-group-actions d-flex justify-content-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save me-2"></i>Save Changes
                            </button>
                            <a href="view.php?id=<?php echo $app_code; ?>" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentYear = new Date().getFullYear();
            const form = document.getElementById('editForm');

            // Date of Birth Validation
            const dobInput = document.getElementById('dob');
            if (dobInput) {
                // Set max date to today
                dobInput.max = new Date().toISOString().split('T')[0];
                
                dobInput.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    const age = today.getFullYear() - selectedDate.getFullYear();
                    
                    if (selectedDate > today) {
                        this.setCustomValidity('Date of birth cannot be in the future');
                        this.classList.add('is-invalid');
                    } else if (age < 16) {
                        this.setCustomValidity('Applicant must be at least 16 years old');
                        this.classList.add('is-invalid');
                    } else if (age > 100) {
                        this.setCustomValidity('Please enter a valid date of birth');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });
            }

            // Year Graduated Validation for all education categories
            const yearGradInputs = document.querySelectorAll('input[name*="_year_grad"]');
            yearGradInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    const year = parseInt(this.value);
                    if (this.value && (year < 1900 || year > currentYear)) {
                        this.setCustomValidity(`Year must be between 1900 and ${currentYear}`);
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });
            });

            // Grade Average Validation
            const gradeInputs = document.querySelectorAll('input[name*="_grade_avg"]');
            gradeInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    const grade = parseFloat(this.value);
                    if (this.value && (grade < 70 || grade > 100)) {
                        this.setCustomValidity('Grade average must be between 70 and 100');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });
            });

            // Annual Income Validation with formatting
            const incomeInputs = document.querySelectorAll('input[name*="_annual_income"]');
            incomeInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    // Remove non-numeric characters except decimal point
                    let value = this.value.replace(/[^\d.]/g, '');
                    
                    // Ensure only one decimal point
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    
                    // Limit to 2 decimal places
                    if (parts[1] && parts[1].length > 2) {
                        value = parseFloat(value).toFixed(2);
                    }
                    
                    this.value = value;
                    
                    const income = parseFloat(value);
                    if (value && income < 0) {
                        this.setCustomValidity('Annual income cannot be negative');
                        this.classList.add('is-invalid');
                    } else if (value && income > 10000000) {
                        this.setCustomValidity('Please enter a reasonable annual income amount');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });

                // Format on blur
                input.addEventListener('blur', function() {
                    if (this.value) {
                        const value = parseFloat(this.value);
                        if (!isNaN(value)) {
                            this.value = value.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                });

                // Remove formatting on focus
                input.addEventListener('focus', function() {
                    this.value = this.value.replace(/,/g, '');
                });
            });

            // ITR Year Validation
            const itrYearInputs = document.querySelectorAll('input[name*="_year_itr"]');
            itrYearInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    const year = parseInt(this.value);
                    if (this.value && (year < 1900 || year > currentYear)) {
                        this.setCustomValidity(`ITR year must be between 1900 and ${currentYear}`);
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });
            });

            // Phone Number Validation
            const phoneInput = document.getElementById('phone_number');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // Remove non-numeric characters
                    this.value = this.value.replace(/\D/g, '');
                    
                    const phone = this.value;
                    if (phone && (phone.length < 10 || phone.length > 11)) {
                        this.setCustomValidity('Phone number must be 10-11 digits');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });
            }

            // Email Validation
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (this.value && !emailPattern.test(this.value)) {
                        this.setCustomValidity('Please enter a valid email address');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });
            }

            // Middle Initial Validation
            const middleInitialInputs = document.querySelectorAll('input[maxlength="1"]');
            middleInitialInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    // Convert to uppercase and allow only letters
                    this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();
                });
            });

            // Name Field Validation (allow only letters, spaces, hyphens, apostrophes)
            const nameInputs = document.querySelectorAll('input[name*="name"], input[name*="first_name"], input[name*="last_name"]');
            nameInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    // Allow letters, spaces, hyphens, and apostrophes only
                    this.value = this.value.replace(/[^A-Za-z\s\-']/g, '');
                });
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');
                
                // Check required fields
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                // Check for any invalid fields
                const invalidFields = form.querySelectorAll('.is-invalid');
                if (invalidFields.length > 0) {
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    
                    // Scroll to first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        firstInvalid.focus();
                    }
                    
                    // Show error message
                    const alertContainer = document.querySelector('#alert-container') || document.querySelector('.container-fluid');
                    const existingAlert = alertContainer.querySelector('.alert-danger');
                    
                    if (!existingAlert) {
                        const errorAlert = document.createElement('div');
                        errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                        errorAlert.innerHTML = `
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Please fix the validation errors before submitting the form.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        alertContainer.insertBefore(errorAlert, alertContainer.firstChild);
                    }
                    
                    return false;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';
                }
            });

            // Real-time validation feedback
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(function(input) {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });

                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid') && this.value.trim()) {
                        // Re-validate the field
                        this.dispatchEvent(new Event('blur'));
                    }
                });
            });

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000);
            });

            // Prevent form submission on Enter key (except for submit button)
            form.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.type !== 'submit' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>