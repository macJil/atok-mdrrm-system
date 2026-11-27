<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$categories = ['Disaster advisory', 'Evacuation instructions', 'Emergency announcement', 'Preparedness reminder'];
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
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = posted('action');
    if ($action === 'withdraw' && $id) {
        $query = $pdo->prepare("UPDATE announcements SET status = 'Withdrawn', show_on_login = 0 WHERE id = ? AND status = 'Published'");
        $query->execute([$id]);
        go('announcements/index.php?id=' . $id);
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
                status=? WHERE id=? AND status=?");
            $values[] = $status;
            $values[] = $status;
            $values[] = $id;
            $values[] = $notice['status'];
        } else {
            $query = $pdo->prepare("INSERT INTO announcements (title,category,message,show_on_login,status,published_at)
                VALUES (?,?,?,?,?,CASE WHEN ? = 'Published' THEN NOW() ELSE NULL END)");
            $values[] = $status;
            $values[] = $status;
        }
        $query->execute($values);
        if (!$id) {
            $id = $pdo->lastInsertId();
        }
        go('announcements/index.php?id=' . $id);
    }
}
$pageTitle = 'Manage announcements';
require __DIR__ . '/../includes/header.php';
?>
<h1>Manage announcements</h1>
<h2 class="h4"><?= $id ? 'Edit announcement' : 'New announcement' ?></h2>
<?php if ($id): ?>
    <p>Status: <strong><?= htmlspecialchars($notice['status']) ?></strong></p>
<?php endif; ?>
<?php if ($error): ?>
    <p role="alert"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<form method="post" class="col-lg-7">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block mb-2">Title
        <input class="form-control" name="title" maxlength="150" required value="<?= htmlspecialchars($notice['title']) ?>">
    </label>
    <label class="d-block mb-2">Category
        <select class="form-select" name="category">
            <?php foreach ($categories as $category): ?>
                <option <?= $notice['category'] === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="d-block mb-2">Message
        <textarea class="form-control" name="message" rows="4" maxlength="3000" required><?= htmlspecialchars($notice['message']) ?></textarea>
    </label>
    <label class="d-block mb-3">
        <input type="checkbox" name="show_on_login" value="yes" <?= $notice['show_on_login'] ? 'checked' : '' ?>>
        Also show on login page when published
    </label>
    <button class="btn btn-primary" name="action" value="save"><?= $id ? 'Save changes' : 'Save draft' ?></button>
    <?php if ($notice['status'] !== 'Published'): ?>
        <button class="btn btn-outline-primary" name="action" value="publish">Publish announcement</button>
    <?php else: ?>
        <button class="btn btn-outline-danger" name="action" value="withdraw" formnovalidate>Withdraw announcement</button>
    <?php endif; ?>
    <?php if ($id): ?>
        <a class="btn btn-outline-secondary" href="index.php">New announcement</a>
    <?php endif; ?>
</form>
<h2 class="h4 mt-4">Saved announcements</h2>
<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = '%' . strtr($search, ['!'=>'!!', '%'=>'!%', '_'=>'!_']) . '%';
$query = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE title LIKE ? ESCAPE '!'");
$query->execute([$term]);
$total = (int)$query->fetchColumn();
$pages = max(1, (int)ceil($total / 10));
$page = min($pages, max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)));
$offset = ($page - 1) * 10;
$rows = $pdo->prepare("SELECT id,title,status,show_on_login FROM announcements WHERE title LIKE ? ESCAPE '!' ORDER BY id DESC LIMIT 10 OFFSET $offset");
$rows->execute([$term]);
?>
<form method="get"><label>Search titles <input class="form-control" name="q" value="<?= htmlspecialchars($search) ?>"></label> <button class="btn btn-outline-secondary">Search</button></form>
<?php if (!$total): ?><p>No matching announcements.</p><?php endif; ?>
<?php foreach ($rows as $row): ?>
    <p>
        <?= htmlspecialchars($row['title']) ?> · <?= htmlspecialchars($row['status']) ?>
        <?= $row['status'] === 'Published' && $row['show_on_login'] ? '· Public login page' : '' ?>
        <a class="btn btn-outline-secondary" href="?id=<?= (int)$row['id'] ?>">Edit</a>
    </p>
<?php endforeach; ?>
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page + 1 ?>">Next</a><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
