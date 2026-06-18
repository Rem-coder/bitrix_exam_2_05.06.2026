<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
IncludeTemplateLangFile(__FILE__);
?>
			</div>
		</div>
		<div id="space-for-footer"></div>
	</div>
	
	<div id="footer">
	
		<div id="copyright">
<?
$APPLICATION->IncludeFile(
	SITE_DIR."include/copyright.php",
	Array(),
	Array("MODE"=>"html")
);
?>
		</div>
		<div class="footer-links">	
<?
$APPLICATION->IncludeComponent("bitrix:menu", "bottom", array(
	"ROOT_MENU_TYPE" => "bottom",
	"MENU_CACHE_TYPE" => "N",
	"MENU_CACHE_TIME" => "36000000",
	"MENU_CACHE_USE_GROUPS" => "Y",
	"MENU_CACHE_GET_VARS" => array(
	),
	"MAX_LEVEL" => "1",
	"CHILD_MENU_TYPE" => "left",
	"USE_EXT" => "N",
	"ALLOW_MULTI_SELECT" => "N"
	),
	false
);
?>
		</div>
		<div id="footer-design"><?=GetMessage("FOOTER_DISIGN")?></div>
	</div>

	<div>
<?
	use \Bitrix\Main\Type\DateTime as BitrixDateTime;

	$lastRunDate = "";
	$lastDate = $lastRunDate ? $lastRunDate : (new BitrixDateTime("2000-01-01 00:00:00", "Y-m-d H:i:s"))->toString();
	$currentDate = new BitrixDateTime();
	
	$dateString = '18.06.2026 03:19:01';

	$res = CIBlockElement::GetList(
		[],
		[
			"IBLOCK_ID" => ID_IBLOCK_RECENZ,
			"DATE_MODIFY_FROM" => $lastDate,
			"DATE_MODIFY_TO" => $dateString
		],
		[]
	);

	$lastDate = "MyAgent::Agent_ex_610('".(new BitrixDateTime())->toString()."');";


	echo "<pre>".htmlspecialchars(print_r($res, true))."</pre>";

	#while($val = $res->GetNext())
	#{
		#echo "<pre>".htmlspecialchars(print_r($val, true))."</pre>";
	#}


?>
</div>

</body>
</html>