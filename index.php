<?php
if (isset($argv[1]) === true && $argv[1] === '-d') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL & ~E_DEPRECATED);
}
define('RUTA_HOST', __DIR__);
// Requires.
require_once "./vendor/autoload.php";
require_once "./functions/utiles.php";
require_once "./functions/endpoints.php";

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

$dotenv = Dotenv\Dotenv::createMutable(__DIR__);
$dotenv->safeLoad();
// Bot de Telegram
$bot = new Nutgram($_ENV['TELEBOT_TOK']);

$bot->onCommand('start', function (Nutgram $bot) {
    $usuario = getNombreUsuario($bot);
    $saludo = <<<SALUDO
    Hola $usuario!

    Bienvenido a gaDEV Music. Con este bot puedes recuperar el audio de tus videos de origenes como YouTube.

    Tan solo tendrás que enviar al bot la URL válida de YouTube (puedes utilizar `Compartir`) y el bot te devolverá el audio en formato MP3.

    Hecho por @jsx_freakk en 2024.
    SALUDO;
    $bot->sendMessage($saludo);
    logUsuario($bot, "Comienza sesion.");
});


$bot->onCommand('playlists', function (Nutgram $bot) {
    logUsuario($bot, "Ha invocado la accion PLAYLISTS");
    $salida = getPlaylists(true);
    $bot->sendMessage($salida);
});


$bot->onCommand('test', function (Nutgram $bot) {
    logUsuario($bot, "Ha invocado la accion TEST");
    $bot->asResponse()->sendMessage('hello'); // This will reply directly and give the method as JSON payload in the reply

    $bot->sendMessage('Chat ID: ' . $bot->chatId()); // This will reply sending a request to the Telegram API
    $bot->sendMessage('USER ID: ' . $bot->userId()); // This will reply sending a request to the Telegram API
    //var_dump($bot->user()); // This will reply sending a request to the Telegram API
});



$bot->onText('https://{texto}', function (Nutgram $bot, string $texto) {
    if (filter_var('https://' . $texto, FILTER_VALIDATE_URL) !== false) {
        $usuario = getNombreUsuario($bot);
        logUsuario($bot, "Solicita una descarga. URL: https://$texto");
        $bot->sendMessage("Bien! 😃 Vamos allá ");
        try {
            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w")   // stderr
            );

            $nombreFichero = "'%(title)s'.mp3";
            $rutaDescarga = "-o /tmp/$nombreFichero";
            $process = proc_open('yt-dlp -x ' . $rutaDescarga . ' --audio-format mp3 ' . $texto, $descriptorspec, $pipes);

            if (is_resource($process)) {
                $lineaCompletaFichero = [];
                while ($line = fgets($pipes[1])) {
                    if (str_contains($line, '[ExtractAudio]') === true) {
                        $bot->sendMessage("🎶 Convirtiendo en audio");
                        $lineaCompletaFichero = explode('[ExtractAudio] Destination: ', $line);
                    } elseif (str_contains($line, '[download] Destination') === true) {
                        $bot->sendMessage("📥 Descargando los datos");
                    }
                    flush();
                }

                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);

                $return_value = proc_close($process);
                if ((int) $return_value === 0) {
                    // Ya debería existir el fichero.
                    $ficheroRutaMP3 = trim($lineaCompletaFichero[1]);
                    if (file_exists($ficheroRutaMP3) === true) {
                        $ficheroMP3 = fopen($ficheroRutaMP3, 'r+');
                        $bot->sendMessage("💿 Enviando el fichero");
                        $bot->sendAudio(audio: InputFile::make($ficheroMP3), caption: "🎉 ¡Que lo disfrutes!");
                        logUsuario($bot, "Recibe el fichero: $ficheroRutaMP3.");
                        unlink($ficheroRutaMP3);
                    } else {
                        throw new Exception("💩 Algo pasó con el fichero");
                    }
                }
            }
        } catch (Exception $ex) {
            logUsuario($bot, "ERROR: ".$ex->getMessage());
            $bot->sendMessage("😵‍💫 Algo salió mal: " . $ex->getMessage() . ".\n 🌞 No te preocupes, voy a revisarlo.");
        }
    } else {
        $bot->sendMessage("❗No parece ser una URL válida: " . $texto);
    }
});

// Comienza el server.
$bot->run();
