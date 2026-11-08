<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->SetTitle('Метадата');
$APPLICATION->IncludeComponent('codexdev:metadata.list', '', []);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
