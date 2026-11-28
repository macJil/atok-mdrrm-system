<?php if (!isset($pages, $section)) { exit; } ?>
        <?php if ($pages > 1): ?>
            <nav aria-label="Breakdown pages">Page <?= $page ?> of <?= $pages ?>
                <?php foreach (['Previous' => $page - 1, 'Next' => $page + 1] as $label => $destination): ?>
                    <?php if ($destination >= 1 && $destination <= $pages): ?>
                        <a href="?<?= h(http_build_query($dates + ['barangay'=>$area, 'section'=>$section, 'page'=>$destination])) ?>#<?= $section ?>"><?= $label ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
