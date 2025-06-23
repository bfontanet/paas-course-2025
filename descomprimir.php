<?php
require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

// ⚙️ Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$sourceContainer = "comprimidos";
$targetContainer = "descomprimidos";

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Función para mostrar mensajes en CLI o navegador
function out($msg, $success = true) {
    if (php_sapi_name() === 'cli') {
        echo ($success ? "[✔] " : "[✖] ") . $msg . PHP_EOL;
    } else {
        $color = $success ? "green" : "red";
        echo "<p style='color:{$color}'>" . htmlspecialchars($msg) . "</p>";
    }
}

try {
    $options = new ListBlobsOptions();
    $options->setPrefix("");  // listar todos

    $blobList = $blobClient->listBlobs($sourceContainer, $options);
    $blobs = $blobList->getBlobs();

    $processed = false;

    foreach ($blobs as $blob) {
        $blobName = $blob->getName();
        $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

        if ($extension !== "zip") continue;

        out("Procesando ZIP: $blobName");

        // Descargar el contenido ZIP
        $zipStream = $blobClient->getBlob($sourceContainer, $blobName)->getContentStream();
        $tmpZipPath = tempnam(sys_get_temp_dir(), 'zip_');
        file_put_contents($tmpZipPath, stream_get_contents($zipStream));

        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath) !== TRUE) {
            out("❌ No se pudo abrir el ZIP: $blobName", false);
            unlink($tmpZipPath);
            continue;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $entrySanitized = str_replace(["\\", ".."], ["/", ""], $entry); // prevención básica
            $content = $zip->getFromIndex($i);

            if ($content !== false) {
                $uploadOptions = new CreateBlockBlobOptions();
                $blobClient->createBlockBlob($targetContainer, $entrySanitized, $content, $uploadOptions);
                out("Extraído y subido: $entrySanitized");
            } else {
                out("No se pudo leer el contenido de: $entry", false);
            }
        }

        $zip->close();
        unlink($tmpZipPath);

        $processed = true;
        break; // Solo el primero
    }

    if (!$processed) {
        out("No se encontraron archivos ZIP en el contenedor '$sourceContainer'.", false);
    }

} catch (ServiceException $e) {
    out("Error de servicio Azure: " . $e->getMessage(), false);
} catch (Exception $e) {
    out("Error general: " . $e->getMessage(), false);
}
