<?php
/**
 * Bitrix Decoder - Веб-интерфейс для декодирования обфусцированных Bitrix файлов
 * Автоматическое определение массивов и функций, современный UI
 */

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Инициализация обработчика ошибок
$errorHandler = ErrorHandler::getInstance();
$errorHandler->setDebugMode(true); // В режиме разработки

// Инициализация обработчика файлов
$fileProcessor = new FileProcessor();

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (isset($_FILES['file'])) {
            handleFileUpload($_FILES['file'], $fileProcessor);
        } elseif (isset($_POST['action']) && $_POST['action'] === 'decode') {
            processDecoding($_POST['content']);
        } elseif (isset($_POST['action']) && $_POST['action'] === 'decode_file') {
            processDecodingFromFile();
        } elseif (isset($_POST['action']) && $_POST['action'] === 'download') {
            handleDownload($_POST['filename'], $_POST['content']);
        } else {
            throw new Exception('Неизвестное действие');
        }
    } catch (Exception $e) {
        $errorHandler->sendJsonError($e->getMessage());
    }
    exit;
}

// Главная страница с интерфейсом
function renderInterface() {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bitrix Decoder - Декодер обфусцированных файлов</title>
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
        <!-- Highlight.js для подсветки синтаксиса -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/styles/github.min.css" rel="stylesheet">
        
        <style>
            .hero-section {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 3rem 0;
            }
            
            .file-drop-zone {
                border: 2px dashed #dee2e6;
                border-radius: 10px;
                padding: 2rem;
                text-align: center;
                transition: all 0.3s ease;
                background: #f8f9fa;
            }
            
            .file-drop-zone:hover {
                border-color: #667eea;
                background: #e9ecef;
            }
            
            .file-drop-zone.dragover {
                border-color: #28a745;
                background: #d4edda;
            }
            
            .code-preview {
                max-height: 400px;
                overflow-y: auto;
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                font-size: 12px;
            }
            
            .progress-container {
                height: 4px;
                background: #e9ecef;
                border-radius: 2px;
                overflow: hidden;
            }
            
            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #667eea, #764ba2);
                transition: width 0.3s ease;
            }
            
            .feature-icon {
                font-size: 2rem;
                color: #667eea;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h1 class="display-4 fw-bold">
                            <i class="bi bi-file-code"></i> Bitrix Decoder
                        </h1>
                        <p class="lead">Автоматический декодер обфусцированных PHP файлов Bitrix</p>
                        <p class="mb-0">Просто загрузите файл - система сделает всё остальное!</p>
                    </div>
                    <div class="col-lg-4 text-center">
                        <i class="bi bi-unlock" style="font-size: 4rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container py-5">
            <!-- Features -->
            <div class="row mb-5">
                <div class="col-md-4 text-center">
                    <div class="feature-icon">
                        <i class="bi bi-cloud-upload"></i>
                    </div>
                    <h5>Простая загрузка</h5>
                    <p>Перетащите файл или выберите его в проводнике</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon">
                        <i class="bi bi-gear"></i>
                    </div>
                    <h5>Автоматическая обработка</h5>
                    <p>Система сама определит массивы и функции</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon">
                        <i class="bi bi-download"></i>
                    </div>
                    <h5>Мгновенное скачивание</h5>
                    <p>Получите читаемый код в один клик</p>
                </div>
            </div>

            <!-- File Upload Section -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card shadow">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-upload"></i> Загрузка файла
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="file-drop-zone" id="fileDropZone">
                                <i class="bi bi-file-earmark-code" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">Перетащите PHP файл сюда</h5>
                                <p class="text-muted">или</p>
                                <label for="fileInput" class="btn btn-primary">
                                    <i class="bi bi-folder2-open"></i> Выбрать файл
                                </label>
                                <input type="file" id="fileInput" class="d-none" accept=".php">
                                <p class="small text-muted mt-2">Поддерживаются только PHP файлы</p>
                            </div>

                            <div class="progress-container mt-3 d-none" id="progressContainer">
                                <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                            </div>

                            <div class="alert alert-info mt-3 d-none" id="infoAlert">
                                <i class="bi bi-info-circle"></i>
                                <span id="infoMessage"></span>
                            </div>

                            <div class="alert alert-danger mt-3 d-none" id="errorAlert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <span id="errorMessage"></span>
                            </div>

                            <div class="alert alert-success mt-3 d-none" id="successAlert">
                                <i class="bi bi-check-circle"></i>
                                <span id="successMessage"></span>
                            </div>

                            <!-- Results Section -->
                            <div class="mt-4 d-none" id="resultsSection">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6>Результат декодирования:</h6>
                                    <button class="btn btn-sm btn-outline-success" id="downloadBtn">
                                        <i class="bi bi-download"></i> Скачать
                                    </button>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <small>Исходный код</small>
                                            </div>
                                            <div class="card-body p-0">
                                                <pre class="code-preview m-0 p-3" id="originalCode"></pre>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <small>Декодированный код</small>
                                            </div>
                                            <div class="card-body p-0">
                                                <pre class="code-preview m-0 p-3" id="decodedCode"></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How it works -->
            <div class="row mt-5">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-question-circle"></i> Как это работает?
                            </h5>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Загрузите обфусцированный PHP файл из Bitrix</li>
                                <li>Система автоматически определит массивы и функции</li>
                                <li>Алгоритм декодирует все зашифрованные значения</li>
                                <li>Вы получите читаемый PHP код с подсветкой синтаксиса</li>
                                <li>Скачайте результат или скопируйте его напрямую</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- JavaScript Libraries -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/highlight.min.js"></script>
        
        <!-- Main Application Script -->
        <script>
        // Активация подсветки синтаксиса
        hljs.highlightAll();
        
        // Обработка drag and drop
        const dropZone = document.getElementById('fileDropZone');
        const fileInput = document.getElementById('fileInput');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        
        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropZone.classList.add('dragover');
        }
        
        function unhighlight() {
            dropZone.classList.remove('dragover');
        }
        
        // File drop handling
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        // File input change handling
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        // Main file handling function
        function handleFiles(files) {
            if (files.length === 0) return;
            
            const file = files[0];
            if (!file.name.endsWith('.php')) {
                showError('Пожалуйста, выберите PHP файл');
                return;
            }
            
            uploadFile(file);
        }
        
        // File upload function
        function uploadFile(file) {
            // Валидация файла
            const allowedTypes = [
                'application/x-php', 
                'text/x-php', 
                'text/php',
                'application/octet-stream'
            ];
            
            const isValidType = allowedTypes.includes(file.type) || 
                              file.name.endsWith('.php');
            
            if (!isValidType) {
                showError('Пожалуйста, выберите PHP файл. Получен тип: ' + file.type);
                return;
            }
            
            if (file.size > 10 * 1024 * 1024) { // 10MB
                showError('Размер файла не должен превышать 10MB');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'upload_file');
            
            progressContainer.classList.remove('d-none');
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showSuccess('Файл успешно загружен! Идет обработка...');
                            processDecoding(response.filename);
                        } else {
                            showError(response.message || 'Ошибка при загрузке файла');
                        }
                    } catch (e) {
                        showError('Ошибка обработки ответа сервера');
                    }
                } else {
                    showError('Ошибка сервера: ' + xhr.status);
                }
            });
            
            xhr.addEventListener('error', function() {
                showError('Ошибка сети при загрузке файла');
            });
            
            xhr.open('POST', '', true);
            xhr.send(formData);
        }
        
        // Process decoding
        function processDecoding(filename) {
            const formData = new FormData();
            formData.append('action', 'decode_file');
            formData.append('filename', filename);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResults(data.original, data.decoded);
                    
                    // Обновляем статистику
                    if (data.statistics) {
                        updateStats(data.statistics);
                    }
                    
                    // Обновляем обнаруженные массивы и функции
                    if (data.arrays || data.functions || data.variables) {
                        updateArraysAndFunctions(data.arrays || [], data.functions || [], data.variables || []);
                    }
                    
                    // Выводим консольные сообщения
                    if (data.console && data.console.length > 0) {
                        console.log('Decoder Console Output:', data.console);
                    }
                    
                    showSuccess('Декодирование завершено успешно!');
                } else {
                    showError(data.message || 'Ошибка при декодировании файла');
                }
            })
            .catch(error => {
                showError('Ошибка: ' + error.message);
            });
        }
        
        // Display results
        function displayResults(original, decoded) {
            document.getElementById('originalCode').textContent = original;
            document.getElementById('decodedCode').textContent = decoded;
            document.getElementById('resultsSection').classList.remove('d-none');
            
            // Re-highlight syntax
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightElement(block);
            });
        }
        
        // Download functionality
        document.getElementById('downloadBtn').addEventListener('click', function() {
            const decodedContent = document.getElementById('decodedCode').textContent;
            
            // Создание временной формы для скачивания
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const filenameInput = document.createElement('input');
            filenameInput.type = 'hidden';
            filenameInput.name = 'filename';
            filenameInput.value = 'decoded_result.php';
            
            const contentInput = document.createElement('input');
            contentInput.type = 'hidden';
            contentInput.name = 'content';
            contentInput.value = decodedContent;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'download';
            
            form.appendChild(filenameInput);
            form.appendChild(contentInput);
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            showSuccess('Начинается скачивание файла...');
        });
        
        // Utility functions
        function showError(message) {
            const alert = document.getElementById('errorAlert');
            document.getElementById('errorMessage').textContent = message;
            alert.classList.remove('d-none');
            hideOtherAlerts('errorAlert');
        }

        // Функция для обновления статистики
        function updateStats(stats) {
            const statsContainer = document.getElementById('stats');
            if (stats && Object.keys(stats).length > 0) {
                statsContainer.innerHTML = `
                    <div class="alert alert-info">
                        <h6>Статистика декодирования:</h6>
                        <ul class="mb-0">
                            ${Object.entries(stats).map(([key, value]) => 
                                `<li><strong>${key}:</strong> ${value}</li>`
                            ).join('')}
                        </ul>
                    </div>
                `;
                statsContainer.style.display = 'block';
            } else {
                statsContainer.style.display = 'none';
            }
        }
        
        // Функция для отображения информации о массивах, функциях и переменных
        function updateArraysAndFunctions(arrays, functions, variables) {
            const arraysContainer = document.getElementById('arraysInfo');
            const functionsContainer = document.getElementById('functionsInfo');
            
            // Отображение массивов
            if (arrays && arrays.length > 0) {
                arraysContainer.innerHTML = `
                    <div class="alert alert-success">
                        <h6>Обнаруженные массивы (${arrays.length}):</h6>
                        <div class="small">
                            ${arrays.map(arr => 
                                `<div><strong>${arr.name}</strong>: ${arr.size} элементов</div>`
                            ).join('')}
                        </div>
                    </div>
                `;
                arraysContainer.style.display = 'block';
            } else {
                arraysContainer.style.display = 'none';
            }
            
            // Отображение функций
            if (functions && functions.length > 0) {
                functionsContainer.innerHTML = `
                    <div class="alert alert-warning">
                        <h6>Обнаруженные функции (${functions.length}):</h6>
                        <div class="small">
                            ${functions.map(func => 
                                `<div><strong>${func.name}</strong>: ${func.complexity}</div>`
                            ).join('')}
                        </div>
                    </div>
                `;
                functionsContainer.style.display = 'block';
            } else {
                functionsContainer.style.display = 'none';
            }
            
            // Отображение переменных
            const variablesContainer = document.getElementById('variablesInfo');
            if (!variablesContainer) {
                // Создаем контейнер для переменных, если его нет
                const newContainer = document.createElement('div');
                newContainer.id = 'variablesInfo';
                newContainer.style.display = 'none';
                functionsContainer.parentNode.insertBefore(newContainer, functionsContainer.nextSibling);
            }
            
            const varsContainer = document.getElementById('variablesInfo');
            if (variables && variables.length > 0) {
                varsContainer.innerHTML = `
                    <div class="alert alert-info">
                        <h6>Обнаруженные переменные (${variables.length}):</h6>
                        <div class="small">
                            ${variables.slice(0, 10).map(variable => 
                                `<div><strong>${variable.name}</strong>: ${variable.type}</div>`
                            ).join('')}
                            ${variables.length > 10 ? `<div class="text-muted">... и ещё ${variables.length - 10}</div>` : ''}
                        </div>
                    </div>
                `;
                varsContainer.style.display = 'block';
            } else {
                varsContainer.style.display = 'none';
            }
        }
        
        // Функция для установки состояния загрузки с сообщением
        function setLoading(loading, message = 'Загрузка...') {
            const loadingElement = document.getElementById('loading');
            const loadingText = document.getElementById('loadingText');
            
            if (loading) {
                loadingText.textContent = message;
                loadingElement.style.display = 'block';
                decodeBtn.disabled = true;
                fileInput.disabled = true;
            } else {
                loadingElement.style.display = 'none';
                decodeBtn.disabled = false;
                fileInput.disabled = false;
            }
        }
        
        function showSuccess(message) {
            const alert = document.getElementById('successAlert');
            document.getElementById('successMessage').textContent = message;
            alert.classList.remove('d-none');
            hideOtherAlerts('successAlert');
        }
        
        function showInfo(message) {
            const alert = document.getElementById('infoAlert');
            document.getElementById('infoMessage').textContent = message;
            alert.classList.remove('d-none');
            hideOtherAlerts('infoAlert');
        }
        
        function hideOtherAlerts(except) {
            ['errorAlert', 'successAlert', 'infoAlert'].forEach(id => {
                if (id !== except) {
                    document.getElementById(id).classList.add('d-none');
                }
            });
        }
        </script>
    </body>
    </html>
    <?php
}

// Функции обработки запросов
function handleFileUpload() {
    if (!isset($_FILES['file'])) {
        return ['success' => false, 'message' => 'Файл не был загружен'];
    }
    
    $file = $_FILES['file'];
    
    // Валидация файла
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Ошибка загрузки файла'];
    }
    
    if ($file['type'] !== 'text/php' && !preg_match('/\.php$/i', $file['name'])) {
        return ['success' => false, 'message' => 'Поддерживаются только PHP файлы'];
    }
    
    // Сохранение файла
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = uniqid() . '_' . basename($file['name']);
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Не удалось сохранить файл'];
}

function processDecoding($content) {
    try {
        // Создаем экземпляр декодера
        $decoder = new Decoder($content);
        
        // Выполняем декодирование
        $decodedContent = $decoder->decode();
        
        // Получаем статистику и дополнительную информацию
        $statistics = $decoder->getStatistics();
        $detectedArrays = $decoder->getDetectedArrays();
        $detectedFunctions = $decoder->getDetectedFunctions();
        $detectedVariables = $decoder->getDetectedVariables();
        $consoleOutput = $decoder->getConsoleOutput();
        
        echo json_encode([
            'success' => true,
            'original' => $content,
            'decoded' => $decodedContent,
            'statistics' => $statistics,
            'arrays' => $detectedArrays,
            'functions' => $detectedFunctions,
            'variables' => $detectedVariables,
            'console' => $consoleOutput
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка декодирования: ' . $e->getMessage()
        ]);
    }
}

function processDecodingFromFile() {
    if (!isset($_POST['filename'])) {
        return ['success' => false, 'message' => 'Имя файла не указано'];
    }
    
    $filename = $_POST['filename'];
    $filepath = __DIR__ . '/uploads/' . $filename;
    
    if (!file_exists($filepath)) {
        return ['success' => false, 'message' => 'Файл не найден'];
    }
    
    // Читаем содержимое файла и обрабатываем
    $originalContent = file_get_contents($filepath);
    processDecoding($originalContent);
}

function handleDownload() {
    // Заглушка для скачивания
    header('Content-Type: application/php');
    header('Content-Disposition: attachment; filename="decoded_result.php"');
    echo "<?php\n// Decoded Bitrix File\n";
    exit;
}

// Запуск приложения
if (!defined('STDIN')) {
    renderInterface();
}


