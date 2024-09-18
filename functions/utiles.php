<?php

use SergiX44\Nutgram\Nutgram;

define('OUTPUT_FORMATS', [
    'mp3' => '-x --audio-format mp3',
    'webm' => '-f webm',
    'mp4' => '-f mp4',
]);


function noAccessChain(): bool
{
    return (isset($_SESSION['access_chain']) === false || empty($_SESSION['access_chain']) === true || $_SESSION['access_expiration'] < time());
}


function retryRequest(string $function, mixed $data = null): mixed
{
    getToken();
    sleep(1);
    if ($data === null) {
        return $function();
    } else {
        return $function($data);
    }
}


function authorizationChain(): array
{
    return ['Authorization' => $_SESSION['access_chain']];
}


function requestConstructor(array $data): Httpful\Request
{
    // Extraccion de todos los datos.
    extract($data);
    // Definicion del método por defecto.
    if (isset($method) === false || empty($method) === true) {
        $method = 'GET';
    }
    // Creacion del objeto.
    $request = \Httpful\Request::init()
            ->method($method)
            ->uri($url)
            ->followRedirects(true)
            ->withoutStrictSSL();
    // Headers.
    $request->addHeader('Accept', '*/*');
    // Comprobación de que haya mas headers.
    if (isset($headers) === true) {
        foreach($headers as $keyHeader => $valueHeader) {
            $request->addHeader($keyHeader, $valueHeader);
        }
    }   
    // Body.
    if (isset($body) === true) {
        if (is_array($body) === true && empty($body) === false) {
            $bodyToAttach = '?';
            foreach ($body as $keyBody => $valueBody) {
                $bodyToAttach .= $keyBody . "=" . $valueBody . "&";
            }

            $body = $bodyToAttach;
        }

        $request->body($body);
    }

    return $request;
}


function escribirLog(string $texto): void
{
    $ruta = RUTA_HOST.'/logs/';
    $fichero = FICHERO_LOG;
    if (file_exists($ruta)) {
        // si el fichero existe - borrarlo primero
        $ruta .= $fichero;
        if (file_exists($ruta)) {
            $fichero = fopen($ruta, 'a');
        } else {
            $fichero = fopen($ruta, 'w');
        }
        fwrite($fichero, "[".time()."] > ".$texto);
        fclose($fichero);
    }
}


function logUsuario(Nutgram $bot, string $mensaje): void
{
    // Definimos variables.
    $usuario = getNombreUsuario($bot);
    $userId = getIdUsuario($bot);
    $texto = "[USR][$usuario:$userId] > ".$mensaje."\n";
    escribirLog($texto);
}


function logServicio(string $mensaje): void
{
    // Definimos variables.
    escribirLog("[SYS] > $mensaje\n");
}


function getNombreUsuario(Nutgram $bot): string
{
    $nombre = '';
    $objetoUsuario = $bot->user();
    if (isset($objetoUsuario->username) === true) {
        $nombre = $objetoUsuario->username;
    }
    return $nombre;
}


function getIdUsuario(Nutgram $bot): string
{
    $id = '';
    $objetoUsuario = $bot->user();
    if (isset($objetoUsuario->username) === true) {
        $id = $objetoUsuario->id;
    }

    return $id;
}


function buildYtDlpQuery(array $parametros): string
{
    extract($parametros);

    $startStop = '';
    $outputFormat = '';

    if (isset($format) === true || in_array($format, OUTPUT_FORMATS) === true) {
        $outputFormat = OUTPUT_FORMATS[$format];
    }

    if (isset($startPosition) === true) {
        $startStop .= '--start '.$startPosition;
    }

    if (isset($endPosition) === true) {
        $startStop  .= ' --end '.$endPosition;
    }

    return sprintf(
        "yt-dlp -o /tmp/'%(title)s'.mp3 %s %s %s",
        $outputFormat,
        $startStop,
        $url
    );
}


function checkIsAlreadyRunning(): bool
{
    exec('ps -ax | grep -i '.NOMBRE_SCRIPT_EXE.' | grep -v grep', $salida);
    return count($salida) > 2;
}


function checkExecDependency(string $executable): bool
{
    exec('which '.$executable, $salida);
    return count($salida) >= 1;
}


function getLogs(): string
{
    $salida = '';
    try {
        exec('tail -15 '.RUTA_HOST.'/logs/'.FICHERO_LOG, $salida);
        $salida = implode("\n", $salida);
    } catch (Exception $ex) {
        logServicio("No se pueden extraer logs: ".$ex->getMessage());
        $salida = "No se pudieron traer logs.";
    }
    
    return $salida;
}


function validaAdmin(Nutgram $bot, string $clave = '') {
    $validar = $_ENV['TELEBOT_ADMIN'] === getIdUsuario($bot);
    if ($validar === true && empty($clave) === false) {
        $validar = $_ENV['TELEBOT_ADMIN_KEY'] === $clave;
    }

    return $validar;
}