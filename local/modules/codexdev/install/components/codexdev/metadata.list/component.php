<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

if (!\Bitrix\Main\Loader::includeModule('codexdev')) {
    ShowError('Модуль CodexDev не установлен.');
    return;
}
try {
    $iblock = \CodexDev\Metadata::find(true);
    if (!$iblock || $iblock['ACTIVE'] !== 'Y') {
        ShowError('Инфоблок «Метадата» не найден или недоступен.');
        return;
    }
    $arResult['ITEMS'] = [];
    $elements = \CIBlockElement::GetList(
        ['SORT' => 'ASC', 'ID' => 'ASC'],
        ['IBLOCK_ID' => (int) $iblock['ID'], 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y',
            'CHECK_PERMISSIONS' => 'Y', 'MIN_PERMISSION' => 'R'],
        false,
        ['nTopCount' => 10],
        ['ID', 'NAME', 'PREVIEW_TEXT']
    );
    while ($element = $elements->Fetch()) {
        $arResult['ITEMS'][] = $element;
    }
    $this->IncludeComponentTemplate();
} catch (\Throwable $exception) {
    AddMessage2Log((string) $exception, 'codexdev');
    ShowError('Не удалось загрузить метаданные. Обратитесь к администратору.');
}
