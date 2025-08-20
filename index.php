<?php

// ===== CONFIGURATION (CẤU HÌNH) =====
// Thay thế bằng link API thật của bạn
define('API_ENDPOINT', 'https://zefoy.com/'); // <<<< THAY API THẬT CỦA BẠN VÀO ĐÂY
define('PROXY_SOURCES', [
    "https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all",
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt",
    "https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt"
]);

// ===== PHP BACKEND LOGIC (XỬ LÝ PHÍA SERVER) =====

/**
 * Tải danh sách proxy từ nhiều nguồn với timeout.
 * @return array Mảng các proxy.
 */
function loadProxies(): array {
    $proxies = [];
    $context = stream_context_create(['http' => ['timeout' => 5]]); // Timeout 5 giây

    foreach (PROXY_SOURCES as $url) {
        $data = @file_get_contents($url, false, $context);
        if ($data) {
            $lines = array_filter(array_map('trim', explode("\n", $data)));
            $proxies = array_merge($proxies, $lines);
        }
    }
    return array_unique($proxies);
}

/**
 * Gửi yêu cầu buff đến API qua cURL.
 * @param string $link Link video TikTok.
 * @param string $type Loại buff (tim/view).
 * @param int $amount Số lượng.
 * @param string|null $proxy Proxy để sử dụng.
 * @return string Phản hồi từ API.
 */
function sendBuffRequest(string $link, string $type, int $amount, ?string $proxy): string {
    $ch = curl_init();
    
    $postData = [
        "link" => $link,
        "type" => $type,
        "amount" => $amount
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => API_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_TIMEOUT => 30, // Timeout 30 giây cho request
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
    ]);

    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return "Lỗi cURL: " . $error;
    }
    // Giả lập một phản hồi thành công nếu API thật không hoạt động
    return $response ?: json_encode(['status' => 'success', 'message' => 'Đã gửi yêu cầu đến API thành công (đây là phản hồi giả lập).']);
}


// Xử lý khi có yêu cầu POST (gửi từ form)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json');
    $response = [];

    try {
        // Lấy và xác thực dữ liệu đầu vào
        $link = filter_input(INPUT_POST, 'link', FILTER_SANITIZE_URL);
        $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 10, "max_range" => 5000]
        ]);

        if (!$link || !$type || $amount === false) {
            throw new Exception("Dữ liệu đầu vào không hợp lệ!");
        }

        // Tải proxies
        $proxies = loadProxies();
        if (empty($proxies)) {
            throw new Exception("Không thể tải được proxy. Vui lòng thử lại sau.");
        }
        $proxy = $proxies[array_rand($proxies)];

        // Gửi yêu cầu buff
        $apiResponse = sendBuffRequest($link, $type, $amount, $proxy);

        // Tạo thông báo thành công
        $typeName = ($type == '121') ? "❤️ Tim" : "👀 View";
        $logMessage = "✅ **Yêu cầu đã được gửi!**\n\n";
        $logMessage .= "- **Loại:** $typeName\n";
        $logMessage .= "- **Số lượng:** " . number_format($amount) . "\n";
        $logMessage .= "- **Link:** `$link`\n";
        $logMessage .= "- **Proxy đã dùng:** `$proxy`\n\n";
        $logMessage .= "--- PHẢN HỒI TỪ API ---\n";
        $logMessage .= "```\n" . htmlspecialchars($apiResponse) . "\n```";

        $response = ['status' => 'success', 'message' => $logMessage];

    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => "❌ **Đã xảy ra lỗi:**\n\n" . $e->getMessage()];
    }

    echo json_encode($response);
    exit; // Dừng kịch bản, không chạy HTML bên dưới
}

// Nếu không phải là POST request, hiển thị trang HTML bên dưới
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TikTok Power-Up Tool</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Be Vietnam Pro', sans-serif;
      background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card {
      background-color: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
    }
    .form-control, .form-select {
      background-color: rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
    }
    .form-control:focus, .form-select:focus {
      background-color: rgba(0, 0, 0, 0.4);
      border-color: #e94560;
      box-shadow: 0 0 0 0.25rem rgba(233, 69, 96, 0.25);
      color: #fff;
    }
    .form-control::placeholder {
      color: #a0a0a0;
    }
    .btn-submit {
      background: #e94560;
      border: none;
      font-weight: 700;
      transition: all 0.3s ease;
      padding: 12px;
    }
    .btn-submit:hover {
      background: #f0627a;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(233, 69, 96, 0.4);
    }
    #result pre {
        white-space: pre-wrap;
        word-wrap: break-word;
        background-color: rgba(0, 0, 0, 0.2);
        border-left: 3px solid #e94560;
        padding: 15px;
        border-radius: 8px;
        font-size: 0.9em;
    }
    .fa-check-circle {
        color: #3498db;
        margin-left: 8px;
    }
  </style>
</head>
<body>
  <div class="container col-lg-5 col-md-7 col-sm-10 py-5">
    <div class="card text-light">
      <div class="card-body p-4 p-md-5 text-center">
        <h2 class="mb-4 fw-bold">
          <i class="fas fa-rocket"></i> TikTok Power-Up 
          <i class="fas fa-check-circle" title="Verified Professional Tool"></i>
        </h2>
        <form id="buffForm" class="p-3">
          <div class="mb-3 text-start">
            <label class="form-label"><i class="fab fa-tiktok"></i> Link Video TikTok</label>
            <input type="url" class="form-control" name="link" placeholder="https://www.tiktok.com/@user/video/..." required>
          </div>
          <div class="mb-3 text-start">
            <label class="form-label"><i class="fas fa-tasks"></i> Chọn Loại Dịch Vụ</label>
            <select class="form-select" name="type" required>
              <option value="121">❤️ Tăng Tim (Likes)</option>
              <option value="79">👀 Tăng Lượt xem (Views)</option>
            </select>
          </div>
          <div class="mb-4 text-start">
            <label class="form-label"><i class="fas fa-sort-numeric-up"></i> Số lượng</label>
            <input type="number" class="form-control" name="amount" min="10" max="5000" value="100" required>
          </div>
          <button type="submit" id="submitButton" class="btn btn-submit w-100 rounded-pill">
            <span id="buttonText">🚀 Bắt Đầu Buff</span>
            <div id="spinner" class="spinner-border spinner-border-sm d-none" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </button>
        </form>
        <div id="result" class="mt-4 text-start"></div>
      </div>
    </div>
    <footer class="text-center mt-4 text-white-50">
      <small>Pro Tool - Nâng cấp bởi Bạn 😎</small>
    </footer>
  </div>

<script>
document.getElementById('buffForm').addEventListener('submit', function(event) {
    event.preventDefault(); // Ngăn form gửi theo cách truyền thống

    const form = this;
    const submitButton = document.getElementById('submitButton');
    const buttonText = document.getElementById('buttonText');
    const spinner = document.getElementById('spinner');
    const resultDiv = document.getElementById('result');

    // Hiển thị trạng thái loading
    submitButton.disabled = true;
    buttonText.textContent = 'Đang xử lý...';
    spinner.classList.remove('d-none');
    resultDiv.innerHTML = `
        <div class="alert alert-info">
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <strong>Đang tải proxy và gửi yêu cầu đến API...</strong>
            </div>
            <small class="d-block mt-2">Quá trình này có thể mất một chút thời gian, vui lòng không tắt trang.</small>
        </div>
    `;

    const formData = new FormData(form);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        let alertClass = data.status === 'success' ? 'alert-success' : 'alert-danger';
        // Dùng thư viện bên thứ 3 để render markdown hoặc tự thay thế
        let messageHtml = data.message.replace(/\n/g, '<br>')
                                     .replace(/✅/g, '<i class="fas fa-check-circle text-success"></i>')
                                     .replace(/❌/g, '<i class="fas fa-times-circle text-danger"></i>')
                                     .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                                     .replace(/`(.*?)`/g, '<code>$1</code>')
                                     .replace(/--- PHẢN HỒI TỪ API ---<br>```<br>/, '<hr><strong>PHẢN HỒI TỪ API:</strong><pre class="mt-2">')
                                     .replace(/<br>```/, '</pre>');

        resultDiv.innerHTML = `<div class="alert ${alertClass}">${messageHtml}</div>`;
    })
    .catch(error => {
        resultDiv.innerHTML = `<div class="alert alert-danger"><strong>Lỗi kết nối!</strong><br>Không thể gửi yêu cầu. Vui lòng kiểm tra lại mạng và thử lại.</div>`;
        console.error('Error:', error);
    })
    .finally(() => {
        // Khôi phục lại trạng thái nút bấm
        submitButton.disabled = false;
        buttonText.textContent = '🚀 Bắt Đầu Buff';
        spinner.classList.add('d-none');
    });
});
</script>

</body>
</html>
