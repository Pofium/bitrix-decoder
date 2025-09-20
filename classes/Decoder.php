<?php
/**
 * Класс Decoder - основной класс для декодирования обфусцированных Bitrix файлов
 * Автоматически определяет массивы и функции, выполняет декодирование
 */

class Decoder {
    private $fileContent;
    private $originalContent;
    private $detectedArrays = [];
    private $detectedFunctions = [];
    private $detectedVariables = [];
    private $statistics = [];
    public $consoleOutput = '';
    
    // Паттерны для обнаружения различных типов обфускации
    private $patterns = [
        'base64' => '/base64_decode\s*\(\s*[\'"]([A-Za-z0-9+\/=]+)[\'"]\s*\)/',
        'hex' => '/\\\\x([0-9a-fA-F]{2})/',
        'chr' => '/chr\s*\(\s*(\d+)\s*\)/',
        'eval' => '/eval\s*\(\s*(.+?)\s*\)/',
        'gzinflate' => '/gzinflate\s*\(\s*(.+?)\s*\)/',
        'str_rot13' => '/str_rot13\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/'
    ];
    
    /**
     * Конструктор класса
     * @param string $content Содержимое файла для декодирования
     */
    public function __construct($content) {
        $this->fileContent = $content;
        $this->originalContent = $content;
        $this->initializeStatistics();
        $this->console_log('Инициализация оптимизированного декодера...');
    }
    
    /**
     * Инициализация статистики
     */
    private function initializeStatistics() {
        $this->statistics = [
            'original_size' => strlen($this->fileContent),
            'arrays_found' => 0,
            'functions_found' => 0,
            'variables_found' => 0,
            'base64_decoded' => 0,
            'hex_decoded' => 0,
            'chr_decoded' => 0,
            'math_expressions' => 0,
            'processing_time' => 0
        ];
    }
    
    /**
     * Логирование в консоль
     * @param string $str Сообщение для логирования
     */
    private function console_log($str) {
        $this->consoleOutput .= $str . "\n";
    }
    
    /**
     * Основной метод декодирования
     * @return string Декодированное содержимое файла
     */
    public function decode() {
        $startTime = microtime(true);
        $this->console_log('Начало оптимизированного декодирования...');
        
        // Многопроходное декодирование для лучших результатов
        $maxPasses = 5;
        $previousContent = '';
        
        for ($pass = 1; $pass <= $maxPasses; $pass++) {
            $this->console_log("Проход #$pass...");
            
            // Если содержимое не изменилось, прекращаем
            if ($this->fileContent === $previousContent) {
                $this->console_log("Содержимое стабилизировалось на проходе #$pass");
                break;
            }
            
            $previousContent = $this->fileContent;
            
            // Автоматическое определение паттернов
            $this->detectAllPatterns();
            
            // Декодирование в порядке приоритета
            $this->decodeBase64Strings();
            $this->decodeHexStrings();
            $this->decodeChrFunctions();
            $this->decodeStrRot13();
            
            // Обработка массивов и функций
            $this->processArrays();
            $this->processFunctions();
            $this->processVariables();
            
            // Математические вычисления
            $this->processMathematicalExpressions();
            
            // Очистка и форматирование
            $this->cleanupCode();
        }
        
        $this->statistics['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
        $this->statistics['final_size'] = strlen($this->fileContent);
        $this->statistics['compression_ratio'] = round((1 - $this->statistics['final_size'] / $this->statistics['original_size']) * 100, 2);
        
        $this->console_log('Декодирование завершено за ' . $this->statistics['processing_time'] . ' мс!');
        
        return $this->fileContent;
    }
    
    /**
     * Обнаружение всех паттернов обфускации
     */
    private function detectAllPatterns() {
        $this->detectGlobalArrays();
        $this->detectGlobalFunctions();
        $this->detectVariables();
        $this->detectEncodedStrings();
    }
    
    /**
     * Обнаружение закодированных строк
     */
    private function detectEncodedStrings() {
        // Подсчет различных типов кодирования
        foreach ($this->patterns as $type => $pattern) {
            $count = preg_match_all($pattern, $this->fileContent);
            if ($count > 0) {
                $this->console_log("Найдено $type строк: $count");
            }
        }
    }
    
    /**
     * Обнаружение переменных
     */
    private function detectVariables() {
        $pattern = '/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*[\'"]([^\'"]+)[\'"]/';
        preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $varName = $match[1];
            $value = $match[2];
            $this->detectedVariables[$varName] = $value;
        }
        
        $this->statistics['variables_found'] = count($this->detectedVariables);
        $this->console_log('Обнаружено переменных: ' . count($this->detectedVariables));
    }
    
    /**
     * Обнаружение глобальных массивов
     */
    private function detectGlobalArrays() {
        $patterns = [
            '/\$GLOBALS\[\'([^\']+)\'\]\[(\d+)\]/',
            '/\$([a-zA-Z_][a-zA-Z0-9_]*)\[(\d+)\]/',
            '/\$\{[\'"]([^\'"]+)[\'"]\}\[(\d+)\]/'
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $arrayName = $match[1];
                $index = (int)$match[2];
                
                if (!isset($this->detectedArrays[$arrayName])) {
                    $this->detectedArrays[$arrayName] = [];
                }
                
                if (!in_array($index, $this->detectedArrays[$arrayName])) {
                    $this->detectedArrays[$arrayName][] = $index;
                }
            }
        }
        
        $this->statistics['arrays_found'] = count($this->detectedArrays);
        $this->console_log('Обнаружено массивов: ' . count($this->detectedArrays));
    }
    
    /**
     * Автоматическое определение глобальных функций
     */
    private function detectGlobalFunctions() {
        $this->console_log('Поиск глобальных функций...');
        
        // Поиск паттернов типа Функция(123)
        preg_match_all('/([A-Za-z_\x7f-\xff][A-Za-z0-9_\x7f-\xff]*)\(\d+\)/', $this->fileContent, $matches);
        
        if (!empty($matches[1])) {
            $this->detectedFunctions = array_unique($matches[1]);
            $this->console_log('Найдены функции: ' . implode(', ', $this->detectedFunctions));
        }
    }
    
    /**
     * Обработка обнаруженных массивов
     */
    private function processArrays() {
        foreach ($this->detectedArrays as $arrayName) {
            $this->console_log('Обработка массива: ' . $arrayName);
            
            $this->fileContent = preg_replace_callback(
                '/\\\$GLOBALS\\[\\\'' . preg_quote($arrayName, '/') . '\\\'\\]\\[(\\d+)\\]/', 
                function($matches) use ($arrayName) {
                    if (isset($GLOBALS[$arrayName]) && isset($GLOBALS[$arrayName][$matches[1]])) {
                        $value = $GLOBALS[$arrayName][$matches[1]];
                        return is_string($value) ? "'" . addslashes($value) . "'" : $value;
                    }
                    return $matches[0];
                }, 
                $this->fileContent
            );
        }
    }
    
    /**
     * Обработка обнаруженных функций
     */
    private function processFunctions() {
        foreach ($this->detectedFunctions as $functionName) {
            $this->console_log('Обработка функции: ' . $functionName);
            
            if (function_exists($functionName)) {
                $this->fileContent = preg_replace_callback(
                    '/' . preg_quote($functionName, '/') . '\\((\\d+)\\)/', 
                    function($matches) use ($functionName) {
                        $result = call_user_func($functionName, $matches[1]);
                        return is_string($result) ? "'" . addslashes($result) . "'" : $result;
                    }, 
                    $this->fileContent
                );
            }
        }
    }
    
    /**
     * Обработка математических выражений
     */
    private function processMathematicalExpressions() {
        $this->console_log('Обработка математических выражений...');
        
        // Обработка встроенных функций (min, round, strtoupper, strrev)
        $this->fileContent = preg_replace_callback(
            '/(min|round|strtoupper|strrev)\\([^\\(\\)\\$]+\\)/', 
            function($matches) {
                try {
                    $result = eval("return $matches[0];");
                    switch (gettype($result)) {
                        case 'string':
                            return "'" . addslashes($result) . "'";
                        case 'double':
                        case 'integer':
                            return $result;
                        default:
                            return $matches[0];
                    }
                } catch (Exception $e) {
                    return $matches[0];
                }
            }, 
            $this->fileContent
        );
        
        // Обработка простых математических выражений
        $this->fileContent = preg_replace_callback(
            '/\\(([0-9-+*\\/\\s]{2,}?)\\)/', 
            function($matches) {
                try {
                    return eval("return $matches[1];");
                } catch (Exception $e) {
                    return $matches[0];
                }
            }, 
            $this->fileContent
        );
    }
    
    /**
     * Получение лога выполнения
     * @return string Лог выполнения операций
     */
    public function getConsoleOutput() {
        return $this->consoleOutput;
    }
    
    /**
     * Получение обнаруженных массивов
     * @return array Массив обнаруженных имен массивов
     */
    public function getDetectedArrays() {
        return $this->detectedArrays;
    }
    
    /**
     * Получение обнаруженных функций
     * @return array Массив обнаруженных имен функций
     */
    public function getDetectedFunctions() {
        return $this->detectedFunctions;
    }
    
    /**
     * Декодирование Base64 строк
     */
    private function decodeBase64Strings() {
        $pattern = $this->patterns['base64'];
        $this->fileContent = preg_replace_callback($pattern, function($matches) {
            $decoded = base64_decode($matches[1]);
            if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                $this->statistics['base64_decoded']++;
                return "'" . addslashes($decoded) . "'";
            }
            return $matches[0];
        }, $this->fileContent);
    }
    
    /**
     * Декодирование HEX строк
     */
    private function decodeHexStrings() {
        $pattern = $this->patterns['hex'];
        $this->fileContent = preg_replace_callback($pattern, function($matches) {
            $char = chr(hexdec($matches[1]));
            $this->statistics['hex_decoded']++;
            return $char;
        }, $this->fileContent);
    }
    
    /**
     * Декодирование CHR функций
     */
    private function decodeChrFunctions() {
        $pattern = $this->patterns['chr'];
        $this->fileContent = preg_replace_callback($pattern, function($matches) {
            $char = chr((int)$matches[1]);
            $this->statistics['chr_decoded']++;
            return "'" . addslashes($char) . "'";
        }, $this->fileContent);
    }
    
    /**
     * Декодирование str_rot13
     */
    private function decodeStrRot13() {
        $pattern = $this->patterns['str_rot13'];
        $this->fileContent = preg_replace_callback($pattern, function($matches) {
            $decoded = str_rot13($matches[1]);
            return "'" . addslashes($decoded) . "'";
        }, $this->fileContent);
    }
    
    /**
     * Обработка переменных
     */
    private function processVariables() {
        foreach ($this->detectedVariables as $varName => $value) {
            $pattern = '/\$' . preg_quote($varName, '/') . '\b/';
            $this->fileContent = preg_replace($pattern, "'" . addslashes($value) . "'", $this->fileContent);
        }
    }
    
    /**
     * Очистка и форматирование кода
     */
    private function cleanupCode() {
        // Удаление лишних пробелов и переносов строк
        $this->fileContent = preg_replace('/\s+/', ' ', $this->fileContent);
        $this->fileContent = preg_replace('/;\s*;+/', ';', $this->fileContent);
        
        // Удаление пустых строк
        $this->fileContent = preg_replace('/^\s*$/m', '', $this->fileContent);
        
        // Форматирование PHP тегов
        $this->fileContent = preg_replace('/<\?php\s+/', "<?php\n", $this->fileContent);
    }
    
    /**
     * Получение статистики декодирования
     * @return array Статистика процесса декодирования
     */
    public function getStatistics() {
        return $this->statistics;
    }
    
    /**
     * Получение обнаруженных переменных
     * @return array Массив обнаруженных переменных
     */
    public function getDetectedVariables() {
        return $this->detectedVariables;
    }
}
?>