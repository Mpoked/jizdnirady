<?php

use CodeIgniter\Pager\PagerRenderer;

/**
 * Vlastní šablona stránkování s třídami Bootstrapu (ref. 07).
 * Kopie vendor/codeigniter4/framework/system/Pager/Views/default_full.php
 *
 * @var PagerRenderer $pager
 */
$pager->setSurroundCount(2);
?>

<nav aria-label="Stránkování">
	<ul class="pagination justify-content-center flex-wrap">
		<?php if ($pager->hasPrevious()) : ?>
			<li class="page-item">
				<a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="První">
					<span aria-hidden="true"><i class="fa-solid fa-angles-left"></i></span>
				</a>
			</li>
			<li class="page-item">
				<a class="page-link" href="<?= $pager->getPrevious() ?>" aria-label="Předchozí">
					<span aria-hidden="true"><i class="fa-solid fa-angle-left"></i></span>
				</a>
			</li>
		<?php endif ?>

		<?php foreach ($pager->links() as $link) : ?>
			<li class="page-item <?= $link['active'] ? 'active' : '' ?>">
				<a class="page-link" href="<?= $link['uri'] ?>">
					<?= $link['title'] ?>
				</a>
			</li>
		<?php endforeach ?>

		<?php if ($pager->hasNext()) : ?>
			<li class="page-item">
				<a class="page-link" href="<?= $pager->getNext() ?>" aria-label="Další">
					<span aria-hidden="true"><i class="fa-solid fa-angle-right"></i></span>
				</a>
			</li>
			<li class="page-item">
				<a class="page-link" href="<?= $pager->getLast() ?>" aria-label="Poslední">
					<span aria-hidden="true"><i class="fa-solid fa-angles-right"></i></span>
				</a>
			</li>
		<?php endif ?>
	</ul>
</nav>
