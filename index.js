const express = require('express');
const axios = require('axios');
const path = require('path');
const { HttpsProxyAgent } = require('https-proxy-agent'); // <<< THÊM DÒNG NÀY

const app = express();
const PORT = process.env.PORT || 3000;

app.use(express.urlencoded({ extended: true }));
app.use(express.json());

const API_ENDPOINT = 'https://zefoy.com/';
const PROXY_SOURCES = [
    "https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all",
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt",
    "https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt"
];

async function loadProxies() {
    console.log('Bắt đầu tải proxy...');
    const promises = PROXY_SOURCES.map(url => axios.get(url, { timeout: 5000 }).catch(err => {
        console.error(`Lỗi khi tải proxy từ ${url}: ${err.message}`);
        return null;
    }));

    const results = await Promise.all(promises);
    let proxies = [];

    results.forEach(response => {
        if (response && response.data) {
            const lines = response.data.split('\n').map(p => p.trim()).filter(Boolean);
            proxies.push(...lines);
        }
    });

    console.log(`Tải thành công ${proxies.length} proxy.`);
    return [...new Set(proxies)];
}


// ===== SỬA HÀM NÀY =====
/**
 * Gửi yêu cầu buff đến API.
 * @param {string} link Link video TikTok.
 * @param {string} type Loại buff (tim/view).
 * @param {number} amount Số lượng.
 * @param {string|null} proxy Proxy để sử dụng.
 * @returns {Promise<string>} Phản hồi từ API.
 */
async function sendBuffRequest(link, type, amount, proxy) {
    const postData = new URLSearchParams({ link, type, amount }).toString();
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
    };
    
    // Tạo agent cho proxy nếu có
    const agent = proxy ? new HttpsProxyAgent(`http://${proxy}`) : null;
    
    try {
        const response = await axios.post(API_ENDPOINT, postData, {
            headers,
            httpsAgent: agent, // <<< SỬ DỤNG AGENT TẠI ĐÂY
            timeout: 30000
        });
        return response.data || JSON.stringify({ status: 'success', message: 'Đã gửi yêu cầu đến API (đây là phản hồi giả lập).' });
    } catch (error) {
        // Cung cấp thông báo lỗi rõ ràng hơn
        let errorMessage = `Lỗi Axios: ${error.message}`;
        if (error.response) {
            errorMessage += ` | Status: ${error.response.status}`;
        }
        return errorMessage;
    }
}
// ===== KẾT THÚC SỬA ĐỔI =====


// ===== CÁC PHẦN CÒN LẠI GIỮ NGUYÊN =====
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

app.post('/buff', async (req, res) => {
    try {
        const { link, type, amount } = req.body;

        if (!link || !type || !amount) {
            return res.status(400).json({ status: 'error', message: 'Dữ liệu đầu vào không hợp lệ!' });
        }
        
        const proxies = await loadProxies();
        if (proxies.length === 0) {
            throw new Error("Không thể tải được proxy. Vui lòng thử lại sau.");
        }
        const proxy = proxies[Math.floor(Math.random() * proxies.length)];

        const apiResponse = await sendBuffRequest(link, type, parseInt(amount), proxy);
        
        const typeName = (type == '121') ? "❤️ Tim" : "👀 View";
        const logMessage = `✅ **Yêu cầu đã được gửi!**\n\n` +
                           `- **Loại:** ${typeName}\n` +
                           `- **Số lượng:** ${parseInt(amount).toLocaleString()}\n` +
                           `- **Link:** \`${link}\`\n` +
                           `- **Proxy đã dùng:** \`${proxy}\`\n\n` +
                           `--- PHẢN HỒI TỪ API ---\n` +
                           `\`\`\`\n${apiResponse}\n\`\`\``;

        res.json({ status: 'success', message: logMessage });

    } catch (error) {
        res.status(500).json({ status: 'error', message: `❌ **Đã xảy ra lỗi server:**\n\n${error.message}` });
    }
});

app.listen(PORT, () => {
    console.log(`🚀 Server đang chạy tại http://localhost:${PORT}`);
});
