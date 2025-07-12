<?php
require_once '../config.php';

// File upload function
function handle_upload($file_input, $old_file = '') {
    if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        return $old_file;
    }

    $ext = pathinfo($_FILES[$file_input]['name'], PATHINFO_EXTENSION);
    $filename = $file_input . '_' . time() . '.' . $ext;
    $target = UPLOAD_DIR . $filename;

    if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $target)) {
        return $filename;
    }

    return $old_file;
}

// Fetch existing record
$result = $conn->query("SELECT * FROM company_profile LIMIT 1");
$existing = $result->fetch_assoc();

// Handle uploads
$logo = handle_upload('logo', $existing['logo'] ?? '');
$qr_code = handle_upload('qr_code', $existing['qr_code'] ?? '');

// Prepare sanitized values
$name = $conn->real_escape_string($_POST['name']);
$address = $conn->real_escape_string($_POST['address']);
$city = $conn->real_escape_string($_POST['city']);
$pin = $conn->real_escape_string($_POST['pin']);
$state = $conn->real_escape_string($_POST['state']);
$gstin = $conn->real_escape_string($_POST['gstin']);
$email = $conn->real_escape_string($_POST['email']);
$mobile = $conn->real_escape_string($_POST['mobile']);
$acc_name = $conn->real_escape_string($_POST['acc_name']);
$acc_number = $conn->real_escape_string($_POST['acc_number']);
$ifsc_code = $conn->real_escape_string($_POST['ifsc_code']);
$branch = $conn->real_escape_string($_POST['branch']);
$bank_name = $conn->real_escape_string($_POST['bank_name']);
$pan_number = $conn->real_escape_string($_POST['pan_number']);
$declaration = $conn->real_escape_string($_POST['declaration']);
$footer_text = $conn->real_escape_string($_POST['footer_text']);

if ($existing) {
    // Update
    $sql = "UPDATE company_profile SET 
        logo='$logo', name='$name', address='$address', city='$city', pin='$pin',
        state='$state', gstin='$gstin', email='$email', mobile='$mobile',
        acc_name='$acc_name', acc_number='$acc_number', ifsc_code='$ifsc_code',
        branch='$branch', bank_name='$bank_name', pan_number='$pan_number',
        qr_code='$qr_code', declaration='$declaration', footer_text='$footer_text'
        WHERE id = {$existing['id']}";
} else {
    // Insert
    $sql = "INSERT INTO company_profile (
        logo, name, address, city, pin, state, gstin, email, mobile,
        acc_name, acc_number, ifsc_code, branch, bank_name, pan_number,
        qr_code, declaration, footer_text
    ) VALUES (
        '$logo', '$name', '$address', '$city', '$pin', '$state', '$gstin', '$email', '$mobile',
        '$acc_name', '$acc_number', '$ifsc_code', '$branch', '$bank_name', '$pan_number',
        '$qr_code', '$declaration', '$footer_text'
    )";
}

if ($conn->query($sql)) {
    header("Location: company_profile.php?success=1");
} else {
    echo "Error: " . $conn->error;
}
?>
