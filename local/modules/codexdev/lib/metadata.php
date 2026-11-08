<?php
namespace CodexDev;

use Bitrix\Main\Loader;
use RuntimeException;

/** All database access stays in this module; identity does not depend on numeric IDs. */
final class Metadata
{
    public const TYPE = 'codexdev';
    public const CODE = 'codexdev_metadata';
    public const XML_ID = 'codexdev.metadata';

    public static function requireIblock(): void
    {
        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Для CodexDev требуется установленный модуль «Информационные блоки».');
        }
    }

    public static function find(bool $checkPermissions = false): ?array
    {
        self::requireIblock();
        $filter = [
            '=TYPE' => self::TYPE,
            '=XML_ID' => self::XML_ID,
            'CHECK_PERMISSIONS' => $checkPermissions ? 'Y' : 'N',
            'MIN_PERMISSION' => 'R',
        ];
        if ($checkPermissions && defined('SITE_ID')) {
            $filter['SITE_ID'] = SITE_ID;
        }
        $result = \CIBlock::GetList([], $filter);
        $found = null;
        while ($row = $result->Fetch()) {
            if ($found !== null) {
                throw new RuntimeException('Найдено несколько инфоблоков CodexDev. Устраните неоднозначность вручную.');
            }
            $found = $row;
        }
        return $found;
    }

    public static function install(): int
    {
        self::requireIblock();
        if (!\CIBlockType::GetByID(self::TYPE)->Fetch()) {
            $languages = [];
            $by = 'sort';
            $order = 'asc';
            $rows = \CLanguage::GetList($by, $order);
            while ($language = $rows->Fetch()) {
                $languages[$language['LID']] = [
                    'NAME' => 'CodexDev', 'ELEMENT_NAME' => 'Метадата',
                ];
            }
            $type = new \CIBlockType();
            if (!$type->Add(['ID' => self::TYPE, 'SECTIONS' => 'N', 'IN_RSS' => 'N', 'LANG' => $languages])) {
                throw new RuntimeException('Не удалось создать тип инфоблока: ' . $type->LAST_ERROR);
            }
        }
        $existing = self::find();
        if ($existing) {
            $id = (int) $existing['ID'];
        } else {
            $sites = [];
            $by = 'sort';
            $order = 'asc';
            $rows = \CSite::GetList($by, $order, ['ACTIVE' => 'Y']);
            while ($site = $rows->Fetch()) {
                $sites[] = $site['LID'];
            }
            if (!$sites) {
                throw new RuntimeException('Для установки CodexDev нужен хотя бы один активный сайт.');
            }
            $iblock = new \CIBlock();
            $id = (int) $iblock->Add([
                'IBLOCK_TYPE_ID' => self::TYPE, 'CODE' => self::CODE,
                'XML_ID' => self::XML_ID, 'NAME' => 'Метадата',
                'ACTIVE' => 'Y', 'SITE_ID' => $sites, 'SORT' => 500,
                'VERSION' => 2, 'GROUP_ID' => [1 => 'X', 2 => 'R'],
                'LIST_PAGE_URL' => '/local/codexdev/index.php',
            ]);
            if (!$id) {
                throw new RuntimeException('Не удалось создать инфоблок: ' . $iblock->LAST_ERROR);
            }
        }
        $samples = [
            ['title', 'Название проекта', 'CodexDev — собственные доработки проекта.'],
            ['description', 'Описание проекта', 'Тестовые метаданные для разработки на Bitrix Framework.'],
            ['version', 'Версия проекта', '1.0.0'],
        ];
        foreach ($samples as $index => [$code, $name, $text]) {
            $xmlId = self::XML_ID . '.' . $code;
            if (\CIBlockElement::GetList([], ['IBLOCK_ID' => $id, '=XML_ID' => $xmlId], false, ['nTopCount' => 1], ['ID'])->Fetch()) {
                continue;
            }
            $element = new \CIBlockElement();
            if (!$element->Add([
                'IBLOCK_ID' => $id, 'XML_ID' => $xmlId, 'CODE' => $code,
                'NAME' => $name, 'SORT' => ($index + 1) * 100, 'ACTIVE' => 'Y',
                'PREVIEW_TEXT' => $text, 'PREVIEW_TEXT_TYPE' => 'text',
            ])) {
                throw new RuntimeException('Не удалось добавить тестовые данные: ' . $element->LAST_ERROR);
            }
        }
        return $id;
    }

    /** Intentionally clears ALL elements, including user-added ones, but keeps the block. */
    public static function clear(): void
    {
        $iblock = self::find();
        if (!$iblock) {
            return;
        }
        // Read a fresh batch after every deletion; do not paginate a shrinking result set.
        while ($element = \CIBlockElement::GetList([], [
            'IBLOCK_ID' => (int) $iblock['ID'], 'CHECK_PERMISSIONS' => 'N',
        ], false, ['nTopCount' => 1], ['ID'])->Fetch()) {
            if (!\CIBlockElement::Delete((int) $element['ID'])) {
                throw new RuntimeException('Не удалось удалить элемент #' . (int) $element['ID'] . '. Удаление модуля остановлено.');
            }
        }
    }
}
