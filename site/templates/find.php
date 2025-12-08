<?
GLOBAL $devUser, $user, $lang;
?>
<h1><?=$lang['find']?></h1>
<div class="find-table">
<?
	$props = (new MProperties())->getItems();

	foreach ($props as $prop) {
		echo html::RenderField(['type' => $prop['type']], $prop);
	}
?>
</div>