<?php
require_once '../config.php';
require_once '../includes/header.php';

$result = $conn->query("SELECT * FROM company_profile LIMIT 1");
$profile = $result->fetch_assoc();
?>

<div class="container py-4">
    <h3 class="mb-4">Company Profile</h3>
    <form method="POST" action="company_profile_save.php" enctype="multipart/form-data" class="shadow-sm bg-white p-4 rounded">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Company Logo (Max 2MB)</label>
                <input type="file" name="logo" class="form-control">
                <?php if (!empty($profile['logo'])): ?>
                    <img src="../uploads/<?= $profile['logo'] ?>" alt="Logo" height="50" class="mt-2">
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">QR Code (Max 2MB)</label>
                <input type="file" name="qr_code" class="form-control">
                <?php if (!empty($profile['qr_code'])): ?>
                    <img src="../uploads/<?= $profile['qr_code'] ?>" alt="QR Code" height="50" class="mt-2">
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label class="form-label">Company Name</label>
                <input type="text" name="name" class="form-control" value="<?= $profile['name'] ?? '' ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Company Email</label>
                <input type="email" name="email" class="form-control" value="<?= $profile['email'] ?? '' ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Mobile Number</label>
                <input type="text" name="mobile" class="form-control" value="<?= $profile['mobile'] ?? '' ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Company GSTIN</label>
                <input type="text" name="gstin" class="form-control" value="<?= $profile['gstin'] ?? '' ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= $profile['address'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= $profile['city'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">PIN</label>
                <input type="text" name="pin" class="form-control" value="<?= $profile['pin'] ?? '' ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="<?= $profile['state'] ?? '' ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">A/C Holder Name</label>
                <input type="text" name="acc_name" class="form-control" value="<?= $profile['acc_name'] ?? '' ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Account Number</label>
                <input type="text" name="acc_number" class="form-control" value="<?= $profile['acc_number'] ?? '' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Bank Name</label>
                <input type="text" name="bank_name" class="form-control" value="<?= $profile['bank_name'] ?? '' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Branch</label>
                <input type="text" name="branch" class="form-control" value="<?= $profile['branch'] ?? '' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">IFSC Code</label>
                <input type="text" name="ifsc_code" class="form-control" value="<?= $profile['ifsc_code'] ?? '' ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Company PAN</label>
                <input type="text" name="pan_number" class="form-control" value="<?= $profile['pan_number'] ?? '' ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Declaration</label>
                <textarea name="declaration" class="form-control" rows="2"><?= $profile['declaration'] ?? '' ?></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Footer Text</label>
                <textarea name="footer_text" class="form-control" rows="2"><?= $profile['footer_text'] ?? '' ?></textarea>
            </div>
        </div>
        <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
