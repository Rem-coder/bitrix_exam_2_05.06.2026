<?
use \Bitrix\Main\Localization\Loc;
global $APPLICATION;

$currentValue = $APPLICATION->GetPageProperty("ex2_meta");
if(!$currentValue){
    $currentValue = $APPLICATION->GetDirProperty("ex2_meta");
}

$validValue = str_replace('#count#', $arResult['COUNT_RECENZ'], $currentValue);
$APPLICATION->SetPageProperty("ex2_meta", $validValue);

if($arResult['COUNT_RECENZ'] > 0){

    ob_start();
    ?>
    <div id="filial-special" class="information-block">
	<div class="top"></div>
	<div class="information-block-inner">
		<h3><?=Loc::GetMessage('TITLE_INFORMATION_BLOCK');?></h3>
		<div class="special-product">
			<div class="special-product-title">
				<?=$arResult['MAIN_RECENZ_NAME']?>
			</div>
		</div>
	</div>
	<div class="bottom"></div>
    </div>
    <?
    $content = ob_get_clean();

    $APPLICATION->AddViewContent('products_main_recenz', $content);
}