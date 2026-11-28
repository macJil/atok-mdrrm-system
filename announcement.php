<?php
require __DIR__ . '/includes/session.php';
$user = requireRole('staff');
$categories = ['Disaster advisory', 'Evacuation instructions', 'Emergency announcement', 'Preparedness reminder'];
$adding = ($_GET['action'] ?? '') === 'add';
$withdrawing = ($_GET['action'] ?? '') === 'withdraw' && isset($_GET['id']);
$managing = $adding || isset($_GET['id']);
$notice = ['title' => '', 'category' => $categories[0], 'message' => '', 'status' => 'Draft', 'show_on_login' => false];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (isset($_GET['id'])) {
    $query = $pdo->prepare('SELECT * FROM announcements WHERE id = ?');
    $query->execute([$id]);
    $notice = $query->fetch();
    if (!$notice) {
        http_response_code(404);
        exit('Announcement not found.');
    }
}
$error = $_SESSION['announcement_conflict'] ?? '';
unset($_SESSION['announcement_conflict']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id && (string)$notice['revision'] !== posted('revision')) {
        $_SESSION['announcement_conflict'] = 'This notice changed. Review the latest version before trying again.';
        go('announcement.php?id=' . $id);
    }
    $action = posted('action');
    if ($action === 'withdraw' && $id) {
        $query = $pdo->prepare("UPDATE announcements SET status = 'Withdrawn', show_on_login = 0, revision=revision+1 WHERE id = ? AND revision=? AND status = 'Published'");
        $query->execute([$id, posted('revision')]);
        if (!$query->rowCount()) {
            $_SESSION['announcement_conflict'] = 'This notice changed. Review the latest version before trying again.';
            go('announcement.php?id=' . $id);
        }
        $_SESSION['announcement_notice'] = 'Announcement withdrawn. Its history has been retained.';
        go('announcement.php');
    }
    $notice['title'] = trim(posted('title'));
    $notice['message'] = trim(posted('message'));
    $notice['category'] = posted('category');
    $notice['show_on_login'] = posted('show_on_login') === 'yes';
    if ($notice['title'] === '' || mb_strlen($notice['title']) > 150 || $notice['message'] === '' || mb_strlen($notice['message']) > 3000) {
        $error = 'Enter a title of up to 150 characters and a message of up to 3,000 characters.';
    } elseif (!in_array($notice['category'], $categories, true) || !in_array($action, ['save', 'publish'], true)) {
        $error = 'Choose a listed category and action.';
    } else {
        $status = $action === 'publish' ? 'Published' : $notice['status'];
        $values = [$notice['title'], $notice['category'], $notice['message'], (int)$notice['show_on_login']];
        if ($id) {
            $query = $pdo->prepare("UPDATE announcements SET title=?, category=?, message=?, show_on_login=?,
                published_at=CASE WHEN status <> 'Published' AND ? = 'Published' THEN NOW() ELSE published_at END,
                status=?, revision=revision+1 WHERE id=? AND revision=?");
            $values[] = $status;
            $values[] = $status;
            $values[] = $id;
            $values[] = (int)posted('revision');
        } else {
            $query = $pdo->prepare("INSERT INTO announcements (title,category,message,show_on_login,status,published_at)
                VALUES (?,?,?,?,?,CASE WHEN ? = 'Published' THEN NOW() ELSE NULL END)");
            $values[] = $status;
            $values[] = $status;
        }
        $query->execute($values);
        if ($id && !$query->rowCount()) {
            $_SESSION['announcement_conflict'] = 'This notice changed. Review the latest version before trying again.';
            go('announcement.php?id=' . $id);
        }
        $created = !$id;
        if ($created) {
            $id = $pdo->lastInsertId();
        }
        $_SESSION['announcement_notice'] = $created ? 'Announcement created.' : 'Announcement updated.';
        go('announcement.php');
    }
}
$pageTitle = 'Manage announcements';
require __DIR__ . '/includes/header.php';
?>
<h1>Manage announcements</h1>
<?php if (isset($_SESSION['announcement_notice'])): ?>
    <p role="status"><?= h($_SESSION['announcement_notice']) ?></p>
    <?php unset($_SESSION['announcement_notice']); ?>
<?php endif; ?>
<?php if (!$managing): ?><p><a class="btn btn-primary" href="?action=add">New announcement</a></p><?php endif; ?>
<?php if ($withdrawing): ?>
    <section class="form-panel col-lg-7">
        <h2>Confirm withdrawal</h2>
        <p>Withdraw <strong><?= h($notice['title']) ?></strong>? It will no longer appear to residents.</p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="revision" value="<?= (int)$notice['revision'] ?>">
            <button class="btn btn-danger" name="action" value="withdraw">Withdraw announcement</button>
            <a class="btn btn-outline-secondary" href="?id=<?= (int)$id ?>">Cancel</a>
        </form>
    </section>
<?php elseif ($managing): ?>
<h2 class="h4"><?= $id ? 'Edit announcement' : 'New announcement' ?></h2>
<?php if ($id): ?>
    <p>Status: <strong><?= h($notice['status']) ?></strong></p>
<?php endif; ?>
<?php if ($error): ?>
    <p role="alert"><?= h($error) ?></p>
<?php endif; ?>
<form method="post" class="col-lg-8 form-panel workflow-form">
    <?php if ($id): ?><input type="hidden" name="revision" value="<?= (int)$notice['revision'] ?>"><?php endif; ?>
    <?= csrfField() ?>
    <label class="d-block mb-2">Title
        <input class="form-control" name="title" maxlength="150" required value="<?= h($notice['title']) ?>">
    </label>
    <label class="d-block mb-2">Category
        <select class="form-select" name="category">
            <?php foreach ($categories as $category): ?>
                <option <?= $notice['category'] === $category ? 'selected' : '' ?>><?= h($category) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="d-block mb-2">Message
        <textarea class="form-control" name="message" rows="4" maxlength="3000" required><?= h($notice['message']) ?></textarea>
    </label>
    <label class="d-block mb-3">
        <input type="checkbox" name="show_on_login" value="yes" <?= $notice['show_on_login'] ? 'checked' : '' ?>>
        Also show on login page when published
    </label>
    <button class="btn btn-primary" name="action" value="save"><?= $id ? 'Save changes' : 'Save draft' ?></button>
    <?php if ($notice['status'] !== 'Published'): ?>
        <button class="btn btn-outline-primary" name="action" value="publish">Publish announcement</button>
    <?php else: ?>
        <a class="btn btn-outline-danger" href="?action=withdraw&amp;id=<?= (int)$id ?>">Withdraw announcement</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="announcement.php">Cancel</a>
</form>
<?php else: ?>
<h2 class="h4 mt-4">Saved announcements</h2>
<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = searchPattern($search);
$query = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE title LIKE ? ESCAPE '!'");
$query->execute([$term]);
$total = (int)$query->fetchColumn();
[$page, $offset, $pages] = pageWindow($total, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$rows = $pdo->prepare("SELECT id,title,status,show_on_login FROM announcements WHERE title LIKE ? ESCAPE '!' ORDER BY id DESC LIMIT 10 OFFSET $offset");
$rows->execute([$term]);
?>
<form method="get" class="filter-panel"><label>Search titles <input class="form-control" name="q" value="<?= h($search) ?>"></label> <button class="btn btn-outline-secondary">Search</button></form>
<?php if (!$total): ?><p>No matching announcements.</p><?php endif; ?>
<?php foreach ($rows as $row): ?>
    <div class="dashboard-row content-card p-3">
        <p class="mb-0"><?= h($row['title']) ?> · <span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span>
            <?= $row['status'] === 'Published' && $row['show_on_login'] ? '· Public login page' : '' ?>
        </p>
        <a class="btn btn-outline-secondary" href="?id=<?= (int)$row['id'] ?>">Edit</a>
    </div>
<?php endforeach; ?>
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page + 1 ?>">Next</a><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
