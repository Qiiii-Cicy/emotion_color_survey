<?php
header('Content-Type: application/json; charset=utf-8');

// 允许跨域请求（如果需要）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 数据文件路径
$dataFile = 'data.json';

// 获取POST数据
$input = file_get_contents('php://input');
$postData = $_POST['data'] ?? $input;

// 如果没有数据，尝试从JSON输入获取
if (empty($postData) && !empty($input)) {
    $jsonData = json_decode($input, true);
    if ($jsonData) {
        $postData = json_encode($jsonData);
    }
}

// 解析JSON数据
$data = json_decode($postData, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => '无效的数据格式'
    ]);
    exit;
}

// 检查数据结构：新格式包含 preQuestionnaire 和 emotionColors
$emotionColors = [];
$preQuestionnaire = [];

if (isset($data['preQuestionnaire']) && isset($data['emotionColors'])) {
    // 新格式：包含预问卷和情绪颜色数据
    $preQuestionnaire = $data['preQuestionnaire'];
    $emotionColors = $data['emotionColors'];
} else {
    // 旧格式：只有情绪颜色数据（向后兼容）
    $emotionColors = $data;
}

// 验证数据是否包含所有必需的情绪
$requiredEmotions = ['anger', 'enjoyment', 'surprise', 'fear', 'disgust', 'sadness'];
$missingEmotions = array_diff($requiredEmotions, array_keys($emotionColors));

if (!empty($missingEmotions)) {
    echo json_encode([
        'success' => false,
        'message' => '缺少以下情绪的数据：' . implode(', ', $missingEmotions)
    ]);
    exit;
}

// 验证颜色值格式（应该是#RRGGBB格式）
foreach ($emotionColors as $emotion => $color) {
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        echo json_encode([
            'success' => false,
            'message' => "情绪 '{$emotion}' 的颜色值格式无效：{$color}"
        ]);
        exit;
    }
}

// 构建完整的数据记录
$dataRecord = $emotionColors; // 先添加情绪颜色数据
if (!empty($preQuestionnaire)) {
    $dataRecord['preQuestionnaire'] = $preQuestionnaire; // 添加预问卷数据
}
$dataRecord['timestamp'] = date('Y-m-d H:i:s'); // 添加时间戳

// 读取现有数据
$existingData = [];
if (file_exists($dataFile)) {
    $fileContent = file_get_contents($dataFile);
    $existingData = json_decode($fileContent, true);
    if (!is_array($existingData)) {
        $existingData = [];
    }
}

// 添加新数据
$existingData[] = $dataRecord;

// 保存数据
$result = file_put_contents($dataFile, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result === false) {
    echo json_encode([
        'success' => false,
        'message' => '无法保存数据到文件'
    ]);
    exit;
}

// 返回成功响应
echo json_encode([
    'success' => true,
    'message' => '数据已成功保存',
    'total_submissions' => count($existingData)
]);
?>

