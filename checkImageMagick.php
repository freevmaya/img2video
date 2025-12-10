<?
function checkImageMagick() {
    $results = [];
    
    // Проверка через exec/command
    $commands = [
        'convert --version' => 'ImageMagick convert',
        'identify --version' => 'ImageMagick identify',
        'mogrify --version' => 'ImageMagick mogrify',
        'composite --version' => 'ImageMagick composite'
    ];
    
    foreach ($commands as $cmd => $description) {
        $output = [];
        $returnCode = 0;
        
        @exec($cmd . ' 2>&1', $output, $returnCode);
        
        $results[$description] = [
            'installed' => $returnCode === 0,
            'version' => $returnCode === 0 ? trim(implode("\n", $output)) : 'Не установлен',
            'command' => $cmd
        ];
    }
    
    // Проверка PHP extension
    $results['PHP Imagick Extension'] = [
        'installed' => extension_loaded('imagick'),
        'version' => extension_loaded('imagick') ? phpversion('imagick') : 'Не установлен'
    ];
    
    // Проверка через which/whereis
    $locations = ['convert', 'identify', 'mogrify'];
    foreach ($locations as $tool) {
        $path = [];
        @exec("which {$tool} 2>/dev/null || whereis {$tool} 2>/dev/null", $path, $code);
        $results["Path {$tool}"] = [
            'installed' => $code === 0,
            'location' => !empty($path) ? implode(', ', $path) : 'Не найден'
        ];
    }
    
    return $results;
}

// Использование:
$check = checkImageMagick();
echo "<pre>";
print_r($check);
echo "</pre>";