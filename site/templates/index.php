<?
GLOBAL $devUser, $user, $lang;

$cacheNumber = DEV ? rand(1000, 1000000) : 2;
$anti_cache = '?_='.$cacheNumber;

$options = ['user_id' => $user['id'], 'state'=>['receive', 'active']];
html::AddJsData("'".$this->createRequestId(get_class($this))."'", 'ajaxRequestId')

?>
<!DOCTYPE html>
<html lang="<?=$user['language_code']?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$this->title?></title>
    <link rel="stylesheet" type="text/css" href="<?=BASEURL?>site/css/styles.css<?=$anti_cache?>">
    <link rel="stylesheet" href="https://telegram.org/css/tg-web-app.css">

    <script src="<?=DEV ? SCRIPTURL : 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1'?>/jquery.min.js"></script> 
    <script src="<?=SCRIPTURL.'main.js?'.$cacheNumber?>"></script>
    <?=html::RenderJSFiles();?>
    <?=html::RenderStyleFiles();?>

    <script src="https://telegram.org/js/telegram-web-app.js?<?=$cacheNumber?>"></script>
    <script type="text/javascript">

        <?=html::RenderJSData()?>
        <?=html::RenderJSCode()?>

        const tg = window.Telegram.WebApp;

        window.addEventListener("error", (e) => {

            //console.error(e);
            Ajax({
                action: 'catchError',
                data: {message: e.message, stack: e.error.stack}
            });
        });

        var DEV = <?=DEV ? 'true' : 'false'?>;
        var BASEURL = '<?=BASEURL?>';
        var lang = <?=preg_replace("/[\r\n]+/", '', json_encode($lang, JSON_PRETTY_PRINT))?>;
        
    <?if ($user) {?>
        var user = <?=json_encode($user)?>;
    <?} else if (DEV) {?>
        var user = <?=$devUser?>;

    <?}?>
    
        $(window).ready(()=>{

            document.body.classList.add('theme-' + tg.colorScheme);
            //$('body').append('<div>' + JSON.stringify(tg.themeParams) + '</div>');

            if (tg.initData) {
                let data = uriStringToObject(tg.initData);

                if (data.user) {

                    data.user = JSON.vparse(data.user);

                    <?if ($user) {?>
                        if (data.user.id != '<?=$user['id']?>') {
                            Ajax({
                                action: 'initData',
                                data: data
                            }).then((v)=>{
                                if (v === true) window.location.reload();
                            });
                        }
                    <?}?>
                }
            }
        });
    </script>
</head>
<body>
    <div class="wrapper">
        <div id="modal-windows"></div>
        <div id="back-content">
            <div id="windows"></div>
            <?=$content?>
        </div>
    </div>

    <?=html::RenderTemplates()?>

    <?
    GLOBAL $isDefServer;
    if (!$isDefServer) {?>
    <!-- Eruda is console for mobile browsers-->
    <script src="https://cdn.jsdelivr.net/npm/eruda"></script>
    <script>eruda.init();</script>
    <?}?> 
</body>
</html>