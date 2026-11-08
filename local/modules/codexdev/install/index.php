<?php
use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

require_once dirname(__DIR__) . '/lib/metadata.php';

class codexdev extends CModule
{
    public $MODULE_ID = 'codexdev';
    public $MODULE_NAME = 'CodexDev';
    public $MODULE_DESCRIPTION = 'Собственные доработки: инфоблок «Метадата» и страница его элементов.';
    public $PARTNER_NAME = 'CodexDev';
    public $MODULE_GROUP_RIGHTS = 'N';
    private $createdFiles = [];

    public function __construct()
    {
        $arModuleVersion = [];
        require __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    /** Explicit file ownership: never overwrite an unrelated local customization. */
    private function files(): array
    {
        $root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $component = $root . '/local/components/codexdev/metadata.list/';
        $files = [__DIR__ . '/public/index.php' => $root . '/local/codexdev/index.php'];
        foreach (['.description.php', 'component.php', 'templates/.default/template.php'] as $relative) {
            $files[__DIR__ . '/components/codexdev/metadata.list/' . $relative] = $component . $relative;
        }
        return $files;
    }

    public function InstallFiles()
    {
        $files = $this->files();
        foreach ($files as $source => $destination) {
            if (!is_file($source)) {
                throw new RuntimeException('Отсутствует файл поставки CodexDev: ' . basename($source));
            }
            if (file_exists($destination) && (!is_file($destination) || hash_file('sha256', $source) !== hash_file('sha256', $destination))) {
                throw new RuntimeException('Установка остановлена: целевой файл уже существует: ' . $destination);
            }
        }
        foreach ($files as $source => $destination) {
            if (file_exists($destination)) {
                continue;
            }
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('Не удалось создать каталог: ' . $directory);
            }
            // Exclusive creation also protects against a file appearing after preflight.
            $stream = fopen($destination, 'x');
            if ($stream === false) {
                throw new RuntimeException('Не удалось создать файл: ' . $destination);
            }
            $this->createdFiles[] = $destination;
            $contents = file_get_contents($source);
            $written = $contents === false ? false : fwrite($stream, $contents);
            fclose($stream);
            if ($contents === false || $written !== strlen($contents)) {
                throw new RuntimeException('Не удалось записать файл: ' . $destination);
            }
        }
        return true;
    }

    private function pruneDirectories(): void
    {
        $root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        foreach ([
            '/local/components/codexdev/metadata.list/templates/.default',
            '/local/components/codexdev/metadata.list/templates',
            '/local/components/codexdev/metadata.list',
            '/local/components/codexdev', '/local/codexdev',
        ] as $relative) {
            $directory = $root . $relative;
            if (is_dir($directory) && count(scandir($directory)) === 2) {
                if (!rmdir($directory)) {
                    throw new RuntimeException('Не удалось удалить каталог: ' . $directory);
                }
            }
        }
    }

    public function UnInstallFiles()
    {
        foreach ($this->files() as $destination) {
            if (file_exists($destination) && !unlink($destination)) {
                throw new RuntimeException('Не удалось удалить файл: ' . $destination);
            }
        }
        $this->pruneDirectories();
        return true;
    }

    public function InstallDB()
    {
        \CodexDev\Metadata::install();
        return true;
    }

    public function UnInstallDB()
    {
        \CodexDev\Metadata::clear();
        return true;
    }

    public function DoInstall()
    {
        global $APPLICATION, $USER;
        if (!is_object($USER) || !$USER->IsAdmin() || !check_bitrix_sessid()) {
            $APPLICATION->ThrowException('Недостаточно прав или истекла сессия.');
            return false;
        }
        $connection = Application::getConnection();
        $this->createdFiles = [];
        $connection->startTransaction();
        try {
            $this->InstallFiles();
            $this->InstallDB();
            ModuleManager::registerModule($this->MODULE_ID);
            $connection->commitTransaction();
            return true;
        } catch (\Throwable $exception) {
            $connection->rollbackTransaction();
            foreach (array_reverse($this->createdFiles) as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $this->pruneDirectories();
            $APPLICATION->ThrowException($exception->getMessage());
            return false;
        }
    }

    public function DoUninstall()
    {
        global $APPLICATION, $USER;
        if (!is_object($USER) || !$USER->IsAdmin() || !check_bitrix_sessid()) {
            $APPLICATION->ThrowException('Недостаточно прав или истекла сессия.');
            return false;
        }
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            $this->UnInstallDB();
            $this->UnInstallFiles();
            ModuleManager::unRegisterModule($this->MODULE_ID);
            $connection->commitTransaction();
            return true;
        } catch (\Throwable $exception) {
            $connection->rollbackTransaction();
            $APPLICATION->ThrowException($exception->getMessage());
            return false;
        }
    }
}
