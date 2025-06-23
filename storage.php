<?php
require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";  // Cambia esto por el nombre de tu contenedor

$blobClient = BlobRestProxy::createBlobService($connectionString);

$message = "";
$messageClass = "";

if (isset($_GET['delete'])) {
    $blobToDelete = $_GET['delete'];
    try {
        $blobClient->deleteBlob($containerName, $blobToDelete);
        $message = "Archivo <strong>$blobToDelete</strong> eliminado correctamente.";
        $messageClass = "success";
    } catch (ServiceException $e) {
        $message = "Error al eliminar: " . $e->getMessage();
        $messageClass = "error";
    }
}

if (!empty($_FILES["zipfile"]["tmp_name"])) {
    $uploadedFile = $_FILES["zipfile"];
    $blobName = basename($uploadedFile["name"]);
    $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

    if ($extension !== "zip") {
        $message = "Solo se permiten archivos ZIP.";
        $messageClass = "error";
    } else {
        $content = fopen($uploadedFile["tmp_name"], "r");
        try {
            $blobClient->createBlockBlob($containerName, $blobName, $content);
            $message = "Archivo <strong>$blobName</strong> subido correctamente.";
            $messageClass = "success";
        } catch (ServiceException $e) {
            $message = "Error al subir: " . $e->getMessage();
            $messageClass = "error";
        }
    }
}

// Listar archivos
try {
    $listOptions = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("Error al listar archivos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor Zen de archivos ZIP</title>
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background: #f6f8f9;
            color: #333;
            margin: 0;
            padding: 2rem;
        }
        h1, h2 {
            color: #2f5d62;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
        }
        .success {
            background: #daf5d7;
            color: #256029;
        }
        .error {
            background: #ffe2e2;
            color: #990000;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            padding: 0.6rem 0;
            border-bottom: 1px solid #ddd;
        }
        a {
            text-decoration: none;
            color: #0077aa;
        }
        a:hover {
            text-decoration: underline;
        }
        form {
            margin-top: 2rem;
        }
        input[type="file"] {
            padding: 0.5rem;
        }
        button {
            margin-top: 1rem;
            padding: 0.6rem 1.2rem;
            background-color: #2f5d62;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background-color: #224b4f;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Gestor de archivos ZIP en Azure Blob</h1>
    <?php if ($message): ?>
        <div class="message <?= htmlspecialchars($messageClass) ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <h2>Contenedor: <em><?= htmlspecialchars($containerName) ?></em></h2>
    <ul>
        <?php foreach ($blobs as $blob): ?>
            <li>
                <a href="<?= htmlspecialchars($blob->getUrl()) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                [<a href="?delete=<?= urlencode($blob->getName()) ?>" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>]
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Subir nuevo archivo ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <br>
        <button type="submit">Subir</button>
    </form>
</div>
</body>
</html>
