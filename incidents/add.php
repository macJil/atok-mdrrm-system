<?php
require __DIR__ . '/access.php';
require_once __DIR__ . '/../map/boundary.php';
$staff = $user['role'] === 'staff';
$name = '';
$phone = '';
$source = $staff ? 'Walk-in' : 'Resident';
$type = posted('type');
$area = posted('barangay');
$location = trim(posted('location'));
$description = trim(posted('description'));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$staff) {
    $name = $user['full_name'];
    $query = $pdo->prepare('SELECT phone FROM accounts WHERE id=?');
    $query->execute([$user['id']]);
    $phone = (string)$query->fetchColumn();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(posted('name'));
    $phone = trim(posted('phone'));
    if ($staff) {
        $source = posted('source');
    }
    if ($staff && !in_array($source, ['Phone','Walk-in'], true)) {
        $error = 'Choose phone or walk-in intake.';
    } elseif (!in_array($type, $types, true) || ($area !== '' && !in_array($area, BARANGAYS, true))) {
        $error = 'Choose a listed incident type and barangay.';
    } elseif ($name === '' || mb_strlen($name) > 100 || !validMobileNumber($phone)) {
        $error = 'Enter a contact name and mobile number such as 09123456789 or +639123456789.';
    } elseif ($location === '' || mb_strlen($location) > 255 || $description === '' || mb_strlen($description) > 4000) {
        $error = 'Enter a written location (up to 255 characters) and description (up to 4,000).';
    }
    $lat = trim(posted('latitude'));
    $lng = trim(posted('longitude'));
    $problems = locationErrors($location, $lat, $lng);
    if ($error === '' && $problems) {
        $error = $problems[0];
    }
    $lat = $lat === '' ? null : $lat;
    $lng = $lng === '' ? null : $lng;
    $image = null;
    $mime = null;
    $file = $_FILES['image'] ?? null;
    if ($error === '' && $file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'The image upload failed. Choose a smaller file and try again.';
        } elseif (!is_uploaded_file($file['tmp_name']) || $file['size'] > 2 * 1024 * 1024) {
            $error = 'Choose an image no larger than 2 MiB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $info = @getimagesize($file['tmp_name']);
            if (!$info || $info['mime'] !== $mime || !in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
                $error = 'Choose a valid JPEG, PNG or WebP image.';
            } else {
                $image = file_get_contents($file['tmp_name']);
                if ($image === false) {
                    $error = 'Could not read the uploaded image.';
                }
            }
        }
    }
    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $query = $pdo->prepare('INSERT INTO incidents(submitted_by,source,contact_name,contact_phone,incident_type,barangay,location,description,latitude,longitude) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $query->execute([$user['id'],$source,$name,$phone,$type,$area ?: null,$location,$description,$lat,$lng]);
            $id = $pdo->lastInsertId();
            if ($image !== null && $mime !== null) {
                $query = $pdo->prepare('INSERT INTO incident_images(incident_id,mime,image_data) VALUES(?,?,?)');
                $query->execute([$id,$mime,$image]);
            }
            $pdo->commit();
            go('incidents/view.php?id=' . $id);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Report could not be saved. Please try again.';
        }
    }
}
$pageTitle = $staff ? 'Record an incident' : 'Report an incident';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= $pageTitle ?></h1>
<?php if ($error): ?><p role="alert"><?= h($error) ?> Choose any attachment again before submitting.</p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="incident-report-form form-panel workflow-form">
    <?= csrfField() ?>
    <section class="form-section">
        <h2>Reporter and incident</h2>
    <?php if ($staff): ?>
        <label class="d-block mb-2">Received through
            <select class="form-select" name="source"><option>Walk-in</option><option <?= $source === 'Phone' ? 'selected' : '' ?>>Phone</option></select>
        </label>
    <?php endif; ?>
    <label class="d-block mb-2">Contact name<input class="form-control" required maxlength="100" name="name" value="<?= h($name) ?>"></label>
    <label class="d-block mb-2">Mobile number<input class="form-control" required maxlength="13" type="tel" name="phone" value="<?= h($phone) ?>"></label>
    <label class="d-block mb-2">Incident type<select name="type" class="form-select" required>
        <option value="">Choose type</option>
        <?php foreach ($types as $value): ?><option <?= $type === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?>
    </select></label>
    <label class="d-block mb-2">Barangay<select class="form-select" name="barangay">
        <option value="">Awaiting confirmation</option>
        <?php foreach (BARANGAYS as $value): ?><option <?= $area === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?>
    </select></label>
    </section>
    <section class="form-section">
        <h2>Location and details</h2>
    <?php
    $pickerKind = 'incidents';
    $point = ['location' => $location];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $point['latitude'] = posted('latitude');
        $point['longitude'] = posted('longitude');
    }
    require __DIR__ . '/../map/picker.php';
    ?>
    <label class="d-block mb-2">What happened?<textarea class="form-control" name="description" required maxlength="4000"><?= h($description) ?></textarea></label>
    <label class="d-block mb-3">Photo (optional, JPEG, PNG or WebP up to 2 MiB)<input type="file" class="form-control" name="image" accept="image/jpeg,image/png,image/webp"></label>
    </section>
    <div class="incident-form-actions">
        <button class="btn btn-primary">Submit report</button>
        <a class="btn btn-outline-secondary" href="index.php">Return to reports</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
