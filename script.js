document.addEventListener('DOMContentLoaded', () => {
    const longUrlInput = document.getElementById('longUrl');
    const customAliasInput = document.getElementById('customAlias');
    const shortenBtn = document.getElementById('shortenBtn');
    const errorMsg = document.getElementById('errorMsg');
    const resultDiv = document.getElementById('result');
    const originalLink = document.getElementById('originalLink');
    const shortLinkAnchor = document.getElementById('shortLink');
    const copyBtn = document.getElementById('copyBtn');
    const qrBtn = document.getElementById('qrBtn');
    const qrContainer = document.getElementById('qrCodeContainer');

    function showError(message) {
        errorMsg.textContent = message;
        errorMsg.style.display = 'block';
        resultDiv.style.display = 'none';
    }

    function clearError() {
        errorMsg.textContent = '';
        errorMsg.style.display = 'none';
    }

    async function shortenUrl() {
        let url = longUrlInput.value.trim();
        if (!url) {
            showError('দয়া করে একটি URL দিন');
            return;
        }

        const customCode = customAliasInput.value.trim();

        shortenBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> প্রক্রিয়াকরণ...';
        shortenBtn.disabled = true;
        clearError();
        resultDiv.style.display = 'none';
        qrContainer.style.display = 'none';

        try {
            const response = await fetch('shorten.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: url, custom_code: customCode })
            });
            const data = await response.json();
            if (data.success) {
                originalLink.href = url;
                originalLink.textContent = url;
                shortLinkAnchor.href = data.short_url;
                shortLinkAnchor.textContent = data.short_url;
                resultDiv.style.display = 'block';
                resultDiv.scrollIntoView({ behavior: 'smooth' });
                customAliasInput.value = '';
            } else {
                showError(data.error || 'শর্ট করতে ব্যর্থ হয়েছে');
            }
        } catch (err) {
            showError('নেটওয়ার্ক সমস্যা, আবার চেষ্টা করুন');
        } finally {
            shortenBtn.innerHTML = '<i class="fas fa-scissors"></i> <span>Shorten</span>';
            shortenBtn.disabled = false;
        }
    }

    function copyToClipboard() {
        const text = shortLinkAnchor.textContent;
        if (!text || text === '#') return;
        navigator.clipboard.writeText(text).then(() => {
            const original = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i> কপি হয়েছে!';
            setTimeout(() => { copyBtn.innerHTML = original; }, 1500);
        }).catch(() => {
            alert('ম্যানুয়ালি কপি করুন: ' + text);
        });
    }

    if (qrBtn) {
        qrBtn.addEventListener('click', () => {
            const shortUrl = shortLinkAnchor.textContent;
            if (!shortUrl || shortUrl === '#') return;
            qrContainer.style.display = 'block';
            qrContainer.innerHTML = `<img src="qr.php?code=${encodeURIComponent(shortUrl)}" alt="QR Code" style="background: white; padding: 10px; border-radius: 10px; max-width:200px;">`;
        });
    }

    shortenBtn.addEventListener('click', shortenUrl);
    copyBtn.addEventListener('click', copyToClipboard);
    longUrlInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') shortenUrl();
    });
    customAliasInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') shortenUrl();
    });
});