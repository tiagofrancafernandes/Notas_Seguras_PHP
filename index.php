<?php
//phpinfo();exit;
/*
 * Global Variable
 * 
 */
define("BASE_PATH", "note/"); // Path (Relative to index.php) where notes will be saved
header('X-XSS-Protection:0');

function dump($log)
{
    return var_dump($log);
}

function dd($log)
{
    return die(dump($log));
}

function reload_this_page()
{
    $http_protocol = $https = false ? 'https' : 'http' ;//TODO to get via $_SERVER
    header('Location: '. $http_protocol .'://'.$_SERVER['HTTP_HOST'].$_SERVER['PATH_INFO']);
}

/*
 * Start Function
 */

/**
 *  Encrypts the given message
 * 
 *  @param string $msg The message which will be encrypted.
 * 
 */
function encrypt($msg) {
    $masala = ""; //Add your masala(Salt) to hash
    return hash("sha256", $masala . $msg);
}

/**
 * Makes the file downloadable
 * 
 * @param string $file File to be downloaded
 * 
 */
function download_file($file) {
    //Removes the "note." from filename
    $filename = str_replace("note.", "", $file);

    // Check if $filename==".txt" then make it note.txt else keep the filename
    $filename = strlen($filename) == 4 ? "note.txt" : $filename;

    //Get the absolute file path
    $_file = BASE_PATH . $file;

    //Check if file exists (for debug purpose)
    if (file_exists($_file))
    {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($_file));
        readfile($_file);
        exit();
    } else {
        //Display Debug Info
        //echo $_file."FILE NOT FOUND";
    }
}

/*
 * End Functions
 */

//Makes Base Path Folder if it does not exists
if (!file_exists(BASE_PATH)) {
    mkdir(BASE_PATH, 777);

    //If Base Path has nested directories eg. notes/public/, personnal/notes/, notes/default/
    //mkdir(BASE_PATH, 777, TRUE);
}


//Get Filepath name for request uri
$url = str_replace("/", "note.", "$_SERVER[REQUEST_URI]");

//Get Lock filename
$lock = $url . ".lock";

// Start Session to store and retrieve PWD for locked file
session_start();

//Check if File is authenticated
$authenticated = false;

//Check if file is password protected
$isPAsswordProtected = false;

//Global Message to be displayed for users action
$globalMessage = null;


//Set $isPAsswordProtected based for lock files existence
$isPAsswordProtected = file_exists(BASE_PATH . $lock);

//Check if user has submitted the form
if (isset($_POST['data'])) {
    $theData = $_POST['data'];
    $myFile = "$url.txt";
    if ($_POST["session_destroy"] == "true") {
        unset($_SESSION["PWD"]);
        $globalMessage = "Bloqueado";
    } else if ($_POST["download"] == "true") {
        $download_auth = false;
        $download_auth = file_exists(BASE_PATH . $myFile);
        if ($download_auth) {
            download_file($myFile);
        }
    } else if ($_POST["delete"] == "true") {
        $isPAsswordProtected = file_exists(BASE_PATH . $lock);
        if ($isPAsswordProtected) {
            $token = $_POST["token"];
            $md_token = encrypt($token);
            $fh = fopen(BASE_PATH . $lock, 'r');
            $theTokenData = fread($fh, filesize(BASE_PATH . $lock));
            if ($theTokenData == $md_token) {
                unlink(BASE_PATH . $myFile);
                unlink(BASE_PATH . $lock);
                session_destroy();
                $isPAsswordProtected = false;
                $theData = "";
                $globalMessage = "Note was removed";
            } else {
                $globalMessage = "Wrong Password";
            }
        } else {
            unlink(BASE_PATH . $myFile);
            unlink(BASE_PATH . $lock);
            session_destroy();
            $isPAsswordProtected = false;
            $theData = "";
            $globalMessage = "Note was removed";
            reload_this_page();
        }
    } else {

        if ($isPAsswordProtected) {
            $token = $_POST["token"];
            $md_token = encrypt($token);

            $fh = fopen(BASE_PATH . $lock, 'r');
            $theTokenData = fread($fh, filesize(BASE_PATH . $lock));
            if ($theTokenData == $md_token) {
                $authenticated = true;
                $_SESSION["PWD"] = $md_token;
            } else {
                if (isset($_SESSION["PWD"])) {
                    if ($_SESSION["PWD"] == $theTokenData) {
                        $authenticated = true;
                    }
                }
            }
            if (!$authenticated) {
                $globalMessage = "Wrong password";
            }

            $fh = fopen(BASE_PATH . $myFile, 'r');
            $theData = fread($fh, filesize(BASE_PATH . $myFile));
        } else if (isset($_POST["token"]) && !empty($_POST["token"])) {
            $token = $_POST["token"];
            $md5_token = encrypt($token);
            $_SESSION["PWD"] = $md5_token;
            $fh = fopen(BASE_PATH . $lock, 'w');
            fwrite($fh, $md5_token);
            $authenticated = true;
            $isPAsswordProtected = true;
            $globalMessage = "Password is set";
        } else {
            $authenticated = true;
        }

        if ($authenticated) {
            $theData = $_POST['data'];
            $fh = fopen(BASE_PATH . $myFile, 'w');
            fwrite($fh, $_POST['data']);
            $globalMessage = "Saved";
            reload_this_page();
        }
    }
} else {

    //Display files content as form was not submitted
    $myFile = "$url.txt";
    
    if(file_exists(BASE_PATH . $myFile))
    {
        $fh = fopen(BASE_PATH . $myFile, 'r');
        $theData = fread($fh, filesize(BASE_PATH . $myFile));
    }

    if ($isPAsswordProtected)
    {
        $fh = fopen(BASE_PATH . $lock, 'r');
        $theTokenData = fread($fh, filesize(BASE_PATH . $lock));
        if ($theTokenData == $_SESSION["PWD"]) {
            $authenticated = true;
        }
    }
}
// Close file handler to prevent memory leak
if(file_exists($myFile))
fclose($fh);
?>
<!DOCTYPE HTML>
<html lang="pt-BR">
    <meta charset="utf-8" />
    <head>
        <style>
            body,textarea,a{
                background-color: #111;
                color: #FFF;
            }/*Criado uma regra de condição de cor
            textarea{
                background-color: #CCC;
                color: #111;
            }*/
            .page-view{
                display: block;
                padding: 10px;
                font-size: 16px;
                width: 100%;
                text-align: center;
                box-sizing: border-box;
                opacity: 0;
            }
            .page-view:hover{
                opacity: 1;
            }
            .button{
                background-color: #FFF;
                border: 1px solid #F60;
                color: #111;
                padding: 5px 15px;
                font-size:20px;
                cursor: pointer;
            }
            .red{
                color: red;
            }
            .newfile{
                border: 0px;
                padding: 0px;
                outline: none;
            }
            .key-img{
                width: 25px;
                height: 25px;
                margin-left: 10px;
                cursor: pointer;
            }
            .globalMessage{
                display: none;
                font-size: 12px;
                color: red;
            }
            .cursor{
                cursor: pointer;
            }
        </style>
        <meta charset="utf-8" />
<?php
	$note = explode('/', $_SERVER['REQUEST_URI']);
	$note_name = $note[count($note)-1];
?>
        <title>Nota "<?=$note_name ?>" | <?php echo "#".$_SERVER['PATH_INFO']." #".$note_name; ?></title>
        <link rel="icon" type="image/x-icon" href="favicon.svg"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <script src="emmet/emmet.min.js"></script>
    </head>

    <body onload="bodyLoaded()">

        <center>
            <hr>
            <a href="<?php echo "$_SERVER[REQUEST_URI]"; ?>" onclick="window.location.reload(true);" style="text-decoration: none;color:red;font-size:20px;"><b>Reabrir</b></a>
            
            <font style="font-size:20px;"> 
                <span onclick="copyClipboard()" class="cursor">
                    <?php
                    $filename = str_replace("/", "", "$_SERVER[REQUEST_URI]");
                    echo "$filename" . ".txt";
                    ?>
                </span>
                (<span style="cursor: pointer;font-size: 14px;color: <?php echo ($isPAsswordProtected) ? "#F00" : "#0F0" ?>;" onclick="lockedClick()" id="Bloqueado">
                    <?php
                    echo $isPAsswordProtected ? "Bloqueado" . ($authenticated ? " - editável" : "" ) : "Desbloqueado";
                    ?>

                </span>)

                <span class="globalMessage" id="globalMessage">
                    <?php if (!empty($globalMessage)) { ?>
                        <?php echo $globalMessage; ?>
                    <?php } ?>
                </span>
            </font>
            <br/>
            <form name="noteform" method="post" action="">
                <?php
                    $bgBloqueado = "<style>textarea{background-color: #F50; color: #111; }</style>";
                    $bgDesBloqueado = "<style>textarea{background-color: #0F9; color: #111; }</style>";
                ?>
                <?php echo ($isPAsswordProtected) ? $bgBloqueado : $bgDesBloqueado; ?>
                <textarea name="data" spellcheck="false" id="textData" rows="20" style="width:100%;height:60vh;font-size:18px;box-sizing: border-box;"><?php echo $theData; ?></textarea>
                <div>
                    <div id="contenteditableId" contenteditable="true" style="background: #ca6262;text-align: left;width: 50%;margin: 1em auto 0 auto;border-radius: 5px;min-height: 3.3em;float: left;resize: both;overflow: auto;">#----------------------------------------------------<?="\n"?>#----------------------------------------------------</div>
                    <input type="button" id="inserir" value="Inserir isso" onclick="pasteHtmlAtCaret();" style="height: 1em;padding: 1em 2em 2em 2em;border-radius: 5px;cursor: pointer;float: left;font-size: 1em;margin: 1em auto 0 1em;">
                    <br clear="all">
                    



                    <script>window.jQuery || document.write('<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js">\x3C/script>')</script>
                    <script>
                        if(typeof jQuery=='undefined')
                        {
                            urljquery="./jquery_1.9.1.min.js";
                            var headTag = document.getElementsByTagName("head")[0];
                            var jqTag = document.createElement('script');
                            jqTag.type = 'text/javascript';
                            jqTag.src = urljquery;
                            headTag.appendChild(jqTag);
                        }
                        

                        jQuery( '#inserir' ).on('click', function(){
                            var cursorPos = jQuery('#textData').prop('selectionStart');
                            var v = jQuery('#textData').val();
                            var textBefore = v.substring(0,  cursorPos );
                            var textAfter  = v.substring( cursorPos, v.length );
                            // jQuery('#textData').val( textBefore+ jQuery('#codigomanual').val() +textAfter );
                            jQuery('#textData').val( textBefore+ jQuery('#contenteditableId').html() +textAfter );
                        });
                    </script>
                </div>
                <input type="hidden" name="token" id="token"/>             
                <input type="hidden" name="session_destroy" id="session_destroy" value="false"/>   

                <input type="hidden" name="delete" id="delete" value="false"/>     
                <input type="hidden" name="download" id="download" value="false"/> 

                <input type="hidden" name="pwdProtected" id="pwdProtected" value="<?php echo $isPAsswordProtected ? "true" : "false"; ?>"/> 

                <br/><br/>
                <div>
                (<span style="cursor: pointer;font-size: 14px;color: <?php echo ($isPAsswordProtected) ? "#F00" : "#0F0" ?>;" onclick="lockedClick()" id="Bloqueado">
                    <?php
                    echo $isPAsswordProtected ? "Bloqueado" . ($authenticated ? " - editável" : "" ) : "Desbloqueado";
                    ?>

                </span>)
                    <input type="submit" value="Salvar Alterações" class="button" onclick="formSave()" /> (CTRL + S)
                    <img align="right" onclick="showDeleteConfirm()" src="img/delete2.png" title="Excluir arquivo" class='key-img'/>                 
                    <img align="right" onclick="downloadClick()" src="img/download2.png" title="Baixar arquivo" class='key-img'/>
                    <img align="right" onclick="showPasswordPromt()"  src="img/lock2.png" title="Lock file" class='key-img'/>
                    <img align="right" onclick="copyClipboard()" src="img/copy2.png" title="Copiar para a área de transferência" class='key-img'/>

                </div>
                <div>
                    <span id="key-msg" style="float: right;">
                        <?php if (!empty($globalMessage)) { ?>
                            <?php echo $globalMessage; ?>
                        <?php } ?>
                    </span>
                </div>

                <br/>

            </form>
        </center>
        <center style="word-break: break-all;">
            <br/><br/>
            <b>Crie sua nota online: http://dev.tiagofranca.com/notas/<span class="red" id="newFileTxtPlaceholder" onclick="showCreateNewFile()">seu_link_unico</span>
                <input type="text" value="" class="newfile" onkeydown="createNewFile(event)" onblur="hideCreateNewFile()" style="display: none;" id="newFileTxt"/>
            </b>
            <br/>
        </center>
        <script>

            function bodyLoaded() {
                showKeyMsg();
            }



            function formSave() {
                setKeyMsg("Saved");
            }
            function setKeyMsg(msg) {
                var el = document.getElementById("key-msg");
                el.innerHTML = msg;
//                el.style.display = "inline";
//                setTimeout(hideKeyMsg, 1500);
                showKeyMsg();
            }
            function showKeyMsg() {
                var el = document.getElementById("key-msg");
                el.style.display = "inline";
                setTimeout(hideKeyMsg, 1500);
            }

            function hideKeyMsg() {
                var el = document.getElementById("key-msg");
                el.innerHTML = "";
                el.style.display = "none";
            }

            function showCreateNewFile() {
                document.getElementById("newFileTxtPlaceholder").style.display = "none";
                document.getElementById("newFileTxt").style.display = "inline";
                document.getElementById("newFileTxt").focus();
            }

            function hideCreateNewFile() {
                document.getElementById("newFileTxtPlaceholder").style.display = "inline";
                document.getElementById("newFileTxt").style.display = "none";

            }
            function createNewFile(e) {
                console.log(e);
                /*
                 * 
                 * 13 -> Enter Key
                 * 27 -> Esc Key
                 * 
                 */

                if (e.keyCode == 13) {
                    var str = document.getElementById("newFileTxt").value;
                    if (str.length > 0) {
                        window.location.href = "" + document.getElementById("newFileTxt").value;
                    } else {
                        hideCreateNewFile();
                    }
                } else if (e.keyCode == 27) {
                    hideCreateNewFile();
                }
            }

            function showPasswordPromt() {
                var pass = prompt("Informar a senha");
                document.getElementById("token").value = pass;
                if (pass && pass.length > 0) {
                    document.forms.noteform.submit();
                }
            }
            function showDeleteConfirm() {

                var pwdProtected = document.getElementById("pwdProtected").value;
                if (pwdProtected == "true") {
                    var pass = prompt("Tem certeza?(Password)");
                    if (pass && pass.length > 0) {
                        document.getElementById("token").value = pass;
                        document.getElementById("delete").value = "true";
                        document.forms.noteform.submit();
                    }
                } else {
                    if (confirm('Deseja mesmo deletar o arquivo links.html?')) {
                    document.getElementById("delete").value = "true";
                    document.forms.noteform.submit();
                        }
                }
            }
            function lockedClick() {
                var element = document.getElementById("Bloqueado");
                var str = element.innerHTML.trim();
                if (str == "Bloqueado" || str == "Desbloqueado") {
                    showPasswordPromt();
                } else if (str == "Bloqueado - editável") {
                    document.getElementById("session_destroy").value = "true";
                    document.forms.noteform.submit();
                }
            }

            /*
             * Keyboard Shortcut
             * 
             */
            function saveShortcut(zEvent) {

//                if (zEvent.ctrlKey && zEvent.shiftKey && zEvent.code === "KeyS") {
//                    document.forms.noteform.submit();
//                }
                if (zEvent.ctrlKey) {
                    if (zEvent.code === "KeyS") {
                        document.forms.noteform.submit();
                        setKeyMsg("Saved");
                        zEvent.preventDefault();
                        return false;
                    } else if (zEvent.code === "KeyD") {
                        downloadClick();

                        zEvent.preventDefault();
                        return false;
                    } else if (zEvent.code === "KeyL") {
                        lockedClick();
                        zEvent.preventDefault();
                        return false;
                    }
                } else if (zEvent.altKey) {
                    if (zEvent.code === "KeyN") {
                        showCreateNewFile();
                        zEvent.preventDefault();
                        return false;
                    } else if (zEvent.code === "KeyC") {
                        copyClipboard();

                        zEvent.preventDefault();
                        return false;
                    }
                }
            }

            function copyClipboard() {
                /* Get the text field */
                var copyText = document.getElementById("textData");

                /* Select the text field */
                copyText.select();

                /* Copy the text inside the text field */
                document.execCommand("Copy");

                setKeyMsg("Copiado");
                copyText.setSelectionRange(0, 0);
            }

            function downloadClick() {
                setKeyMsg("Baixando");
                document.getElementById("download").value = "true";
                document.forms.noteform.submit();

                //Because forms value aren't reset
                document.getElementById("download").value = "false";
            }
            document.addEventListener("keydown", saveShortcut);

        </script>
        <?php
        //logic for page counter

        $fp = fopen("counterlog.txt", "r");

        $count = fread($fp, 1024);

        fclose($fp);

        $count = ++$count;

        echo "<span class='page-view'>Counter : " . $count . "</span>";

        $fp = fopen("counterlog.txt", "w");

        fwrite($fp, $count);
        fclose($fp);
        ?>
        <script>
        emmet.require('textarea').setup({
            pretty_break: false, // enable formatted line breaks (when inserting 
                                // between opening and closing tag) 
            use_tab: false       // expand abbreviations by Tab key
        });
        </script>
        <br/>
        <a href="https://github.com/tiagofrancafernandes/Notas_Seguras_PHP">Código fonte (Notas_Seguras_PHP)</a>
    </body>

</html>
