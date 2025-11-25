// 六种情绪
const emotions = ['anger', 'enjoyment', 'surprise', 'fear', 'disgust', 'sadness'];

// 情绪的中英文名称
const emotionNames = {
    'anger': '愤怒 / Anger',
    'enjoyment': '快乐 / Enjoyment',
    'surprise': '惊讶 / Surprise',
    'fear': '恐惧 / Fear',
    'disgust': '厌恶 / Disgust',
    'sadness': '悲伤 / Sadness'
};

// 存储每个情绪选择的颜色
const selectedColors = {};

// 存储预问卷数据
const preQuestionnaireData = {};

// 当前活动的情绪卡片
let currentActiveCard = null;

// 页面切换函数
function showPage(pageId) {
    // 隐藏所有页面
    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });
    // 显示指定页面
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.style.display = 'block';
    }
}

// 初始化页面
function init() {
    // 显示第一页：知情同意书
    showPage('consentPage');
    
    // 绑定知情同意书按钮
    const consentBtn = document.getElementById('consentBtn');
    if (consentBtn) {
        consentBtn.addEventListener('click', () => {
            showPage('preQuestionnairePage');
        });
    }
    
    // 绑定预问卷表单提交
    const preQuestionnaireForm = document.getElementById('preQuestionnaireForm');
    if (preQuestionnaireForm) {
        preQuestionnaireForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handlePreQuestionnaireSubmit();
        });
        
        // 处理"其他"选项的显示/隐藏
        const educationRadios = document.querySelectorAll('input[name="education"]');
        const educationOtherInput = document.getElementById('educationOther');
        
        educationRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'other') {
                    educationOtherInput.style.display = 'block';
                    educationOtherInput.required = true;
                } else {
                    educationOtherInput.style.display = 'none';
                    educationOtherInput.required = false;
                    educationOtherInput.value = '';
                }
            });
        });
    }
    
    // 初始化调查页面（第三页）
    initSurveyPage();
}

// 处理预问卷提交
function handlePreQuestionnaireSubmit() {
    const form = document.getElementById('preQuestionnaireForm');
    const formData = new FormData(form);
    
    // 收集所有表单数据
    preQuestionnaireData.gender = formData.get('gender');
    preQuestionnaireData.age = formData.get('age');
    preQuestionnaireData.education = formData.get('education');
    if (preQuestionnaireData.education === 'other') {
        preQuestionnaireData.educationOther = formData.get('education-other');
    }
    preQuestionnaireData.psychology = formData.get('psychology');
    preQuestionnaireData.visualization = formData.get('visualization');
    
    // 切换到调查页面
    showPage('surveyPage');
}

// 初始化调查页面
function initSurveyPage() {
    const emotionSection = document.getElementById('emotionSection');
    
    if (emotionSection) {
        emotions.forEach(emotion => {
            const card = createEmotionCard(emotion);
            emotionSection.appendChild(card);
        });
        
        updateProgress();
    }
}

// 创建情绪卡片
function createEmotionCard(emotion) {
    const card = document.createElement('div');
    card.className = 'emotion-card';
    card.id = `card-${emotion}`;
    
    const emotionName = document.createElement('div');
    emotionName.className = `emotion-name ${emotion}`;
    emotionName.textContent = emotionNames[emotion];
    
    const colorPickerContainer = document.createElement('div');
    colorPickerContainer.className = 'color-picker-container';
    
    const label = document.createElement('label');
    label.className = 'color-picker-label';
    label.textContent = '选择颜色 / Select Color:';
    
    const wrapper = document.createElement('div');
    wrapper.className = 'color-picker-wrapper';
    
    const colorInput = document.createElement('input');
    colorInput.type = 'color';
    colorInput.id = `color-${emotion}`;
    colorInput.value = '#cccccc'; // 默认浅灰色
    colorInput.addEventListener('input', () => {
        updateSelectedColor(emotion, colorInput.value);
    });
    
    const selectedColorDisplay = document.createElement('div');
    selectedColorDisplay.className = 'selected-color';
    selectedColorDisplay.id = `display-${emotion}`;
    selectedColorDisplay.textContent = colorInput.value.toUpperCase();
    
    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'confirm-btn';
    confirmBtn.textContent = '确定 / Confirm';
    confirmBtn.addEventListener('click', () => {
        confirmSelection(emotion, colorInput.value);
    });
    
    wrapper.appendChild(colorInput);
    colorPickerContainer.appendChild(label);
    colorPickerContainer.appendChild(wrapper);
    colorPickerContainer.appendChild(selectedColorDisplay);
    colorPickerContainer.appendChild(confirmBtn);
    
    card.appendChild(emotionName);
    card.appendChild(colorPickerContainer);
    
    return card;
}

// 更新选中的颜色显示
function updateSelectedColor(emotion, color) {
    const display = document.getElementById(`display-${emotion}`);
    display.textContent = color.toUpperCase();
    
    // 如果已经确认过，但颜色改变了，则移除completed状态，允许重新确认
    const card = document.getElementById(`card-${emotion}`);
    const confirmBtn = card.querySelector('.confirm-btn');
    if (card.classList.contains('completed')) {
        const currentColor = selectedColors[emotion];
        if (currentColor && currentColor !== color) {
            card.classList.remove('completed');
            confirmBtn.textContent = '确定 / Confirm';
            // 从已选择列表中移除，直到重新确认
            delete selectedColors[emotion];
            updateProgress();
            // 检查是否需要隐藏提交按钮
            const submitSection = document.getElementById('submitSection');
            if (Object.keys(selectedColors).length < emotions.length) {
                submitSection.style.display = 'none';
            }
        }
    }
}

// 确认选择
function confirmSelection(emotion, color) {
    selectedColors[emotion] = color;
    
    // 更新卡片状态
    const card = document.getElementById(`card-${emotion}`);
    card.classList.add('completed');
    card.classList.remove('active');
    
    // 更新按钮文字，但不禁用，允许继续修改
    const confirmBtn = card.querySelector('.confirm-btn');
    confirmBtn.textContent = '已确认 / Confirmed';
    
    // 移除当前活动状态
    if (currentActiveCard) {
        currentActiveCard.classList.remove('active');
    }
    
    updateProgress();
    
    // 检查是否所有情绪都已选择
    if (Object.keys(selectedColors).length === emotions.length) {
        showSubmitSection();
    }
}

// 更新进度条
function updateProgress() {
    const completed = Object.keys(selectedColors).length;
    const total = emotions.length;
    const percentage = (completed / total) * 100;
    
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    
    progressFill.style.width = `${percentage}%`;
    progressText.textContent = `${completed} / ${total} 已完成 / Completed`;
}

// 显示提交区域
function showSubmitSection() {
    const submitSection = document.getElementById('submitSection');
    submitSection.style.display = 'block';
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.addEventListener('click', submitData);
}

// 提交数据到后端
function submitData() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = '提交中... / Submitting...';
    
    // 创建FormData，包含情绪颜色映射和预问卷数据
    const formData = new FormData();
    const allData = {
        preQuestionnaire: preQuestionnaireData,
        emotionColors: selectedColors
    };
    formData.append('data', JSON.stringify(allData));
    
    // 发送到PHP后端
    fetch('submit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            submitBtn.textContent = '提交成功！/ Success!';
            submitBtn.style.background = 'linear-gradient(135deg, #4caf50 0%, #45a049 100%)';
            
            // 显示成功消息
            setTimeout(() => {
                alert('感谢您的参与！您的调查结果已成功提交。\nThank you for your participation! Your survey results have been submitted successfully.');
                // 可选：重置页面或跳转
                // location.reload();
            }, 500);
        } else {
            submitBtn.textContent = '提交失败，请重试 / Failed, Retry';
            submitBtn.disabled = false;
            alert('提交失败：' + (data.message || '未知错误') + '\nSubmission failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitBtn.textContent = '提交失败，请重试 / Failed, Retry';
        submitBtn.disabled = false;
        alert('提交失败：网络错误，请检查您的连接。\nSubmission failed: Network error, please check your connection.');
    });
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', init);

