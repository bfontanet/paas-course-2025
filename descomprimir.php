<?php
require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$sourceContainer = "comprimidos";
$targetContainer = "descomprimidos";

$blobClient = BlobRestProxy::createBlobService($connectionString);

$mensajes = [];

function out($msg, $success = true) {
    global $mensajes;
    $mensajes[] = [
        'texto' => $msg,
        'tipo' => $success ? 'success' : 'error'
    ];
}

try {
    $options = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($sourceContainer, $options);
    $blobs = $blobList->getBlobs();
    $processed = false;

    foreach ($blobs as $blob) {
        $blobName = $blob->getName();
        if (strtolower(pathinfo($blobName, PATHINFO_EXTENSION)) !== "zip") continue;

        out("Procesando ZIP: <strong>$blobName</strong>");

        $zipStream = $blobClient->getBlob($sourceContainer, $blobName)->getContentStream();
        $tmpZipPath = tempnam(sys_get_temp_dir(), 'zip_');
        file_put_contents($tmpZipPath, stream_get_contents($zipStream));

        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath) !== TRUE) {
            out("❌ No se pudo abrir el archivo ZIP: $blobName", false);
            unlink($tmpZipPath);
            continue;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $entrySanitized = str_replace(["\\", ".."], ["/", ""], $entry);
            $content = $zip->getFromIndex($i);

            if ($content !== false) {
                $uploadOptions = new CreateBlockBlobOptions();
                $blobClient->createBlockBlob($targetContainer, $entrySanitized, $content, $uploadOptions);
                out("Extraído y subido: <em>$entrySanitized</em>");
            } else {
                out("⚠️ No se pudo leer el contenido de: $entry", false);
            }
        }

        $zip->close();
        unlink($tmpZipPath);
        $processed = true;
        break;
    }

    if (!$processed) {
        out("📭 No se encontraron archivos ZIP en el contenedor '$sourceContainer'.", false);
    }

} catch (ServiceException $e) {
    out("☁️ Error de Azure: " . $e->getMessage(), false);
} catch (Exception $e) {
    out("💥 Error general: " . $e->getMessage(), false);
}

// Si estamos en CLI, mostramos los mensajes y salimos
if (php_sapi_name() === 'cli') {
    foreach ($mensajes as $m) {
        echo ($m['tipo'] === 'success' ? "[✔] " : "[✖] ") . strip_tags($m['texto']) . PHP_EOL;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Descompresión de ZIP en Azure</title>
    <style>
        body {
            background: #f3f7f5;
            font-family: "Segoe UI", sans-serif;
            padding: 2em;
            color: #333;
        }
        .container {
            background: #fff;
            max-width: 700px;
            margin: auto;
            padding: 2em;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        h1 {
            text-align: center;
            color: #2e7d32;
        }
        .message {
            border-left: 5px solid;
            padding: 1em;
            margin: 1em 0;
            border-radius: 6px;
        }
        .success {
            border-color: #4caf50;
            background: #e8f5e9;
            color: #2e7d32;
        }
        .error {
            border-color: #f44336;
            background: #ffebee;
            color: #c62828;
        }
        em {
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧘 Archivos descomprimidos en Azure</h1>
        <?php foreach ($mensajes as $m): ?>
            <div class="message <?= $m['tipo'] ?>">
                <?= $m['texto'] ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
            
