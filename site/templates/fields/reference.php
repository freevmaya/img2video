
<?
	$refList = (new MReferenceValues())->getItems(['prop_id'=>$value['id']]);
	if (count($refList) > 0) {
		?><p><?=$value['name']?></p>
		<select>
		<?

		foreach ($refList as $refValue) {
			?><option><?=$refValue['value']?></option><?
		}

		?></select><?
	}
?>