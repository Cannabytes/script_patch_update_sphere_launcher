<?php

ini_set('upload_max_filesize', '1000M');
ini_set('post_max_size', '1200M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '1500M');

$csvFile = 'client.csv';
if ( ! file_exists($csvFile)) {
    echo "CSV-файл не существует. Разместите файл в корневую папку с патчем.";
    exit;
}

//Проверка если файл называется index.php то выдаем ошибку
if (basename($_SERVER['SCRIPT_NAME']) == 'index.php') {
    echo "Переименуйте файл index.php в любое другое название и обновите страницу.<br>";

    // Функция генерации случайной строки
    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    // Генерация случайного имени файла
    $randomFileName = generateRandomString(mt_rand(10, 35)) . '.php';

    echo "Предлагаю назвать файл: <strong>" . htmlspecialchars($randomFileName) . "</strong>";
    exit;
}


class Index
{

    public function __construct()
    {
        $this->upload();
    }

    public function upload()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_FILES['files']) && isset($_POST['directory'])) {
                $files     = $_FILES['files'];
                $uploadDir = $_POST['directory'];

                if ($uploadDir != '') {
                    if ( ! is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                }

                $responses = [];

                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] == 0) {
                        $filename   = basename($files['name'][$i]);
                        $uploadFile = $uploadDir . DIRECTORY_SEPARATOR . $filename;

                        if (move_uploaded_file($files['tmp_name'][$i], $uploadFile)) {
                            $this->processFile($uploadFile);
                            $responses[] = "Файл успешно загружен: $filename.";
                        } else {
                            $responses[] = "Ошибка при загрузке файла: $filename.";
                        }
                    } else {
                        $responses[] = "Ошибка: " . $files['error'][$i] . " для файла: " . $files['name'][$i];
                    }
                }

                echo implode("<br>", $responses);
            } else {
                echo "Нет файлов для загрузки или не выбрана директория.";
            }
        } else {
            echo "Неправильный метод запроса.";
        }
    }

    private function processFile($filePath)
    {
        $csvFile = 'client.csv';
        $hash    = $this->getXXHash($filePath);

        // Читаем текущие данные из CSV
        $rows       = readCSV($csvFile);
        $newRows    = [];
        $fileExists = false;

        $fileInfo        = pathinfo($filePath);
        $fileNameWithExt = $fileInfo['filename'] . '.' . $fileInfo['extension'];

        // Проверяем наличие файла в CSV и удаляем старую запись
        foreach ($rows as $row) {
            $csvFileInfo        = pathinfo($row[0]);
            $csvFileNameWithExt = $csvFileInfo['filename'];
            if ($csvFileNameWithExt === $fileNameWithExt) {
                $fileExists = true;
                // Удаляем старый zip-файл, если он существует
                if (file_exists($row[0])) {
                    unlink($row[0]);
                }
            } else {
                $newRows[] = $row;
            }
        }

        // Архивируем и добавляем новый файл
        $archiveDir = $_POST['directory'];
        if ( ! is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $zip         = new ZipArchive();
        $zipFilename = $archiveDir . DIRECTORY_SEPARATOR . $fileNameWithExt . '.zip';

        if ($zip->open($zipFilename, ZipArchive::CREATE) === true) {
            $zip->addFile($filePath, basename($filePath));
            $zip->close();

            // Добавляем запись в CSV
            $relativePath = str_replace('\\', '/', $archiveDir . DIRECTORY_SEPARATOR . basename($zipFilename));
            $newRows[]    = [$relativePath, filesize($filePath), $hash];

            // Записываем обновленные данные в CSV
            writeCSV($csvFile, $newRows);

            // Удаляем загруженный файл
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            echo "Файл успешно обработан и добавлен в архив: $zipFilename";
        } else {
            echo "Не удалось создать архив: $zipFilename";
        }
    }

    private function getXXHash($filename)
    {
        if ( ! file_exists($filename)) {
            return "none";
        }

        $file = fopen($filename, 'rb');
        if ($file === false) {
            return "none";
        }

        $data = fread($file, filesize($filename));
        fclose($file);

        if ($data === false) {
            return "none";
        }

        // Используем встроенную функцию hash() с алгоритмом xxh64
        $hash = hash('xxh64', $data, true); // true для получения бинарного вывода

        return bin2hex($hash);
    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['files'])) {
    $index = new Index();
}

function readCSV($file)
{
    $rows = [];
    if (($handle = fopen($file, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    }

    return $rows;
}

// Функция для записи данных в CSV
function writeCSV($file, $data)
{
    if (($handle = fopen($file, 'w')) !== false) {
        foreach ($data as $row) {
            if (fputcsv($handle, $row) === false) {
                fclose($handle);

                return false;
            }
        }
        fclose($handle);

        return true;
    }

    return false;
}

// Обработка удаления файлов
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $toDelete = $_POST['delete'];
    $rows     = readCSV($csvFile);
    $newRows  = [];

    foreach ($rows as $row) {
        if (in_array($row[0], $toDelete)) {
            // Удаляем файл с диска
            $filePath = $row[0];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } else {
            $newRows[] = $row;
        }
    }

    writeCSV($csvFile, $newRows);
    header('Refresh: 0');
    exit;
}

$rows = readCSV($csvFile);

function getDirectories($baseDir)
{
    $directories = [];
    $baseDir     = realpath($baseDir);
    if (is_dir($baseDir)) {
        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
          RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                $relativePath  = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $fileInfo->getRealPath());
                $directories[] = $relativePath;
            }
        }
    }

    return $directories;
}

$directories = getDirectories('.');

?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Загрузка файлов</title>
</head>
<body>
<h2>Загрузка файлов</h2>
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data">
  <label for="files">Выберите файлы для загрузки:</label>
  <input type="file" name="files[]" id="files" multiple>

  <label for="directory">Выберите директорию:</label>
  <select name="directory" id="directory">
      <?php
      foreach ($directories as $directory): ?>
        <option value="<?php
        echo htmlspecialchars($directory, ENT_QUOTES, 'UTF-8'); ?>"><?php
            echo htmlspecialchars($directory, ENT_QUOTES, 'UTF-8'); ?></option>
      <?php
      endforeach; ?>
  </select>

  <input type="submit" value="Загрузить файлы">
</form>

<h2>Управление файлами (<?= count($rows) ?>)</h2>
<form action="index.php" method="post">
  <table>
    <thead>
    <tr>
      <th>Выбор</th>
      <th>Название файла</th>
      <th>Размер (байт)</th>
      <th>Хэш</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach ($rows as $row): ?>
      <tr>
        <td><input type="checkbox" name="delete[]" value="<?php
            echo htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8'); ?>"></td>
        <td><?php
            echo htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php
            echo htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php
            echo htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    <?php
    endforeach; ?>
    </tbody>
  </table>
  <input type="submit" value="Удалить выбранные файлы">
</form>

</body>
</html>
