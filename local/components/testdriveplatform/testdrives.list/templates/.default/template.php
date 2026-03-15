<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
?>
<style>
    .test-drives-table th, .test-drives-table td {
        border-width: 1px;
        border-style: solid;
        padding: 5px;
    }
</style>
<div class="test-drives-list">
    <h2>Список бронирований</h2>
    <?php if (!empty($arResult['ITEMS'])): ?>
        <table style="border-collapse: collapse;" class="test-drives-table">
            <thead>
                <tr>
                    <th>ID Бронирования</th>
                    <th>Модель авто</th>
                    <th>Время начала</th>
                    <th>Время окончания</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($arResult['ITEMS'] as $item): ?>
                    <tr>
                        <td><?= $item['ID'] ?></td>
                        <td><?= htmlspecialcharsbx($item['CAR_MODEL']) ?></td>
                        <td><?= $item['DATE_START'] ?></td>
                        <td><?= $item['DATE_END'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Нет доступных бронирований.</p>
    <?php endif; ?>
</div>
