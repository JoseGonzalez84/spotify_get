<?php

use SergiX44\Nutgram\Nutgram;

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


function logUsuario(Nutgram $bot, string $mensaje): void
{
    // Definimos variables.
    $usuario = getNombreUsuario($bot);
    $userId = getIdUsuario($bot);
    $ruta = RUTA_HOST.'/logs/';
    $fichero = "log_".date("yM").".log";
    $texto = date("d.F H:i:s")." [$usuario:$userId] -> ".$mensaje."\n";
    if (file_exists($ruta)) {
        // si el fichero existe - borrarlo primero
        $ruta .= $fichero;
        if (file_exists($ruta)) {
            $fichero = fopen($ruta, 'a');
        } else {
            $fichero = fopen($ruta, 'w');
        }
        fwrite($fichero, $texto);
        fclose($fichero);
    }
}


function getNombreUsuario(Nutgram $bot): string
{
    $objetoUsuario = $bot->user();
    return $objetoUsuario->username;
}


function getIdUsuario(Nutgram $bot): string
{
    $objetoUsuario = $bot->user();
    return $objetoUsuario->id;
}