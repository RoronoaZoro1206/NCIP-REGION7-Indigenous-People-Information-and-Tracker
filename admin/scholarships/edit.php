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

$stmt = $pdo->prepare("
    SELECT a.*, ad.*, ea.EDUA_CODE, ea.EDUC_ID, ec.EDUC_NAME, s.SCH_CODE, s.SCH_NAME, s.SCH_ADDRESS, s.SCH_YEAR_GRAD, s.SCH_GRADE_AVG, s.SCH_RANK,
           fb.FAM_CODE, fb.FAM_RELATION, fb.FAM_IS_ALIVE, fb.FAM_NAME, fb.FAM_ADDR, fb.FAM_OCCUP, fb.FAM_OFFICE_ADDR, fb.FAM_EDUC_ATTAIN, fb.FAM_ETHNO_GROUP, fb.FAM_ANNUAL_INCOME, fb.FAM_YEAR_ITR,
           sib.SIB_CODE, sib.SIB_LAST_NAME, sib.SIB_FIRST_NAME, sib.SIB_MIDDLE_INITIAL,
           sch.SCHOLAR_CODE, sch.SCHOLAR_NAME, sch.SCHOLAR_COURSE, sch.SCHOLAR_YEAR,
           sp.PREF_CODE, sp.PREF_NAME, sp.PREF_ADDR, sp.PREF_DEGREE, sp.PREF_REMARKS
    FROM APPLICANT a
    LEFT JOIN ADDRESS ad ON a.APP_CODE = ad.APP_CODE
    LEFT JOIN EDUCATION_ATTAINMENT ea ON a.APP_CODE = ea.APP_CODE
    LEFT JOIN EDUCATION_CATEGORY ec ON ea.EDUC_ID = ec.EDUC_ID
    LEFT JOIN SCHOOL s ON ea.EDUA_CODE = s.EDUA_CODE
    LEFT JOIN FAMILY_BACKGROUND fb ON a.APP_CODE = fb.APP_CODE
    LEFT JOIN SIBLING sib ON a.APP_CODE = sib.APP_CODE
    LEFT JOIN SCHOLARSHIP sch ON sib.SIB_CODE = sch.SIB_CODE
    LEFT JOIN SCHOOL_PREFERENCE sp ON a.APP_CODE = sp.APP_CODE
    WHERE a.APP_CODE = :app_code
");
$stmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
$stmt->execute();
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    $_SESSION['error_message'] = "Application not found.";
    header("Location: index.php");
    exit();
}

$educStmt = $pdo->query("SELECT * FROM EDUCATION_CATEGORY ORDER BY EDUC_ID");
$educationCategories = $educStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $last_name = trim($_POST["last_name"]);
    $first_name = trim($_POST["first_name"]);
    $middle_initial = trim($_POST["middle_initial"]);
    $dob = $_POST["dob"];
    $pob = trim($_POST["pob"]);
    $gender = $_POST["gender"];
    $civil_status = $_POST["civil_status"];
    $ethno_group = trim($_POST["ethno_group"]);
    $phone_number = trim($_POST["phone_number"]);
    $email = trim($_POST["email"]);
    $addr_mail = trim($_POST["addr_mail"]);
    $addr_permanent = trim($_POST["addr_permanent"]);
    $addr_house_and_street = trim($_POST["addr_house_and_street"]);
    $addr_barangay = trim($_POST["addr_barangay"]);
    $addr_municipality = trim($_POST["addr_municipality"]);
    $addr_province = trim($_POST["addr_province"]);

    $educ_id = intval($_POST["educ_id"]);
    $sch_name = trim($_POST["sch_name"]);
    $sch_address = trim($_POST["sch_address"]);
    $sch_year_grad = intval($_POST["sch_year_grad"]);
    $sch_grade_avg = floatval($_POST["sch_grade_avg"]);
    $sch_rank = trim($_POST["sch_rank"]);

    $fam_relation = trim($_POST["fam_relation"]);
    $fam_is_alive = isset($_POST["fam_is_alive"]) ? ($_POST["fam_is_alive"] === "1" ? true : false) : false;
    $fam_name = trim($_POST["fam_name"]);
    $fam_addr = trim($_POST["fam_addr"]);
    $fam_occup = trim($_POST["fam_occup"]);
    $fam_office_addr = trim($_POST["fam_office_addr"]);
    $fam_educ_attain = trim($_POST["fam_educ_attain"]);
    $fam_ethno_group = trim($_POST["fam_ethno_group"]);
    $fam_annual_income = floatval($_POST["fam_annual_income"]);
    $fam_year_itr = intval($_POST["fam_year_itr"]);

    $sib_last_name = trim($_POST["sib_last_name"]);
    $sib_first_name = trim($_POST["sib_first_name"]);
    $sib_middle_initial = trim($_POST["sib_middle_initial"]);

    $scholar_name = trim($_POST["scholar_name"]);
    $scholar_course = trim($_POST["scholar_course"]);
    $scholar_year = trim($_POST["scholar_year"]);

    $pref_name = trim($_POST["pref_name"]);
    $pref_addr = trim($_POST["pref_addr"]);
    $pref_degree = trim($_POST["pref_degree"]);
    $pref_remarks = trim($_POST["pref_remarks"]);

    $updateApplicant = $pdo->prepare("
        UPDATE APPLICANT SET
            APP_LAST_NAME = :last_name,
            APP_FIRST_NAME = :first_name,
            APP_MIDDLE_INITIAL = :middle_initial,
            APP_DATE_OF_BIRTH = :dob,
            APP_PLACE_OF_BIRTH = :pob,
            APP_GENDER = :gender,
            APP_CIVIL_STATUS = :civil_status,
            APP_ETHNO_GROUP = :ethno_group,
            APP_PHONE_NUMBER = :phone_number,
            APP_EMAIL = :email
        WHERE APP_CODE = :app_code
    ");
    $updateApplicant->execute([
        ':last_name' => $last_name,
        ':first_name' => $first_name,
        ':middle_initial' => $middle_initial,
        ':dob' => $dob,
        ':pob' => $pob,
        ':gender' => $gender,
        ':civil_status' => $civil_status,
        ':ethno_group' => $ethno_group,
        ':phone_number' => $phone_number,
        ':email' => $email,
        ':app_code' => $app_code
    ]);

    $updateAddress = $pdo->prepare("
        UPDATE ADDRESS SET
            ADDR_MAIL = :addr_mail,
            ADDR_PERMANENT = :addr_permanent,
            ADDR_HOUSE_AND_STREET = :addr_house_and_street,
            ADDR_BARANGAY = :addr_barangay,
            ADDR_MUNICIPALITY = :addr_municipality,
            ADDR_PROVINCE = :addr_province
        WHERE APP_CODE = :app_code
    ");
    $updateAddress->execute([
        ':addr_mail' => $addr_mail,
        ':addr_permanent' => $addr_permanent,
        ':addr_house_and_street' => $addr_house_and_street,
        ':addr_barangay' => $addr_barangay,
        ':addr_municipality' => $addr_municipality,
        ':addr_province' => $addr_province,
        ':app_code' => $app_code
    ]);

    $updateEducation = $pdo->prepare("
        UPDATE EDUCATION_ATTAINMENT SET
            EDUC_ID = :educ_id
        WHERE APP_CODE = :app_code
    ");
    $updateEducation->execute([
        ':educ_id' => $educ_id,
        ':app_code' => $app_code
    ]);

    $edua_code = $applicant['edua_code'];
    $updateSchool = $pdo->prepare("
        UPDATE SCHOOL SET
            SCH_NAME = :sch_name,
            SCH_ADDRESS = :sch_address,
            SCH_YEAR_GRAD = :sch_year_grad,
            SCH_GRADE_AVG = :sch_grade_avg,
            SCH_RANK = :sch_rank
        WHERE EDUA_CODE = :edua_code
    ");
    $updateSchool->execute([
        ':sch_name' => $sch_name,
        ':sch_address' => $sch_address,
        ':sch_year_grad' => $sch_year_grad,
        ':sch_grade_avg' => $sch_grade_avg,
        ':sch_rank' => $sch_rank,
        ':edua_code' => $edua_code
    ]);

    $updateFamily = $pdo->prepare("
        UPDATE FAMILY_BACKGROUND SET
            FAM_RELATION = :fam_relation,
            FAM_IS_ALIVE = :fam_is_alive,
            FAM_NAME = :fam_name,
            FAM_ADDR = :fam_addr,
            FAM_OCCUP = :fam_occup,
            FAM_OFFICE_ADDR = :fam_office_addr,
            FAM_EDUC_ATTAIN = :fam_educ_attain,
            FAM_ETHNO_GROUP = :fam_ethno_group,
            FAM_ANNUAL_INCOME = :fam_annual_income,
            FAM_YEAR_ITR = :fam_year_itr
        WHERE APP_CODE = :app_code
    ");
    $updateFamily->execute([
        ':fam_relation' => $fam_relation,
        ':fam_is_alive' => $fam_is_alive,
        ':fam_name' => $fam_name,
        ':fam_addr' => $fam_addr,
        ':fam_occup' => $fam_occup,
        ':fam_office_addr' => $fam_office_addr,
        ':fam_educ_attain' => $fam_educ_attain,
        ':fam_ethno_group' => $fam_ethno_group,
        ':fam_annual_income' => $fam_annual_income,
        ':fam_year_itr' => $fam_year_itr,
        ':app_code' => $app_code
    ]);

    $sib_code = $applicant['sib_code'];
    $updateSibling = $pdo->prepare("
        UPDATE SIBLING SET
            SIB_LAST_NAME = :sib_last_name,
            SIB_FIRST_NAME = :sib_first_name,
            SIB_MIDDLE_INITIAL = :sib_middle_initial
        WHERE SIB_CODE = :sib_code
    ");
    $updateSibling->execute([
        ':sib_last_name' => $sib_last_name,
        ':sib_first_name' => $sib_first_name,
        ':sib_middle_initial' => $sib_middle_initial,
        ':sib_code' => $sib_code
    ]);

    $scholar_code = $applicant['scholar_code'];
    $updateScholarship = $pdo->prepare("
        UPDATE SCHOLARSHIP SET
            SCHOLAR_NAME = :scholar_name,
            SCHOLAR_COURSE = :scholar_course,
            SCHOLAR_YEAR = :scholar_year
        WHERE SCHOLAR_CODE = :scholar_code
    ");
    $updateScholarship->execute([
        ':scholar_name' => $scholar_name,
        ':scholar_course' => $scholar_course,
        ':scholar_year' => $scholar_year,
        ':scholar_code' => $scholar_code
    ]);

    $pref_code = $applicant['pref_code'];
    $updatePreference = $pdo->prepare("
        UPDATE SCHOOL_PREFERENCE SET
            PREF_NAME = :pref_name,
            PREF_ADDR = :pref_addr,
            PREF_DEGREE = :pref_degree,
            PREF_REMARKS = :pref_remarks
        WHERE PREF_CODE = :pref_code
    ");
    $updatePreference->execute([
        ':pref_name' => $pref_name,
        ':pref_addr' => $pref_addr,
        ':pref_degree' => $pref_degree,
        ':pref_remarks' => $pref_remarks,
        ':pref_code' => $pref_code
    ]);

    $_SESSION['success_message'] = "Application updated successfully.";
    header("Location: view.php?id=" . $app_code);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Scholarship Application - NCIP Admin</title>
    <link rel="icon" href="../assets/img/ncip-logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Edit Scholarship Application</h1>
                <a href="view.php?id=<?php echo $app_code; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Details
                </a>
            </div>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($applicant['app_last_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($applicant['app_first_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="middle_initial" class="form-label">Middle Initial</label>
                    <input type="text" class="form-control" id="middle_initial" name="middle_initial" maxlength="1" value="<?php echo htmlspecialchars($applicant['app_middle_initial']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" required value="<?php echo htmlspecialchars($applicant['app_date_of_birth']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="pob" class="form-label">Place of Birth</label>
                    <input type="text" class="form-control" id="pob" name="pob" required value="<?php echo htmlspecialchars($applicant['app_place_of_birth']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="Male" <?php if($applicant['app_gender'] == 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if($applicant['app_gender'] == 'Female') echo 'selected'; ?>>Female</option>
                        <option value="Other" <?php if($applicant['app_gender'] == 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="civil_status" class="form-label">Civil Status</label>
                    <input type="text" class="form-control" id="civil_status" name="civil_status" value="<?php echo htmlspecialchars($applicant['app_civil_status']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="ethno_group" class="form-label">Ethnic Group</label>
                    <input type="text" class="form-control" id="ethno_group" name="ethno_group" required value="<?php echo htmlspecialchars($applicant['app_ethno_group']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="phone_number" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($applicant['app_phone_number']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($applicant['app_email']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="addr_mail" class="form-label">Mailing Address</label>
                    <input type="text" class="form-control" id="addr_mail" name="addr_mail" value="<?php echo htmlspecialchars($applicant['addr_mail']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="addr_permanent" class="form-label">Permanent Address</label>
                    <input type="text" class="form-control" id="addr_permanent" name="addr_permanent" value="<?php echo htmlspecialchars($applicant['addr_permanent']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="addr_house_and_street" class="form-label">House/Street</label>
                    <input type="text" class="form-control" id="addr_house_and_street" name="addr_house_and_street" value="<?php echo htmlspecialchars($applicant['addr_house_and_street']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="addr_barangay" class="form-label">Barangay</label>
                    <input type="text" class="form-control" id="addr_barangay" name="addr_barangay" value="<?php echo htmlspecialchars($applicant['addr_barangay']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="addr_municipality" class="form-label">Municipality</label>
                    <input type="text" class="form-control" id="addr_municipality" name="addr_municipality" value="<?php echo htmlspecialchars($applicant['addr_municipality']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="addr_province" class="form-label">Province</label>
                    <input type="text" class="form-control" id="addr_province" name="addr_province" value="<?php echo htmlspecialchars($applicant['addr_province']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="educ_id" class="form-label">Education Category</label>
                    <select class="form-select" id="educ_id" name="educ_id">
                        <?php foreach($educationCategories as $cat): ?>
                            <option value="<?php echo $cat['educ_id']; ?>" <?php if($applicant['educ_id'] == $cat['educ_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($cat['educ_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="sch_name" class="form-label">School Name</label>
                    <input type="text" class="form-control" id="sch_name" name="sch_name" value="<?php echo htmlspecialchars($applicant['sch_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="sch_address" class="form-label">School Address</label>
                    <input type="text" class="form-control" id="sch_address" name="sch_address" value="<?php echo htmlspecialchars($applicant['sch_address']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="sch_year_grad" class="form-label">School Year Graduated</label>
                    <input type="number" class="form-control" id="sch_year_grad" name="sch_year_grad" value="<?php echo htmlspecialchars($applicant['sch_year_grad']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="sch_grade_avg" class="form-label">School Grade Average</label>
                    <input type="number" step="0.01" class="form-control" id="sch_grade_avg" name="sch_grade_avg" value="<?php echo htmlspecialchars($applicant['sch_grade_avg']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="sch_rank" class="form-label">School Rank</label>
                    <input type="text" class="form-control" id="sch_rank" name="sch_rank" value="<?php echo htmlspecialchars($applicant['sch_rank']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_relation" class="form-label">Family Relation</label>
                    <input type="text" class="form-control" id="fam_relation" name="fam_relation" value="<?php echo htmlspecialchars($applicant['fam_relation']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_is_alive" class="form-label">Family Is Alive</label>
                    <select class="form-select" id="fam_is_alive" name="fam_is_alive">
                        <option value="1" <?php if($applicant['fam_is_alive']) echo 'selected'; ?>>Yes</option>
                        <option value="0" <?php if(!$applicant['fam_is_alive']) echo 'selected'; ?>>No</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="fam_name" class="form-label">Family Name</label>
                    <input type="text" class="form-control" id="fam_name" name="fam_name" value="<?php echo htmlspecialchars($applicant['fam_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_addr" class="form-label">Family Address</label>
                    <input type="text" class="form-control" id="fam_addr" name="fam_addr" value="<?php echo htmlspecialchars($applicant['fam_addr']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_occup" class="form-label">Family Occupation</label>
                    <input type="text" class="form-control" id="fam_occup" name="fam_occup" value="<?php echo htmlspecialchars($applicant['fam_occup']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_office_addr" class="form-label">Family Office Address</label>
                    <input type="text" class="form-control" id="fam_office_addr" name="fam_office_addr" value="<?php echo htmlspecialchars($applicant['fam_office_addr']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_educ_attain" class="form-label">Family Education Attainment</label>
                    <input type="text" class="form-control" id="fam_educ_attain" name="fam_educ_attain" value="<?php echo htmlspecialchars($applicant['fam_educ_attain']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_ethno_group" class="form-label">Family Ethnic Group</label>
                    <input type="text" class="form-control" id="fam_ethno_group" name="fam_ethno_group" value="<?php echo htmlspecialchars($applicant['fam_ethno_group']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_annual_income" class="form-label">Family Annual Income</label>
                    <input type="number" step="0.01" class="form-control" id="fam_annual_income" name="fam_annual_income" value="<?php echo htmlspecialchars($applicant['fam_annual_income']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="fam_year_itr" class="form-label">Family Year ITR</label>
                    <input type="number" class="form-control" id="fam_year_itr" name="fam_year_itr" value="<?php echo htmlspecialchars($applicant['fam_year_itr']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="sib_last_name" class="form-label">Sibling Last Name</label>
                    <input type="text" class="form-control" id="sib_last_name" name="sib_last_name" value="<?php echo htmlspecialchars($applicant['sib_last_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="sib_first_name" class="form-label">Sibling First Name</label>
                    <input type="text" class="form-control" id="sib_first_name" name="sib_first_name" value="<?php echo htmlspecialchars($applicant['sib_first_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="sib_middle_initial" class="form-label">Sibling Middle Initial</label>
                    <input type="text" class="form-control" id="sib_middle_initial" name="sib_middle_initial" maxlength="1" value="<?php echo htmlspecialchars($applicant['sib_middle_initial']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="scholar_name" class="form-label">Scholarship Name</label>
                    <input type="text" class="form-control" id="scholar_name" name="scholar_name" value="<?php echo htmlspecialchars($applicant['scholar_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="scholar_course" class="form-label">Scholarship Course</label>
                    <input type="text" class="form-control" id="scholar_course" name="scholar_course" value="<?php echo htmlspecialchars($applicant['scholar_course']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="scholar_year" class="form-label">Scholarship Year</label>
                    <input type="text" class="form-control" id="scholar_year" name="scholar_year" value="<?php echo htmlspecialchars($applicant['scholar_year']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="pref_name" class="form-label">School Preference Name</label>
                    <input type="text" class="form-control" id="pref_name" name="pref_name" value="<?php echo htmlspecialchars($applicant['pref_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="pref_addr" class="form-label">School Preference Address</label>
                    <input type="text" class="form-control" id="pref_addr" name="pref_addr" value="<?php echo htmlspecialchars($applicant['pref_addr']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="pref_degree" class="form-label">School Preference Degree</label>
                    <input type="text" class="form-control" id="pref_degree" name="pref_degree" value="<?php echo htmlspecialchars($applicant['pref_degree']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="pref_remarks" class="form-label">School Preference Remarks</label>
                    <input type="text" class="form-control" id="pref_remarks" name="pref_remarks" value="<?php echo htmlspecialchars($applicant['pref_remarks']); ?>">
                </div>
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="view.php?id=<?php echo $app_code; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>