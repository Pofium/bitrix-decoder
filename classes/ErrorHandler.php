<?php
/**
 * Класс ErrorHandler - обработка ошибок и исключений
 * Централизованная система обработки ошибок для веб-интерфейса
 */

class ErrorHandler {
    private static $instance = null;
    private $debugMode = false;
    private $logFile;
    
    /**
     * Конструктор класса (приватный для singleton)
     */
    private function __construct() {
        $this->logFile = __DIR__ . '/../logs/errors.log';
        $this->ensureLogDirExists();
        $this->initializeErrorHandling();
    }
    
    /**
     * Получение экземпляра singleton
     * @return ErrorHandler Экземпляр класса
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Создание директории для логов, если она не существует
     */
    private function ensureLogDirExists() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Инициализация обработки ошибок
     */
    private function initializeErrorHandling() {
        // Установка обработчиков ошибок и исключений
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        
        // Настройка отображения ошибок в зависимости от режима
        if ($this->debugMode) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        }
    }
    
    /**
     * Установка режима отладки
     * @param bool $debugMode Режим отладки
     */
    public function setDebugMode($debugMode) {
        $this->debugMode = (bool)$debugMode;
        $this->initializeErrorHandling();
    }
    
    /**
     * Обработчик ошибок PHP
     * @param int $errno Уровень ошибки
     * @param string $errstr Сообщение об ошибке
     * @param string $errfile Файл с ошибкой
     * @param int $errline Строка с ошибкой
     * @return bool Остановить выполнение скрипта
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        // Игнорирование ошибок, которые не должны прерывать выполнение
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $errorType = $this->getErrorType($errno);
        $message = "[$errorType] $errstr in $errfile on line $errline";
        
        // Логирование ошибки
        $this->logError($message, $errno, $errfile, $errline);
        
        // В режиме отладки показываем подробную информацию
        if ($this->debugMode) {
            $this->displayError($message, $errno, $errfile, $errline);
        }
        
        // Для фатальных ошибок прерываем выполнение
        if ($this->isFatalError($errno)) {
            $this->handleFatalError($message);
        }
        
        return true;
    }
    
    /**
     * Обработчик исключений
     * @param Throwable $exception Исключение
     */
    public function handleException($exception) {
        $message = "[Exception] " . $exception->getMessage() . 
                  " in " . $exception->getFile() . 
                  " on line " . $exception->getLine();
        
        $this->logError($message, E_ERROR, $exception->getFile(), $exception->getLine());
        
        if ($this->debugMode) {
            $this->displayException($exception);
        } else {
            $this->sendJsonError('Внутренняя ошибка сервера');
        }
    }
    
    /**
     * Обработка фатальных ошибок
     * @param string $message Сообщение об ошибке
     */
    private function handleFatalError($message) {
        $this->logError($message, E_ERROR, '', 0);
        
        if ($this->debugMode) {
            $this->displayError($message, E_ERROR, '', 0);
        } else {
            $this->sendJsonError('Критическая ошибка системы');
        }
        
        exit(1);
    }
    
    /**
     * Логирование ошибки
     * @param string $message Сообщение об ошибке
     * @param int $errno Уровень ошибки
     * @param string $file Файл с ошибкой
     * @param int $line Строка с ошибкой
     */
    private function logError($message, $errno, $file, $line) {
        $timestamp = date('Y-m-d H:i:s');
        $errorType = $this->getErrorType($errno);
        
        $logMessage = "[$timestamp] [$errorType] $message\n";
        
        // Добавление трассировки стека для серьезных ошибок
        if ($this->isSeriousError($errno)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $logMessage .= "Backtrace:\n";
            foreach ($backtrace as $index => $trace) {
                $logMessage .= sprintf("#%d %s(%d): %s%s%s\n",
                    $index,
                    $trace['file'] ?? 'unknown',
                    $trace['line'] ?? 0,
                    $trace['class'] ?? '',
                    $trace['type'] ?? '',
                    $trace['function'] ?? ''
                );
            }
            $logMessage .= "\n";
        }
        
        // Запись в файл лога
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Отображение ошибки в режиме отладки
     * @param string $message Сообщение об ошибке
     * @param int $errno Уровень ошибки
     * @param string $file Файл с ошибкой
     * @param int $line Строка с ошибкой
     */
    private function displayError($message, $errno, $file, $line) {
        if (php_sapi_name() === 'cli') {
            echo "Ошибка: $message\n";
            return;
        }
        
        http_response_code(500);
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Ошибка - Bitrix Decoder</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 50px; }
                .error-container { border: 2px solid #e74c3c; padding: 20px; border-radius: 5px; }
                .error-title { color: #e74c3c; font-size: 24px; margin-bottom: 10px; }
                .error-message { background: #fdf2f2; padding: 15px; border-radius: 3px; }
                .error-details { margin-top: 15px; font-size: 14px; color: #666; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-title">Произошла ошибка</div>
                <div class="error-message">' . htmlspecialchars($message) . '</div>
                <div class="error-details">
                    Файл: ' . htmlspecialchars($file) . '<br>
                    Строка: ' . $line . '<br>
                    Тип ошибки: ' . $this->getErrorType($errno) . '
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Отображение исключения в режиме отладки
     * @param Throwable $exception Исключение
     */
    private function displayException($exception) {
        if (php_sapi_name() === 'cli') {
            echo "Исключение: " . $exception->getMessage() . "\n";
            echo $exception->getTraceAsString() . "\n";
            return;
        }
        
        http_response_code(500);
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Исключение - Bitrix Decoder</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 50px; }
                .exception-container { border: 2px solid #e67e22; padding: 20px; border-radius: 5px; }
                .exception-title { color: #e67e22; font-size: 24px; margin-bottom: 10px; }
                .exception-message { background: #fef9e7; padding: 15px; border-radius: 3px; }
                .exception-trace { margin-top: 15px; font-family: monospace; font-size: 12px; }
                .trace-item { margin-bottom: 5px; }
            </style>
        </head>
        <body>
            <div class="exception-container">
                <div class="exception-title">Произошло исключение</div>
                <div class="exception-message">' . 
                htmlspecialchars($exception->getMessage()) . '</div>
                <div class="exception-trace">
                    <strong>Трассировка стека:</strong><br>
                    ' . nl2br(htmlspecialchars($exception->getTraceAsString())) . '
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Отправка JSON ошибки для AJAX запросов
     * @param string $message Сообщение об ошибке
     * @param int $statusCode HTTP статус код
     */
    public function sendJsonError($message, $statusCode = 500) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ]);
        
        exit;
    }
    
    /**
     * Проверка, является ли ошибка фатальной
     * @param int $errno Уровень ошибки
     * @return bool Результат проверки
     */
    private function isFatalError($errno) {
        $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, 
                       E_COMPILE_ERROR, E_COMPILE_WARNING];
        return in_array($errno, $fatalErrors);
    }
    
    /**
     * Проверка, является ли ошибка серьезной
     * @param int $errno Уровень ошибки
     * @return bool Результат проверки
     */
    private function isSeriousError($errno) {
        $seriousErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        return in_array($errno, $seriousErrors);
    }
    
    /**
     * Получение текстового описания типа ошибки
     * @param int $errno Уровень ошибки
     * @return string Текстовое описание
     */
    private function getErrorType($errno) {
        $errorTypes = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            // E_STRICT => 'E_STRICT', // Deprecated in PHP 8.4
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $errorTypes[$errno] ?? 'E_UNKNOWN';
    }
    
    /**
     * Регистрация shutdown функции для обработки фатальных ошибок
     */
    public function registerShutdownFunction() {
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && $this->isFatalError($error['type'])) {
                $this->handleFatalError(
                    "[$error[type]] $error[message] in $error[file] on line $error[line]"
                );
            }
        });
    }
    
    /**
     * Получение содержимого лог файла
     * @param int $lines Количество последних строк
     * @return array Содержимое лога
     */
    public function getLogContents($lines = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($content, -$lines);
    }
    
    /**
     * Очистка лог файла
     * @return bool Результат очистки
     */
    public function clearLog() {
        if (file_exists($this->logFile)) {
            return file_put_contents($this->logFile, '') !== false;
        }
        return true;
    }
}

// Автоматическая инициализация обработчика ошибок
ErrorHandler::getInstance()->registerShutdownFunction();
?>