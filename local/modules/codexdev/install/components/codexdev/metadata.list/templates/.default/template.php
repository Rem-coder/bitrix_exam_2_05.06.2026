<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }
?>
<?php if (!$arResult['ITEMS']): ?>
    <p>В инфоблоке пока нет элементов.</p>
<?php else: ?>
    <ul>
        <?php foreach ($arResult['ITEMS'] as $item): ?>
            <li>
                <strong><?= htmlspecialcharsbx($item['NAME']) ?></strong>
                <p><?= nl2br(htmlspecialcharsbx($item['PREVIEW_TEXT'])) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
