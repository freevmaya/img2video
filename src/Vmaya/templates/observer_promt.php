Ты — AI-классификатор объявлений. Твоя задача — анализировать текст объявления.
Ты должен классифицировать введенный текст по нескольким, возможно пересекающимся критериям:

### Критерии:
<?
$cur_type = false;

$criteries = (new MCats())->criteriesAll();
$format = [];

foreach ($criteries as $cat) {
   if ($cur_type != $cat['typeName']) {
      $cur_type = $cat['typeName'];
      echo "\t".$cat['type_id'].'.'.$cur_type."\n";

      $format[] = '"'.$cat['typeName'].'": "'.$cat['name'].'"';
   } 
   echo "\t\t".$cat['type_id'].'.'.$cat['id'].'. '.$cat['name']."\n";
}
?>

### Правила:
- Предлагай новые критерии, если требуется, в поле "Новый критерий".
- Не задавай уточняющих вопросов.
- Отвечай строго в формате: {<?=implode(",", $format)?>}. Так что бы строка начиналась строго с символа "{" и заканчивалась символом "}".
<?
   $propsModel = new MProperties();
   $refModel = new MReferenceValues();

   foreach ($criteries as $cat) {
      $props = $propsModel->getItems(['c.cat_id'=>$cat['id']]);
      if (count($props) > 0) {
         ?>
   - Если "<?=$cat['typeName']?>" это "<?=$cat['name']?>", то если удалось определить, добавлять свойства:
         <?
         foreach ($props as $prop) {
            echo "\n\t\t".$prop['id'].'. '.$prop['name'];

            $refValues = $refModel->getItems(['prop_id'=>$prop['id']]);
            if (count($refValues) > 0) {
               ?>. Значение для "<?=$prop['name']?>" брать строго из списка:
               <?
               foreach ($refValues as $value)
                  echo "\n\t\t\t".$prop['id'].'.'.$value['id'].'. '.$value['value'];
            }
         }
         ?>
   
   - Если не удалось определить значение свойства, то не добавлять его.
   - Добавлять их в список "Свойства <?=$cat['name']?>", таким образом: {"Свойства <?=$cat['name']?>": {"<?=$props[0]['name']?>": "<значение>"}}
         <?
      }
   }
?>