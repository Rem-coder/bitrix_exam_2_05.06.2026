<?php
/** Standalone contract tests. No Bitrix bootstrapping and no real database access. */
namespace Bitrix\Main {
    class Loader { public static function includeModule($id) { return true; } }
    class Application {
        public static function getConnection() { return new class {
            public function startTransaction() {}
            public function commitTransaction() {}
            public function rollbackTransaction() {}
        }; }
    }
    class ModuleManager {
        public static $installed = false;
        public static function registerModule($id) { self::$installed = true; }
        public static function unRegisterModule($id) { self::$installed = false; }
    }
}
namespace {
    class CModule { public $MODULE_VERSION; public $MODULE_VERSION_DATE; }
    class Rows {
        private $rows;
        public function __construct($rows) { $this->rows = array_values($rows); }
        public function Fetch() { return array_shift($this->rows) ?: false; }
    }
    class CLanguage { public static function GetList(&$by, &$order) { return new Rows([['LID' => 'ru']]); } }
    class CSite { public static function GetList(&$by, &$order, $filter) { return new Rows([['LID' => 's1']]); } }
    class CIBlockType {
        public static $types = [];
        public static function GetByID($id) { return new Rows(isset(self::$types[$id]) ? [self::$types[$id]] : []); }
        public function Add($fields) { self::$types[$fields['ID']] = $fields; return $fields['ID']; }
    }
    class CIBlock {
        public static $blocks = [];
        public static function GetList($order, $filter) {
            return new Rows(array_filter(self::$blocks, static function ($row) use ($filter) {
                return $row['IBLOCK_TYPE_ID'] === $filter['=TYPE'] && $row['XML_ID'] === $filter['=XML_ID'];
            }));
        }
        public function Add($fields) {
            $id = count(self::$blocks) + 1;
            self::$blocks[$id] = ['ID' => $id] + $fields;
            return $id;
        }
    }
    class CIBlockElement {
        public static $elements = [];
        public static $sequence = 0;
        public static function GetList($order, $filter, $group, $navigation, $select) {
            $rows = array_filter(self::$elements, static function ($row) use ($filter) {
                return $row['IBLOCK_ID'] === $filter['IBLOCK_ID']
                    && (!isset($filter['=XML_ID']) || $row['XML_ID'] === $filter['=XML_ID']);
            });
            return new Rows(array_slice(array_values($rows), 0, $navigation['nTopCount']));
        }
        public function Add($fields) {
            $id = ++self::$sequence;
            self::$elements[$id] = ['ID' => $id] + $fields;
            return $id;
        }
        public static function Delete($id) { unset(self::$elements[$id]); return true; }
    }
    $sessionValid = true;
    function check_bitrix_sessid() { return $GLOBALS['sessionValid']; }
    $USER = new class { public $admin = true; public function IsAdmin() { return $this->admin; } };
    $APPLICATION = new class { public $error; public function ThrowException($text) { $this->error = $text; } };
    function check($condition, $message) {
        if (!$condition) { throw new \RuntimeException($message); }
        echo "PASS: $message\n";
    }
    $root = sys_get_temp_dir() . '/codexdev-test-' . bin2hex(random_bytes(8));
    mkdir($root, 0755, true);
    $_SERVER['DOCUMENT_ROOT'] = $root;
    require dirname(__DIR__) . '/install/index.php';
    $module = new \codexdev();
    try {
        check($module->DoInstall(), 'first install succeeds');
        check(count(CIBlock::$blocks) === 1 && count(CIBlockElement::$elements) === 3, 'one block and three elements');
        $id = array_key_first(CIBlock::$blocks);
        CIBlockElement::$elements[1]['NAME'] = 'Edited sample';
        check($module->DoInstall(), 'second install succeeds');
        check(count(CIBlock::$blocks) === 1 && count(CIBlockElement::$elements) === 3, 'second install does not duplicate data');
        check(CIBlockElement::$elements[1]['NAME'] === 'Edited sample', 'existing sample edits survive reinstall');
        check(is_file($root . '/local/codexdev/index.php') && is_file($root . '/local/components/codexdev/metadata.list/component.php'), 'page and component deployed');
        file_put_contents($root . '/local/codexdev/keep.txt', 'unrelated');
        (new CIBlockElement())->Add(['IBLOCK_ID' => $id, 'XML_ID' => 'user.record', 'NAME' => 'User data']);
        (new CIBlockElement())->Add(['IBLOCK_ID' => 999, 'XML_ID' => 'other.block', 'NAME' => 'Other block']);
        check($module->DoUninstall(), 'uninstall succeeds');
        check(count(CIBlock::$blocks) === 1 && isset(CIBlock::$blocks[$id]), 'block identity survives uninstall');
        check(count(CIBlockElement::$elements) === 1 && reset(CIBlockElement::$elements)['IBLOCK_ID'] === 999, 'all owned-block elements removed; other block untouched');
        check(!file_exists($root . '/local/codexdev/index.php') && !file_exists($root . '/local/components/codexdev/metadata.list'), 'deployed files and empty component directories removed');
        check(is_file($root . '/local/codexdev/keep.txt'), 'unrelated local file preserved');
        check(!\Bitrix\Main\ModuleManager::$installed, 'module unregistered');
        check($module->DoInstall() && count(CIBlock::$blocks) === 1 && count(CIBlockElement::$elements) === 4, 'install after uninstall restores three samples in the same block');
        check($module->DoUninstall(), 'second uninstall succeeds');
        file_put_contents($root . '/local/codexdev/index.php', 'foreign file');
        check($module->DoInstall() === false, 'conflicting target prevents installation');
        check(file_get_contents($root . '/local/codexdev/index.php') === 'foreign file', 'conflicting target not overwritten or removed');
        check(count(CIBlockElement::$elements) === 1, 'file conflict does not seed data');
        unlink($root . '/local/codexdev/index.php');
        $USER->admin = false;
        check($module->DoInstall() === false && $module->DoUninstall() === false, 'non-admin lifecycle rejected');
        $USER->admin = true;
        $sessionValid = false;
        check($module->DoInstall() === false && $module->DoUninstall() === false, 'invalid CSRF session rejected');
        echo "Lifecycle contract checks completed (mock API; not a Bitrix integration test).\n";
    } finally {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($root);
    }
}
