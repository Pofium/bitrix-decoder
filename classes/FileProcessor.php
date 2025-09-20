<?php
/**
 * Класс FileProcessor - обработка файлов и управление загрузками
 * Отвечает за валидацию, сохранение и обработку загружаемых файлов
 */

class FileProcessor {
    private $uploadDir;
    private $allowedExtensions = ['php'];
    private $maxFileSize = 10485760; // 10MB
    
    /**
     * Конструктор класса
     * @param string $uploadDir Директория для загрузки файлов
     */
    public function __construct($uploadDir = null) {
        $this->uploadDir = $uploadDir ?: __DIR__ . '/../uploads/';
        $this->ensureUploadDirExists();
    }
    
    /**
     * Создание директории для загрузок, если она не существует
     */
    private function ensureUploadDirExists() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
            // Создание файла .htaccess для защиты директории
            file_put_contents($this->uploadDir . '.htaccess', 
                "Order deny,allow\nDeny from all\n");
        }
    }
    
    /**
     * Валидация загружаемого файла
     * @param array $file Массив файла из $_FILES
     * @return array Результат валидации
     */
    public function validateFile($file) {
        $errors = [];
        
        // Проверка ошибок загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadError($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Проверка размера файла
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = "Размер файла превышает допустимый лимит (" . 
                      $this->formatBytes($this->maxFileSize) . ")";
        }
        
        // Проверка расширения файла
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $errors[] = "Недопустимое расширение файла. Разрешены: " . 
                      implode(', ', $this->allowedExtensions);
        }
        
        // Проверка MIME-типа
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['text/x-php', 'application/x-php', 'text/php'];
        if (!in_array($mime, $allowedMimes) && !preg_match('/^text\//', $mime)) {
            $errors[] = "Недопустимый тип файла: $mime";
        }
        
        // Проверка содержимого файла (базовый PHP валидатор)
        $content = file_get_contents($file['tmp_name']);
        if (!$this->isValidPhpFile($content)) {
            $errors[] = "Файл не содержит валидный PHP код";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime' => $mime,
            'size' => $file['size']
        ];
    }
    
    /**
     * Сохранение загруженного файла
     * @param array $file Массив файла из $_FILES
     * @return array Результат сохранения
     */
    public function saveUploadedFile($file) {
        $validation = $this->validateFile($file);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Ошибка валидации файла',
                'errors' => $validation['errors']
            ];
        }
        
        // Генерация уникального имени файла
        $filename = $this->generateUniqueFilename($file['name']);
        $filepath = $this->uploadDir . $filename;
        
        // Сохранение файла
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Установка прав доступа
            chmod($filepath, 0644);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'original_name' => $file['name'],
                'size' => $file['size']
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Не удалось сохранить файл',
            'error' => error_get_last()
        ];
    }
    
    /**
     * Генерация уникального имени файла
     * @param string $originalName Оригинальное имя файла
     * @return string Уникальное имя файла
     */
    private function generateUniqueFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Очистка имени файла от небезопасных символов
        $safeBasename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        
        return uniqid() . '_' . $safeBasename . '.' . $extension;
    }
    
    /**
     * Проверка валидности PHP файла
     * @param string $content Содержимое файла
     * @return bool Результат проверки
     */
    private function isValidPhpFile($content) {
        // Базовая проверка на наличие PHP тегов
        if (strpos($content, '<?php') === false && 
            strpos($content, '<?=') === false && 
            strpos($content, '<?') === false) {
            return false;
        }
        
        // Дополнительные проверки могут быть добавлены здесь
        // Например, проверка синтаксиса с помощью token_get_all()
        
        return true;
    }
    
    /**
     * Получение файла по имени
     * @param string $filename Имя файла
     * @return array|false Информация о файле или false если не найден
     */
    public function getFile($filename) {
        $filepath = $this->uploadDir . $filename;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        // Проверка, что файл находится в разрешенной директории
        $realPath = realpath($filepath);
        $realUploadDir = realpath($this->uploadDir);
        
        if (strpos($realPath, $realUploadDir) !== 0) {
            return false;
        }
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'content' => file_get_contents($filepath),
            'size' => filesize($filepath),
            'modified' => filemtime($filepath)
        ];
    }
    
    /**
     * Удаление файла
     * @param string $filename Имя файла
     * @return bool Результат удаления
     */
    public function deleteFile($filename) {
        $filepath = $this->uploadDir . $filename;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
    
    /**
     * Очистка старых файлов
     * @param int $maxAge Максимальный возраст файлов в секундах (по умолчанию 1 час)
     * @return int Количество удаленных файлов
     */
    public function cleanupOldFiles($maxAge = 3600) {
        $files = glob($this->uploadDir . '*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && time() - filemtime($file) > $maxAge) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Получение описания ошибки загрузки
     * @param int $errorCode Код ошибки
     * @return string Описание ошибки
     */
    private function getUploadError($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает разрешенный лимит',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает лимит формы',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная директория',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Расширение PHP остановило загрузку файла'
        ];
        
        return $errors[$errorCode] ?? 'Неизвестная ошибка загрузки';
    }
    
    /**
     * Форматирование размера файла в читаемый вид
     * @param int $bytes Размер в байтах
     * @return string Отформатированный размер
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Получение статистики загрузок
     * @return array Статистика использования
     */
    public function getStorageStats() {
        $files = glob($this->uploadDir . '*');
        $totalSize = 0;
        $fileCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $fileCount++;
            }
        }
        
        return [
            'total_files' => $fileCount,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'max_size' => $this->maxFileSize,
            'max_size_formatted' => $this->formatBytes($this->maxFileSize)
        ];
    }
}
?>