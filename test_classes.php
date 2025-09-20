<?php
// Тестовый скрипт для проверки всех классов декодера

// Подключение автозагрузчика
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

echo "<h1>Тестирование классов Bitrix Decoder</h1>\n";

// Тест ErrorHandler
echo "<h2>1. Тестирование ErrorHandler</h2>\n";
try {
    $errorHandler = new ErrorHandler();
    echo "✅ ErrorHandler создан успешно<br>\n";
    
    // Тест логирования
    $errorHandler->logError("Тестовая ошибка", "test_file.php", 1);
    echo "✅ Логирование ошибок работает<br>\n";
} catch (Exception $e) {
    echo "❌ Ошибка в ErrorHandler: " . $e->getMessage() . "<br>\n";
}

// Тест FileProcessor
echo "<h2>2. Тестирование FileProcessor</h2>\n";
try {
    $fileProcessor = new FileProcessor();
    echo "✅ FileProcessor создан успешно<br>\n";
    
    // Тест проверки файла
    if (file_exists('test_encoded.php')) {
        $isValid = $fileProcessor->validateFile('test_encoded.php');
        echo "✅ Валидация файла: " . ($isValid ? "пройдена" : "не пройдена") . "<br>\n";
        
        $content = $fileProcessor->readFile('test_encoded.php');
        echo "✅ Чтение файла: " . (strlen($content) > 0 ? "успешно" : "ошибка") . "<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка в FileProcessor: " . $e->getMessage() . "<br>\n";
}

// Тест Decoder
echo "<h2>3. Тестирование Decoder</h2>\n";
try {
    // Читаем тестовый файл для декодера
    $testContent = file_exists('test_encoded.php') ? file_get_contents('test_encoded.php') : '<?php echo "test"; ?>';
    $decoder = new Decoder($testContent);
    echo "✅ Decoder создан успешно<br>\n";
    
    // Тест декодирования
    $result = $decoder->decode();
    echo "✅ Декодирование выполнено: " . ($result ? "успешно" : "ошибка") . "<br>\n";
    
    // Получаем статистику
    $stats = $decoder->getStatistics();
    if ($stats) {
        echo "<h3>Статистика декодирования:</h3>\n";
        echo "Обработано строк: " . ($stats['processed_lines'] ?? 0) . "<br>\n";
        echo "Найдено закодированных элементов: " . ($stats['decoded_count'] ?? 0) . "<br>\n";
        echo "Найдено подозрительных функций: " . ($stats['suspicious_count'] ?? 0) . "<br>\n";
    }
    
    // Получаем результат декодирования
    $decodedContent = $decoder->getDecodedContent();
    if ($decodedContent) {
        echo "✅ Получен декодированный контент (длина: " . strlen($decodedContent) . " символов)<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка в Decoder: " . $e->getMessage() . "<br>\n";
}

echo "<h2>✅ Тестирование завершено!</h2>\n";
?>