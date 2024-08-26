<?php
if (isset($argv[1]) === true && $argv[1] === '-d') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL & ~E_DEPRECATED);
}
define('RUTA_HOST', __DIR__);
define('NOMBRE_SCRIPT_EXE', basename(__FILE__));
define('RUTA_EXE', $argv[0]);
define('FICHERO_LOG', "log_".date("yM").".log");
// Requires.
require_once "vendor/autoload.php";
require_once "functions/utiles.php";
require_once "functions/endpoints.php";

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

$dotenv = Dotenv\Dotenv::createMutable(__DIR__);
$dotenv->safeLoad();
// Bot de Telegram
try {
    // Check de que el servicio está activo.
    if (checkIsAlreadyRunning() === true) {
        throw new Exception("El servicio ya se encontraba activo. Se mantiene sesion.");
    } else {
        logServicio("Se inicia el servicio.");
    }
    // Definicion del Bot.
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
    });
    

    $bot->onCommand('stop', function (Nutgram $bot) {
        if (getIdUsuario($bot) === $_ENV['TELEBOT_ADMIN']) {
            logServicio("Accion de parada. Se solicita clave de validacion.");
            $GLOBALS['ACTION_REQUEST'] = time();
            $bot->sendMessage('Puedes llevar a cabo esta accion. Introduce la clave de validacion:');
        } else {
            logUsuario($bot, "WARNING - Accion de parada no permitida. Reportado");
            $bot->sendMessage('No puedes llevar a cabo esta accion.');
        }
    });


    $bot->onCommand('logs', function (Nutgram $bot) {
        if (validaAdmin($bot) === true) { 
            $bot->sendMessage(getLogs());
        } else {
            $bot->sendMessage('Acción no permitida.');
        }
        logUsuario($bot, "Solicitud de logs.");
    });
    

    $bot->onText('Clave Validacion {clave}', function(Nutgram $bot, string $clave) {
        if (isset($GLOBALS['ACTION_REQUEST']) === true && $GLOBALS['ACTION_REQUEST'] < time() + 3600) {
            if (validaAdmin($bot, $clave)) {
                logServicio("Parada validada. Se detiene la ejecucion");
                $bot->sendMessage('Servicio detenido.');
                exit;
            } else {
                logUsuario($bot, "WARNING - Intento de validacion de clave.");
                $bot->sendMessage('Acción no permitida.');
                sleep(10);
            }
        }
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
                logServicio("ERROR Obtencion fichero: ".$ex->getMessage());
                $bot->sendMessage("😵‍💫 Algo salió mal: " . $ex->getMessage() . ".\n 🌞 No te preocupes, voy a revisarlo.");
            }
        } else {
            $bot->sendMessage("❗No parece ser una URL válida: " . $texto);
            logUsuario($bot, "URL NO VALIDA: ".$texto);
        }
    });
    
    // Comienza el server.
    $bot->run();
} catch (Exception $ex) {
    logServicio($ex->getMessage());
}
