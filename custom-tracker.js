// custom-tracker.js - শেষ সংস্করণ
(function() {
    const SESSION_TIMEOUT = 30 * 60 * 1000; // 30 মিনিট
    const API_URL = '/track.php';

    // ইউনিক আইডি জেনারেটর (UUID v4)
    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    // কুকি ফাংশন
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }
    function setCookie(name, value, days = 365) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${value}; expires=${expires}; path=/; SameSite=Lax; Secure`;
    }

    // ব্রাউজার ফিঙ্গারপ্রিন্ট (SHA-256)
    async function getFingerprint() {
        const components = {
            ua: navigator.userAgent,
            lang: navigator.language,
            platform: navigator.platform,
            screen: `${screen.width}x${screen.height}x${screen.colorDepth}`,
            tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
            cores: navigator.hardwareConcurrency || 0,
            memory: navigator.deviceMemory || 0,
            touch: 'ontouchstart' in window,
            canvas: await getCanvasFingerprint() // অতিরিক্ত নির্ভুলতার জন্য
        };
        const str = JSON.stringify(components);
        const encoder = new TextEncoder();
        const hash = await crypto.subtle.digest('SHA-256', encoder.encode(str));
        return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    // ক্যানভাস ফিঙ্গারপ্রিন্ট (অপশনাল, ব্রাউজার ব্লক করলে error ধরা)
    function getCanvasFingerprint() {
        return new Promise((resolve) => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 200;
                canvas.height = 50;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#f60';
                ctx.fillRect(0, 0, 200, 50);
                ctx.fillStyle = '#069';
                ctx.font = '14px Arial';
                ctx.fillText('fingerprint', 10, 30);
                const data = canvas.toDataURL();
                resolve(data.substring(0, 100));
            } catch(e) { resolve('canvas_error'); }
        });
    }

    // সেশন ম্যানেজমেন্ট
    let sessionId = null;
    function getSession() {
        const now = Date.now();
        let sess = localStorage.getItem('ca_session_id');
        let last = localStorage.getItem('ca_last_activity');
        if (!sess || !last || (now - parseInt(last)) > SESSION_TIMEOUT) {
            sess = uuid();
            localStorage.setItem('ca_session_id', sess);
        }
        localStorage.setItem('ca_last_activity', now);
        sessionId = sess;
        return sess;
    }

    // ট্র্যাকিং ফাংশন
    async function track(eventType = 'pageview', extra = {}) {
        try {
            let cookieId = getCookie('ca_visitor_id');
            if (!cookieId) {
                cookieId = uuid();
                setCookie('ca_visitor_id', cookieId);
            }
            let localId = localStorage.getItem('ca_local_id');
            if (!localId) {
                localId = uuid();
                localStorage.setItem('ca_local_id', localId);
            }
            const fingerprint = await getFingerprint();
            const session = getSession();

            // ইউজার এজেন্ট পার্সিং
            const ua = navigator.userAgent;
            let browser = 'Unknown', os = 'Unknown', device = 'Desktop';
            if (ua.includes('Chrome')) browser = 'Chrome';
            else if (ua.includes('Firefox')) browser = 'Firefox';
            else if (ua.includes('Safari')) browser = 'Safari';
            else if (ua.includes('Edg')) browser = 'Edge';
            else if (ua.includes('OPR')) browser = 'Opera';
            if (ua.includes('Win')) os = 'Windows';
            else if (ua.includes('Mac')) os = 'macOS';
            else if (ua.includes('Linux')) os = 'Linux';
            else if (ua.includes('Android')) os = 'Android';
            else if (/iPhone|iPad|iPod/.test(ua)) os = 'iOS';
            else if (ua.includes('CrOS')) os = 'ChromeOS';

            if (/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i.test(ua)) device = 'Tablet';
            else if (/Mobile|iPhone|Android|BlackBerry|Opera Mini|IEMobile/i.test(ua)) device = 'Mobile';

            const payload = {
                event_type: eventType,
                cookie_id: cookieId,
                local_storage_id: localId,
                fingerprint_hash: fingerprint,
                session_id: session,
                url: location.href,
                referrer: document.referrer,
                user_agent: ua,
                browser: browser,
                os: os,
                device_type: device,
                screen_resolution: `${screen.width}x${screen.height}`,
                language: navigator.language,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                click_position: extra.clickX ? { x: extra.clickX, y: extra.clickY } : null
            };
            await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                keepalive: true
            });
        } catch(e) { console.warn('Track error', e); }
    }

    // ইভেন্ট লিসেনার
    window.addEventListener('load', () => setTimeout(() => track('pageview'), 100));
    document.addEventListener('click', (e) => {
        track('click', { clickX: e.clientX, clickY: e.clientY });
    });
    // SPA নেভিগেশন হ্যান্ডলিং
    let lastUrl = location.href;
    new MutationObserver(() => {
        if (location.href !== lastUrl) {
            lastUrl = location.href;
            track('pageview');
        }
    }).observe(document, { subtree: true, childList: true });
    // সেশন রিফ্রেশ
    setInterval(() => getSession(), 60000);
})();