<?php

/*
 TODOs:
    - Limite de peticiones
    - Control de acceso
    - Mejor validación de los datos
    - Base de Datos en lugar de logs
    - Obtener informacion de cantidad de datos procesados (metricas usuarios)
    - Descarga de videos a peticion
    - Aviso a user admin para cuando haya arranque u otros avisos importantes
 */
if (isset($argv[1]) === true && $argv[1] === '-d') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL & ~E_DEPRECATED);
}
define('RUTA_HOST', __DIR__);
define('NOMBRE_SCRIPT_EXE', basename(__FILE__));
define('RUTA_EXE', $argv[0]);
define('FICHERO_LOG', "log_" . date("yM") . ".log");
// Requires.
require_once "vendor/autoload.php";
require_once "functions/utiles.php";
require_once "functions/endpoints.php";

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;

$dotenv = Dotenv\Dotenv::createMutable(__DIR__);
$dotenv->safeLoad();
// Bot de Telegram
try {
    // Check de dependencias.
    if (checkExecDependency('yt-dlp') === false) {
        throw new Exception("No se ha encontrado el ejecutable yt-dlp.");
    }
    // Check de que el servicio está activo.
    if (checkIsAlreadyRunning() === true) {
        exit;
        //throw new Exception("El servicio ya se encontraba activo. Se mantiene sesion.");
    } else {
        logServicio("Se inicia el servicio.");
    }
    // Definicion del Bot.
    $bot = new Nutgram($_ENV['TELEBOT_TOK']);

    $bot->onCommand('start', function (Nutgram $bot) {
        $usuario = getNombreUsuario($bot);
        $saludo = <<<SALUDO
        Hola $usuario!
    
        Bienvenido a gaDEV Music. Con este bot puedes recuperar el audio de tus videos online.
    
        Tan solo tendrás que enviar al bot la URL válida del video en cuestion, utilizando por ejemplo la opción "Compartir".
        
        Si quieres mas información o instrucciones, utiliza el comando /info.
        
        Hecho por 😺 @jsx_freakk en 2024.
        SALUDO;
        $bot->sendMessage($saludo);
        logUsuario($bot, "Comienza sesion.");
    });


    $bot->onCommand('info', function (Nutgram $bot) {
        logUsuario($bot, "Ha invocado la accion INFO.");
        $textoDescargo = <<<DESCARGO
        \# Información de gaDEVMusic

        \#\# Descargo de responsabilidad

        Este bot se trata de un experimento educativo. Forma parte de un proyecto personal, sin animo de lucro.
        El software se entrega tal cual y sin ninguna garantía. No se garantiza su correcto funcionamiento ni se responde de los daños que pueda ocasionar.
        El bot puede estar sujeto a cambios constantes y funcionalidades que hoy esten y mañana puede que no.
        El contenido descargable debe ser de dominio público, de libre acceso y respetando las leyes de copyright pertinentes.
        Solo se registra el id de usuario, el nombre y el link que se adjunta. Esta inforación no se comparte con nadie y se almacena por motivos de seguridad.
        DESCARGO;

        $textInstrucciones = <<<INSTRUCCIONES
        \#\# Instrucciones

        - Obten la ruta para compartir el video (o el enlace al propio video) de tu servicio de video online. Los siguientes está comprobado que funcionan:

            - _YouTube_
            - _DailyMotion_
            - _TikTok_
            - _X_

        - El sistema reconocerá el video, lo procesará y devolverá el audio extraido al usuario.
        INSTRUCCIONES;

        $textoSoftware = <<<SOFTWARE
        \#\# Software utilizado

        - **gaDEVMusic**, version 0.4.240904 (https://github.com/JoseGonzalez84/gaDEVMusic)
        - **PHP** 8.2 (https://www.php.net/)
        - **Nutgram** 4.25 (https://github.com/nutgram/nutgram)
        - **phpdotenv** 5.6 (https://github.com/vlucas/phpdotenv)
        - **yt-dlp** 2024.08.06 (https://github.com/yt-dlp/yt-dlp)

        Parte de la documentacion se ha obtenido de https://atareao.es/ 

        Que lo disfrutes!
        SOFTWARE;

        $textoUltimosCambios = <<<CAMBIOS
        \#\# Versionado
        - 0.4.240918: Agregados metadatos. Se pueden descargar playlists de YouTube aunque dan algunos fallos a veces.

        CAMBIOS;

        try {
            $bot->sendMessage(text: $textoDescargo);
            $bot->sendMessage(text: $textInstrucciones);
            $bot->sendMessage(text: $textoSoftware);
        } catch (Exception $ex) {
            if (getIdUsuario($bot) === $_ENV['TELEBOT_ADMIN']) {
                $bot->sendMessage("Error en la solicitud info: " . $ex->getMessage());
            } else {
                $bot->sendMessage("Error. Se ha reportado la incidencia.");
            }
        }
    });


    $bot->onCommand('playlists', function (Nutgram $bot) {
        logUsuario($bot, "Ha invocado la accion PLAYLISTS");
        $salida = getPlaylists(true);
        $bot->sendMessage($salida);
    });


    $bot->onCommand('test', function (Nutgram $bot) {
        logUsuario($bot, "Ha invocado la accion TEST");
        $bot->asResponse()->sendMessage('hello'); // This will reply directly and give the method as JSON payload in the reply
    });


    $bot->onText('download {url}', function (Nutgram $bot, string $url) {
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
        }
    });

    $bot->onText('testing2', function (Nutgram $bot, string $url) {
        $bot->sendMessage(
            text: 'Selecciona que quieres obtener:',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('Audio', callback_data: 'format:audio'),
                    InlineKeyboardButton::make('Video', callback_data: 'format:video')
                )
        );
    });

    $bot->onCallbackQueryData('format:audio', function (Nutgram $bot) {
        $videoQuality = '-S "height:480" -f "bv*"';
        $bot->answerCallbackQuery(
            text: 'You selected A'
        );
    });

    $bot->onCallbackQueryData('format:video', function (Nutgram $bot) {
        $bot->answerCallbackQuery(
            text: 'You selected B'
        );
    });


    $bot->onCommand('stop', function (Nutgram $bot) {
        if (getIdUsuario($bot) === $_ENV['TELEBOT_ADMIN']) {
            logServicio("Accion de parada. Se solicita clave de validacion.");
            $bot->setUserData('action_request_stop', time(), getIdUsuario($bot));
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


    $bot->onText($_ENV['MSG_VALID_STOP'] . ' {clave}', function (Nutgram $bot, string $clave) {
        $requestStop = $bot->getUserData('action_request_stop', getIdUsuario($bot));
        if (empty($requestStop) === false && $requestStop < (time() + 3600) && empty($clave) === false) {
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
            logUsuario($bot, "Solicita una descarga. URL: https://$texto");
            $bot->sendMessage("🛫 Vamos allá! ");
            try {
                $descriptorspec = array(
                    0 => array("pipe", "r"),  // stdin
                    1 => array("pipe", "w"),  // stdout
                    2 => array("pipe", "w")   // stderr
                );

                $nombreFichero = "'%(title)s'.mp3";
                $rutaDescarga = "-o /tmp/$nombreFichero";
                $process = proc_open('yt-dlp -x ' . $rutaDescarga . ' --restrict-filenames --embed-thumbnail --embed-metadata --audio-format mp3 ' . $texto, $descriptorspec, $pipes);

                if (is_resource($process)) {
                    $ficheros = [];
                    $informadoPlaylist = false;
                    while ($line = fgets($pipes[1])) {
                        logUsuario($bot, "YT-DLP -> $line");
                        if (str_contains($line, '[ExtractAudio]') === true) {
                            $bot->sendMessage("🎶 Convirtiendo en audio");
                            $ficheros[] = explode('[ExtractAudio] Destination: ', $line);
                        } elseif (str_contains($line, 'ERROR:') === true) {
                            $bot->sendMessage("Hay algun error con un elemento.");
                        }
                        if (str_contains($texto, 'playlist') === true) {
                            if ($informadoPlaylist === false) {
                                $informadoPlaylist = true;
                                $bot->sendMessage("🚀 Procesando la playlist.");
                                sleep(3);
                                $bot->sendMessage("⏱️ Esto puede tardar un rato... Si hubiera algún problema, el sistema te avisará.");
                            }
                        } else {
                            if (str_contains($line, 'ERROR: [DRM]') === true) {
                                $bot->sendMessage("🚫 Enlace con protección DRM. No permitido.");
                            }
                        }
                        flush();
                    }

                    fclose($pipes[0]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);

                    $return_value = proc_close($process);
                    $errores = [];
                    if ((int) $return_value === 0) {
                        foreach ($ficheros as $fichero) {
                            try {
                                $ficheroRutaMP3 = trim($fichero[1]);
                                if (file_exists($ficheroRutaMP3) === true) {
                                    $ficheroMP3 = fopen($ficheroRutaMP3, 'r+');
                                    $bot->sendMessage("🚚 Enviando el fichero");
                                    $bot->sendAudio(audio: InputFile::make($ficheroMP3), caption: "🎉 ¡Que lo disfrutes!");
                                    logUsuario($bot, "Recibe el fichero: $ficheroRutaMP3.");
                                    unlink($ficheroRutaMP3);
                                } else {
                                    throw new Exception("Error con el fichero: $ficheroRutaMP3");
                                }
                            } catch (\Exception $ex) {
                                $errores[] = $ex->getMessage();
                            }
                        }
                        // Si hubo errores, notificarlo.
                        if (empty($errores) === false) {
                            throw new Exception('💩 Hubo errores en el proceso');
                        }
                    }
                }
            } catch (Exception $ex) {
                logUsuario($bot, "ERROR: " . $ex->getMessage());
                logServicio("ERROR Obtencion fichero: " . $ex->getMessage());
                $bot->sendMessage("😵‍💫 Algo salió mal: " . $ex->getMessage() . ".");
            }
        } else {
            $bot->sendMessage("❗No parece ser una URL válida: " . $texto);
            logUsuario($bot, "URL NO VALIDA: " . $texto);
        }
    });

    // Comienza el server.
    $bot->run();
} catch (Exception $ex) {
    logServicio($ex->getMessage());
}
