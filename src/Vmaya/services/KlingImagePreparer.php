<?php

class KlingImagePreparer {
    /**
     * Поддерживаемые разрешения Kling AI и их размеры
     */
    private const RESOLUTION_MAP = [
        '360p'  => ['width' => 640,  'height' => 360],
        '480p'  => ['width' => 854,  'height' => 480],
        '540p'  => ['width' => 960,  'height' => 540],
        '720p'  => ['width' => 1280, 'height' => 720],
        '1080p' => ['width' => 1920, 'height' => 1080],
        '1440p' => ['width' => 2560, 'height' => 1440],
        '4k'    => ['width' => 3840, 'height' => 2160],
    ];

    /**
     * Поддерживаемые ориентации и их соотношения сторон
     */
    private const ASPECT_RATIOS = [
        '16:9'  => 16/9,
        '1:1'   => 1,
        '9:16'  => 9/16,
        '4:3'   => 4/3,
        '3:4'   => 3/4,
    ];

    /**
     * Основной метод для подготовки изображения
     * 
     * @param string $sourcePath Путь к исходному изображению
     * @param string $targetPath Путь для сохранения обработанного изображения
     * @param string $resolution Целевое разрешение ('720p', '1080p', etc.)
     * @param string|null $orientation Целевая ориентация ('16:9', '9:16', '1:1'). Если null - определяется автоматически
     * @return array Массив с информацией о результате обработки
     * @throws Exception Если что-то пошло не так
     */
    public function prepareImage(
        string $sourcePath,
        string $targetPath,
        string $resolution = '720p',
        ?string $orientation = null
    ): array {
        // 1. Валидация входных параметров
        $this->validateParameters($resolution, $orientation);

        $targetPath = empty($targetPath) ? $sourcePath : $targetPath;
        
        // 2. Загружаем исходное изображение для получения его размеров
        $sourceImage = $this->loadImage($sourcePath);
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // 3. Определяем ориентацию исходного изображения
        $detectedOrientation = $this->detectImageOrientation($sourceWidth, $sourceHeight);
        
        // 4. Используем переданную ориентацию или определенную автоматически
        $finalOrientation = $orientation ?? $detectedOrientation;
        
        // 5. Получаем целевые размеры
        $targetDims = $this->getTargetDimensions($resolution, $finalOrientation);
        
        // 6. Рассчитываем масштабирование с сохранением пропорций

        $scale = max(
            $targetDims['width'] / $sourceWidth,
            $targetDims['height'] / $sourceHeight
        );
        
        $newWidth = (int)round($sourceWidth * $scale);
        $newHeight = (int)round($sourceHeight * $scale);
        
        // 7. Создаем изображение с целевыми размерами
        $resizedImage = imagecreatetruecolor($targetDims['width'], $targetDims['height']);
        
        // 8. Создаем белый фон (можно изменить на другой цвет)
        $backgroundColor = imagecolorallocate($resizedImage, 255, 255, 255);
        imagefill($resizedImage, 0, 0, $backgroundColor);
        
        // 9. Рассчитываем координаты для центрирования масштабированного изображения
        $destX = (int)(($targetDims['width'] - $newWidth) / 2);
        $destY = (int)(($targetDims['height'] - $newHeight) / 2);
        
        // 10. Копируем и масштабируем изображение
        imagecopyresampled(
            $resizedImage, $sourceImage,
            $destX, $destY, 0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );
        
        if (version_compare(PHP_VERSION, '8.0.0', '<'))
            imagedestroy($sourceImage);
        
        // 11. Сохраняем результат
        $this->saveImage($resizedImage, $targetPath);
        
        // 12. Формируем информацию о результате
        $resultInfo = [
            'original_size' => "{$sourceWidth}x{$sourceHeight}",
            'original_orientation' => $detectedOrientation,
            'target_resolution' => $resolution,
            'target_orientation' => $finalOrientation,
            'final_size' => "{$targetDims['width']}x{$targetDims['height']}",
            'file_path' => $targetPath,
            'file_size_kb' => round(filesize($targetPath) / 1024, 2),
            'scale_factor' => round($scale, 3),
            'position_x' => $destX,
            'position_y' => $destY,
            'scaled_size' => "{$newWidth}x{$newHeight}"
        ];
        
        if (version_compare(PHP_VERSION, '8.0.0', '<'))
            imagedestroy($resizedImage);
        
        return $resultInfo;
    }
    
    /**
     * Определяет ориентацию изображения на основе его пропорций
     */
    private function detectImageOrientation(int $width, int $height): string {
        $aspectRatio = $width / $height;
        
        $bestMatch = '16:9';
        $minDifference = PHP_FLOAT_MAX;
        
        foreach (self::ASPECT_RATIOS as $orientation => $targetRatio) {
            $difference = abs($aspectRatio - $targetRatio);
            
            if ($difference < $minDifference) {
                $minDifference = $difference;
                $bestMatch = $orientation;
            }
        }
        
        return $bestMatch;
    }
    
    /**
     * Валидация входных параметров
     */
    private function validateParameters(string $resolution, ?string $orientation): void {
        if (!isset(self::RESOLUTION_MAP[$resolution])) {
            throw new Exception("Неподдерживаемое разрешение: {$resolution}. 
                Доступные: " . implode(', ', array_keys(self::RESOLUTION_MAP)));
        }
        
        if ($orientation !== null && !isset(self::ASPECT_RATIOS[$orientation])) {
            throw new Exception("Неподдерживаемая ориентация: {$orientation}. 
                Доступные: " . implode(', ', array_keys(self::ASPECT_RATIOS)));
        }
    }
    
    /**
     * Получение целевых размеров
     */
    private function getTargetDimensions(string $resolution, string $orientation): array {
        $baseDims = self::RESOLUTION_MAP[$resolution];
        
        switch ($orientation) {
            case '1:1':
                $size = min($baseDims['width'], $baseDims['height']);
                return ['width' => $size, 'height' => $size];
                
            case '9:16':
                return [
                    'width' => $baseDims['height'],
                    'height' => $baseDims['width']
                ];
                
            case '3:4':
                $height = (int)($baseDims['width'] * 3 / 4);
                return ['width' => $baseDims['width'], 'height' => $height];
                
            case '4:3':
                $height = (int)($baseDims['width'] * 3 / 4);
                return ['width' => $baseDims['width'], 'height' => $height];
                
            default:
                return $baseDims;
        }
    }
    
    /**
     * Загрузка изображения
     */
    private function loadImage(string $path) {
        if (!file_exists($path)) {
            throw new Exception("Файл не найден: {$path}");
        }
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($path);
                break;
            case 'png':
                $image = imagecreatefrompng($path);
                imagesavealpha($image, true);
                break;
            case 'gif':
                $image = imagecreatefromgif($path);
                break;
            case 'webp':
                $image = imagecreatefromwebp($path);
                break;
            default:
                throw new Exception("Неподдерживаемый формат: {$extension}");
        }
        
        if (!$image) {
            throw new Exception("Не удалось загрузить изображение: {$path}");
        }
        
        return $image;
    }
    
    /**
     * Сохранение изображения
     */
    private function saveImage($image, string $path): void {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $path, 90);
                break;
            case 'png':
                imagepng($image, $path, 9);
                break;
            case 'gif':
                imagegif($image, $path);
                break;
            case 'webp':
                imagewebp($image, $path, 90);
                break;
            default:
                imagejpeg($image, $path, 90);
        }
    }
}

// Пример использования
/*
$preparer = new KlingImagePreparer();

try {
    // Автоматическое определение ориентации
    $result = $preparer->prepareImage(
        sourcePath: 'input.jpg',
        targetPath: 'output_1080p.jpg',
        resolution: '1080p',
        orientation: null
    );
    
    echo "Подготовка завершена!\n";
    echo "Исходный размер: {$result['original_size']}\n";
    echo "Исходная ориентация: {$result['original_orientation']}\n";
    echo "Целевое разрешение: {$result['target_resolution']}\n";
    echo "Использованная ориентация: {$result['target_orientation']}\n";
    echo "Финальный размер: {$result['final_size']}\n";
    echo "Масштаб: x{$result['scale_factor']}\n";
    echo "Размер после масштабирования: {$result['scaled_size']}\n";
    echo "Позиция на холсте: X={$result['position_x']}, Y={$result['position_y']}\n";
    echo "Файл: {$result['file_path']} ({$result['file_size_kb']} KB)\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
*/
?>