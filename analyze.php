<?php
/**
 * 数据分析脚本
 * 用于计算每种情绪对应的平均颜色值
 */

header('Content-Type: text/html; charset=utf-8');

$dataFile = 'data.json';

// 检查文件是否存在
if (!file_exists($dataFile)) {
    die('<h1>错误 / Error</h1><p>数据文件不存在。请先收集一些数据。<br>Data file does not exist. Please collect some data first.</p>');
}

// 读取数据
$fileContent = file_get_contents($dataFile);
$data = json_decode($fileContent, true);

if (!is_array($data) || empty($data)) {
    die('<h1>错误 / Error</h1><p>数据文件为空或格式错误。<br>Data file is empty or format is incorrect.</p>');
}

$emotions = ['anger', 'enjoyment', 'surprise', 'fear', 'disgust', 'sadness'];
$emotionNames = [
    'anger' => '愤怒 / Anger',
    'enjoyment' => '快乐 / Enjoyment',
    'surprise' => '惊讶 / Surprise',
    'fear' => '恐惧 / Fear',
    'disgust' => '厌恶 / Disgust',
    'sadness' => '悲伤 / Sadness'
];

// 将十六进制颜色转换为RGB
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

// 将RGB转换为十六进制
function rgbToHex($r, $g, $b) {
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

// 计算两个RGB颜色之间的欧氏距离
function colorDistance($rgb1, $rgb2) {
    $dr = $rgb1['r'] - $rgb2['r'];
    $dg = $rgb1['g'] - $rgb2['g'];
    $db = $rgb1['b'] - $rgb2['b'];
    return sqrt($dr * $dr + $dg * $dg + $db * $db);
}

// 计算数据集中区域的平均颜色
// 使用基于质心和距离的密度分析方法
function calculateDenseRegionAverage($colors) {
    if (empty($colors)) {
        return null;
    }
    
    $count = count($colors);
    
    // 如果数据点少于3个，直接计算平均值
    if ($count < 3) {
        $totalR = 0;
        $totalG = 0;
        $totalB = 0;
        foreach ($colors as $color) {
            $rgb = hexToRgb($color);
            $totalR += $rgb['r'];
            $totalG += $rgb['g'];
            $totalB += $rgb['b'];
        }
        return [
            'hex' => rgbToHex(round($totalR / $count), round($totalG / $count), round($totalB / $count)),
            'rgb' => ['r' => round($totalR / $count), 'g' => round($totalG / $count), 'b' => round($totalB / $count)],
            'count' => $count,
            'total_count' => $count,
            'colors' => $colors
        ];
    }
    
    // 步骤1: 计算所有点的质心（中心点）
    // 公式: C = (1/n) * Σ(P_i)，其中P_i是每个颜色点的RGB坐标
    $totalR = 0;
    $totalG = 0;
    $totalB = 0;
    $rgbPoints = [];
    
    foreach ($colors as $color) {
        $rgb = hexToRgb($color);
        $rgbPoints[] = $rgb;
        $totalR += $rgb['r'];
        $totalG += $rgb['g'];
        $totalB += $rgb['b'];
    }
    
    $centroid = [
        'r' => $totalR / $count,
        'g' => $totalG / $count,
        'b' => $totalB / $count
    ];
    
    // 步骤2: 计算每个点到质心的欧氏距离
    // 公式: d_i = sqrt((R_i - R_c)² + (G_i - G_c)² + (B_i - B_c)²)
    $distances = [];
    foreach ($rgbPoints as $index => $rgb) {
        $distances[$index] = colorDistance($rgb, $centroid);
    }
    
    // 步骤3: 使用四分位数方法选择密集区域
    // 选择距离质心最近的50%的点（或者使用中位数+0.5倍四分位距作为阈值）
    $distanceValues = array_values($distances);
    sort($distanceValues);
    $medianIndex = floor($count / 2);
    $q1Index = floor($count / 4);
    $q3Index = floor(3 * $count / 4);
    
    $q1 = $distanceValues[$q1Index];
    $median = $distanceValues[$medianIndex];
    $q3 = $distanceValues[$q3Index];
    $iqr = $q3 - $q1; // 四分位距 (Interquartile Range)
    
    // 阈值：中位数 + 0.5倍四分位距（选择最密集的约50-60%的点）
    $threshold = $median + 0.5 * $iqr;
    
    // 步骤4: 筛选出密集区域内的点
    $denseColors = [];
    $denseRgbPoints = [];
    foreach ($rgbPoints as $index => $rgb) {
        $distance = $distances[$index];
        if ($distance <= $threshold) {
            $denseColors[] = $colors[$index];
            $denseRgbPoints[] = $rgb;
        }
    }
    
    // 如果密集区域点太少（少于30%），则使用中位数方法（选择最近的50%）
    if (count($denseColors) < $count * 0.3) {
        $denseColors = [];
        $denseRgbPoints = [];
        // 按距离排序，保持索引
        asort($distances);
        $sortedIndices = array_keys($distances);
        $top50Percent = array_slice($sortedIndices, 0, ceil($count * 0.5));
        foreach ($top50Percent as $index) {
            $denseColors[] = $colors[$index];
            $denseRgbPoints[] = $rgbPoints[$index];
        }
    }
    
    // 步骤5: 计算密集区域的平均值
    $denseCount = count($denseRgbPoints);
    $denseTotalR = 0;
    $denseTotalG = 0;
    $denseTotalB = 0;
    
    foreach ($denseRgbPoints as $rgb) {
        $denseTotalR += $rgb['r'];
        $denseTotalG += $rgb['g'];
        $denseTotalB += $rgb['b'];
    }
    
    return [
        'hex' => rgbToHex(round($denseTotalR / $denseCount), round($denseTotalG / $denseCount), round($denseTotalB / $denseCount)),
        'rgb' => ['r' => round($denseTotalR / $denseCount), 'g' => round($denseTotalG / $denseCount), 'b' => round($denseTotalB / $denseCount)],
        'count' => $denseCount,
        'total_count' => $count,
        'colors' => $colors // 保存所有颜色值用于色盘显示
    ];
}

// 收集每种情绪的所有颜色值
$emotionColors = [];
foreach ($emotions as $emotion) {
    $emotionColors[$emotion] = [];
}

// 收集预问卷数据
$preQuestionnaireData = [];
$preQuestionnaireStats = [
    'gender' => [],
    'age' => [],
    'education' => [],
    'psychology' => [],
    'visualization' => []
];

foreach ($data as $entry) {
    // 处理情绪颜色数据
    foreach ($emotions as $emotion) {
        if (isset($entry[$emotion])) {
            $emotionColors[$emotion][] = $entry[$emotion];
        }
    }
    
    // 处理预问卷数据
    if (isset($entry['preQuestionnaire'])) {
        $preQuestionnaireData[] = $entry['preQuestionnaire'];
        
        // 统计性别
        if (isset($entry['preQuestionnaire']['gender'])) {
            $gender = $entry['preQuestionnaire']['gender'];
            if (!isset($preQuestionnaireStats['gender'][$gender])) {
                $preQuestionnaireStats['gender'][$gender] = 0;
            }
            $preQuestionnaireStats['gender'][$gender]++;
        }
        
        // 统计年龄
        if (isset($entry['preQuestionnaire']['age'])) {
            $age = $entry['preQuestionnaire']['age'];
            if (!isset($preQuestionnaireStats['age'][$age])) {
                $preQuestionnaireStats['age'][$age] = 0;
            }
            $preQuestionnaireStats['age'][$age]++;
        }
        
        // 统计学历
        if (isset($entry['preQuestionnaire']['education'])) {
            $education = $entry['preQuestionnaire']['education'];
            // 如果是"其他"选项且有自定义文本，使用自定义文本作为键的一部分
            if ($education === 'other' && isset($entry['preQuestionnaire']['educationOther']) && !empty($entry['preQuestionnaire']['educationOther'])) {
                $educationKey = 'other: ' . $entry['preQuestionnaire']['educationOther'];
            } else {
                $educationKey = $education;
            }
            if (!isset($preQuestionnaireStats['education'][$educationKey])) {
                $preQuestionnaireStats['education'][$educationKey] = 0;
            }
            $preQuestionnaireStats['education'][$educationKey]++;
        }
        
        // 统计心理学熟练程度
        if (isset($entry['preQuestionnaire']['psychology'])) {
            $psychology = $entry['preQuestionnaire']['psychology'];
            if (!isset($preQuestionnaireStats['psychology'][$psychology])) {
                $preQuestionnaireStats['psychology'][$psychology] = 0;
            }
            $preQuestionnaireStats['psychology'][$psychology]++;
        }
        
        // 统计可视化熟练程度
        if (isset($entry['preQuestionnaire']['visualization'])) {
            $visualization = $entry['preQuestionnaire']['visualization'];
            if (!isset($preQuestionnaireStats['visualization'][$visualization])) {
                $preQuestionnaireStats['visualization'][$visualization] = 0;
            }
            $preQuestionnaireStats['visualization'][$visualization]++;
        }
    }
}

// 预问卷选项的中英文映射
$genderLabels = [
    'male' => 'Male 男士',
    'female' => 'Female 女士',
    'prefer-not-say' => 'Prefer not to say 不愿透露'
];

$ageLabels = [
    'under-18' => 'Under 18 18岁以下',
    '18-25' => '18-25 years old 18-25岁',
    '26-35' => '26-35 years old 26-35岁',
    '36-45' => '36-45 years old 36-45岁',
    '45-above' => '45 years old and above 45岁以上'
];

$educationLabels = [
    'high-school' => 'High school diploma or equivalent 高中文凭或同等学历',
    'bachelor' => "Bachelor's degree or equivalent 学士学位或同等学历",
    'master' => "Master's degree or equivalent 硕士学位或同等学历",
    'doctoral' => 'Doctoral degree or equivalent 博士学位或同等学历',
    'other' => 'Other 其他'
];

$proficiencyLabels = [
    'no-knowledge' => 'No knowledge at all 一点都不了解',
    'beginner' => 'Beginner 初级',
    'intermediate' => 'Intermediate 中级',
    'advanced' => 'Advanced 高级',
    'expert' => 'Expert 专业'
];

// 使用密集区域分析方法计算平均颜色
$averageColors = [];
foreach ($emotions as $emotion) {
    if (empty($emotionColors[$emotion])) {
        continue;
    }
    
    $result = calculateDenseRegionAverage($emotionColors[$emotion]);
    if ($result) {
        $averageColors[$emotion] = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>情绪颜色数据分析 / Emotion Color Data Analysis</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: white;
        }
        
        .analyze-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 10px;
        }
        
        .analyze-header {
            text-align: center;
            margin-bottom: 12px;
        }
        
        .analyze-header h1 {
            color: #333;
            font-size: 1.3em;
            margin-bottom: 5px;
            line-height: 1.3;
        }
        
        .analyze-header .en-title {
            font-size: 0.85em;
            color: #666;
            font-weight: normal;
        }
        
        .analyze-stats {
            color: #666;
            font-size: 0.75em;
            text-align: center;
            margin-bottom: 12px;
        }
        
        .pre-questionnaire-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .section-title {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 12px;
            border: 2px solid #e0e0e0;
        }
        
        .stat-title {
            font-size: 0.9em;
            color: #333;
            margin-bottom: 10px;
            font-weight: bold;
            text-align: center;
            padding-bottom: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .stat-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8em;
            padding: 4px 0;
        }
        
        .stat-label {
            color: #555;
            flex: 1;
        }
        
        .stat-value {
            color: #333;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .analyze-results {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .analyze-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            min-height: auto;
            display: flex;
            flex-direction: column;
        }
        
        .analyze-emotion-name {
            font-size: 0.9em;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.2;
        }
        
        .analyze-color-display {
            width: 100%;
            height: 35px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 2px solid #ddd;
        }
        
        .analyze-color-info {
            font-family: monospace;
            font-size: 0.7em;
            color: #666;
            margin-bottom: 4px;
        }
        
        .analyze-count-info {
            color: #999;
            font-size: 0.65em;
            margin-top: auto;
        }
        
        .analyze-color-wheel-container {
            margin-top: 10px;
            margin-bottom: 8px;
        }
        
        .analyze-color-wheel-label {
            font-size: 0.7em;
            color: #555;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .analyze-color-wheel-wrapper {
            position: relative;
            width: 100%;
            max-width: 180px;
            margin: 0 auto;
            aspect-ratio: 1;
            border: 2px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
            background: #f0f0f0;
        }
        
        .analyze-color-wheel {
            width: 100%;
            height: 100%;
            cursor: crosshair;
        }
        
        .analyze-color-point {
            position: absolute;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        
        .analyze-color-tooltip {
            position: fixed;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7em;
            font-family: monospace;
            pointer-events: none;
            z-index: 1000;
            white-space: nowrap;
            transform: translate(-50%, -100%);
        }
        
        
        /* 平板设备 */
        @media (max-width: 1024px) and (min-width: 769px) {
            .analyze-results {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            
            .analyze-card {
                padding: 8px;
                min-height: auto;
            }
        }
        
        /* 移动设备 */
        @media (max-width: 768px) {
            .analyze-container {
                padding: 8px;
            }
            
            .analyze-header h1 {
                font-size: 1.1em;
                margin-bottom: 4px;
            }
            
            .analyze-header .en-title {
                font-size: 0.75em;
            }
            
            .analyze-stats {
                font-size: 0.7em;
            }
            
            .pre-questionnaire-section {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .section-title {
                font-size: 1em;
                margin-bottom: 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 10px;
            }
            
            .stat-title {
                font-size: 0.85em;
            }
            
            .stat-item {
                font-size: 0.75em;
            }
            
            .analyze-results {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .analyze-card {
                padding: 8px;
                min-height: auto;
            }
            
            .analyze-emotion-name {
                font-size: 0.8em;
                margin-bottom: 6px;
            }
            
            .analyze-color-wheel-wrapper {
                max-width: 140px;
            }
        }
        
        /* 小屏手机 */
        @media (max-width: 480px) {
            .analyze-container {
                padding: 6px;
            }
            
            .pre-questionnaire-section {
                padding: 10px;
                margin-bottom: 12px;
            }
            
            .section-title {
                font-size: 0.95em;
                margin-bottom: 10px;
            }
            
            .stats-grid {
                gap: 10px;
            }
            
            .stat-card {
                padding: 8px;
            }
            
            .stat-title {
                font-size: 0.8em;
            }
            
            .stat-item {
                font-size: 0.7em;
            }
            
            .analyze-results {
                grid-template-columns: repeat(2, 1fr);
                gap: 6px;
            }
            
            .analyze-card {
                padding: 6px;
                min-height: auto;
            }
            
            .analyze-emotion-name {
                font-size: 0.75em;
                margin-bottom: 5px;
            }
            
            .analyze-header h1 {
                font-size: 1em;
            }
            
            .analyze-header .en-title {
                font-size: 0.7em;
            }
            
            .analyze-color-wheel-wrapper {
                max-width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="analyze-container">
        <header class="analyze-header">
            <h1>情绪颜色数据分析结果<br><span class="en-title">Emotion Color Data Analysis Results</span></h1>
            <p class="analyze-stats">总提交数 / Total Submissions: <?php echo count($data); ?> 份</p>
        </header>
        
        <?php if (!empty($preQuestionnaireData)): ?>
        <div class="pre-questionnaire-section">
            <h2 class="section-title">预问卷统计 / Pre-questionnaire Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3 class="stat-title">性别分布 / Gender Distribution</h3>
                    <div class="stat-content">
                        <?php foreach ($preQuestionnaireStats['gender'] as $gender => $count): ?>
                        <div class="stat-item">
                            <span class="stat-label"><?php echo isset($genderLabels[$gender]) ? $genderLabels[$gender] : $gender; ?>:</span>
                            <span class="stat-value"><?php echo $count; ?> (<?php echo round($count / count($preQuestionnaireData) * 100, 1); ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">年龄分布 / Age Distribution</h3>
                    <div class="stat-content">
                        <?php foreach ($preQuestionnaireStats['age'] as $age => $count): ?>
                        <div class="stat-item">
                            <span class="stat-label"><?php echo isset($ageLabels[$age]) ? $ageLabels[$age] : $age; ?>:</span>
                            <span class="stat-value"><?php echo $count; ?> (<?php echo round($count / count($preQuestionnaireData) * 100, 1); ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">学历分布 / Education Distribution</h3>
                    <div class="stat-content">
                        <?php foreach ($preQuestionnaireStats['education'] as $education => $count): ?>
                        <div class="stat-item">
                            <?php 
                            // 处理"其他"选项的自定义文本
                            if (strpos($education, 'other: ') === 0) {
                                $customText = substr($education, 7);
                                $label = 'Other: ' . htmlspecialchars($customText) . ' / 其他: ' . htmlspecialchars($customText);
                            } else {
                                $label = isset($educationLabels[$education]) ? $educationLabels[$education] : $education;
                            }
                            ?>
                            <span class="stat-label"><?php echo $label; ?>:</span>
                            <span class="stat-value"><?php echo $count; ?> (<?php echo round($count / count($preQuestionnaireData) * 100, 1); ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">心理学熟练程度 / Psychology Proficiency</h3>
                    <div class="stat-content">
                        <?php foreach ($preQuestionnaireStats['psychology'] as $level => $count): ?>
                        <div class="stat-item">
                            <span class="stat-label"><?php echo isset($proficiencyLabels[$level]) ? $proficiencyLabels[$level] : $level; ?>:</span>
                            <span class="stat-value"><?php echo $count; ?> (<?php echo round($count / count($preQuestionnaireData) * 100, 1); ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">可视化熟练程度 / Visualization Proficiency</h3>
                    <div class="stat-content">
                        <?php foreach ($preQuestionnaireStats['visualization'] as $level => $count): ?>
                        <div class="stat-item">
                            <span class="stat-label"><?php echo isset($proficiencyLabels[$level]) ? $proficiencyLabels[$level] : $level; ?>:</span>
                            <span class="stat-value"><?php echo $count; ?> (<?php echo round($count / count($preQuestionnaireData) * 100, 1); ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="analyze-results">
            <?php foreach ($averageColors as $emotion => $result): ?>
            <div class="analyze-card">
                <div class="analyze-emotion-name"><?php echo $emotionNames[$emotion]; ?></div>
                <div class="analyze-color-display" style="background-color: <?php echo $result['hex']; ?>"></div>
                <div class="analyze-color-info">HEX: <?php echo strtoupper($result['hex']); ?></div>
                <div class="analyze-color-info">RGB: (<?php echo $result['rgb']['r']; ?>, <?php echo $result['rgb']['g']; ?>, <?php echo $result['rgb']['b']; ?>)</div>
                <div class="analyze-color-wheel-container">
                    <div class="analyze-color-wheel-label">颜色分布 / Color Distribution</div>
                    <div class="analyze-color-wheel-wrapper" id="wheel-wrapper-<?php echo $emotion; ?>">
                        <canvas class="analyze-color-wheel" id="color-wheel-<?php echo $emotion; ?>" data-emotion="<?php echo $emotion; ?>" data-colors='<?php echo json_encode($result['colors']); ?>'></canvas>
                    </div>
                </div>
                <div class="analyze-count-info">密集区域: <?php echo $result['count']; ?> / 总样本: <?php echo $result['total_count']; ?><br>Dense Region: <?php echo $result['count']; ?> / Total: <?php echo $result['total_count']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // RGB转HSV
        function rgbToHsv(r, g, b) {
            r /= 255;
            g /= 255;
            b /= 255;
            
            const max = Math.max(r, g, b);
            const min = Math.min(r, g, b);
            const diff = max - min;
            
            let h = 0;
            if (diff !== 0) {
                if (max === r) {
                    h = ((g - b) / diff + (g < b ? 6 : 0)) / 6;
                } else if (max === g) {
                    h = ((b - r) / diff + 2) / 6;
                } else {
                    h = ((r - g) / diff + 4) / 6;
                }
            }
            
            const s = max === 0 ? 0 : diff / max;
            const v = max;
            
            return { h: h * 360, s: s * 100, v: v * 100 };
        }
        
        // 十六进制转RGB
        function hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : null;
        }
        
        // 绘制色盘
        function drawColorWheel(canvas, colors) {
            const ctx = canvas.getContext('2d');
            const size = canvas.width;
            const center = size / 2;
            const radius = center - 2;
            
            // 清空画布
            ctx.clearRect(0, 0, size, size);
            
            // 绘制HSV色盘（优化版本，使用更大的步长）
            const step = size > 200 ? 2 : 1;
            for (let y = 0; y < size; y += step) {
                for (let x = 0; x < size; x += step) {
                    const dx = x - center;
                    const dy = y - center;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance <= radius) {
                        const angle = Math.atan2(dy, dx);
                        const hue = (angle + Math.PI) / (2 * Math.PI) * 360;
                        const saturation = (distance / radius) * 100;
                        const value = 100;
                        
                        const rgb = hsvToRgb(hue, saturation, value);
                        ctx.fillStyle = `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
                        ctx.fillRect(x, y, step, step);
                    }
                }
            }
            
            // 绘制颜色点
            const points = [];
            colors.forEach(color => {
                const rgb = hexToRgb(color);
                if (rgb) {
                    const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
                    const angle = (hsv.h / 360) * 2 * Math.PI - Math.PI;
                    const distance = (hsv.s / 100) * radius;
                    const x = center + Math.cos(angle) * distance;
                    const y = center + Math.sin(angle) * distance;
                    
                    // 绘制点 - 增强可见性
                    ctx.fillStyle = color;
                    ctx.beginPath();
                    ctx.arc(x, y, 5, 0, 2 * Math.PI);
                    ctx.fill();
                    ctx.strokeStyle = '#000000';
                    ctx.lineWidth = 2;
                    ctx.stroke();
                    // 添加白色外圈增强对比度
                    ctx.strokeStyle = 'rgba(255, 255, 255, 1)';
                    ctx.lineWidth = 1.5;
                    ctx.beginPath();
                    ctx.arc(x, y, 6, 0, 2 * Math.PI);
                    ctx.stroke();
                    
                    points.push({ x, y, color });
                }
            });
            
            return points;
        }
        
        // HSV转RGB
        function hsvToRgb(h, s, v) {
            h /= 360;
            s /= 100;
            v /= 100;
            
            const i = Math.floor(h * 6);
            const f = h * 6 - i;
            const p = v * (1 - s);
            const q = v * (1 - f * s);
            const t = v * (1 - (1 - f) * s);
            
            let r, g, b;
            switch (i % 6) {
                case 0: r = v; g = t; b = p; break;
                case 1: r = q; g = v; b = p; break;
                case 2: r = p; g = v; b = t; break;
                case 3: r = p; g = q; b = v; break;
                case 4: r = t; g = p; b = v; break;
                case 5: r = v; g = p; b = q; break;
            }
            
            return {
                r: Math.round(r * 255),
                g: Math.round(g * 255),
                b: Math.round(b * 255)
            };
        }
        
        // 初始化所有色盘
        document.addEventListener('DOMContentLoaded', function() {
            const canvases = document.querySelectorAll('.analyze-color-wheel');
            
            canvases.forEach(canvas => {
                const wrapper = canvas.closest('.analyze-color-wheel-wrapper');
                const size = wrapper.offsetWidth;
                canvas.width = size;
                canvas.height = size;
                
                const colors = JSON.parse(canvas.getAttribute('data-colors'));
                const points = drawColorWheel(canvas, colors);
                
                // 创建工具提示
                const tooltip = document.createElement('div');
                tooltip.className = 'analyze-color-tooltip';
                tooltip.style.display = 'none';
                document.body.appendChild(tooltip);
                
                // 添加鼠标移动事件
                canvas.addEventListener('mousemove', function(e) {
                    const rect = canvas.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    // 检查是否在某个点附近（增大检测范围以匹配更大的点）
                    let found = false;
                    let closestPoint = null;
                    let minDistance = Infinity;
                    
                    // 找到最近的点
                    points.forEach(point => {
                        const distance = Math.sqrt(Math.pow(x - point.x, 2) + Math.pow(y - point.y, 2));
                        if (distance < 12 && distance < minDistance) {
                            minDistance = distance;
                            closestPoint = point;
                            found = true;
                        }
                    });
                    
                    if (found && closestPoint) {
                        tooltip.textContent = closestPoint.color.toUpperCase();
                        tooltip.style.display = 'block';
                        // 使用点的实际位置（画布坐标 + 页面偏移）来定位工具提示
                        // 工具提示显示在点的上方，稍微偏移
                        tooltip.style.left = (rect.left + closestPoint.x) + 'px';
                        tooltip.style.top = (rect.top + closestPoint.y - 25) + 'px';
                        tooltip.style.transform = 'translate(-50%, -100%)';
                    } else {
                        tooltip.style.display = 'none';
                    }
                });
                
                canvas.addEventListener('mouseleave', function() {
                    tooltip.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>

