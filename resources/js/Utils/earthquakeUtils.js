export const formatScale = (scale) => {
    const s = parseInt(scale);
    if (isNaN(s) || s < 10) return "-";
    if (s >= 70) return "7";
    if (s >= 60) return "6⁺";
    if (s >= 55) return "6⁻";
    if (s >= 50) return "5⁺";
    if (s >= 45) return "5⁻";
    if (s >= 40) return "4";
    if (s >= 30) return "3";
    if (s >= 20) return "2";
    if (s >= 10) return "1";
    return "-";
};

export const formatScaleJP = (scale) => {
    const s = parseInt(scale);
    if (isNaN(s) || s < 10) return "-";
    if (s >= 70) return "7";
    if (s >= 60) return "6強";
    if (s >= 55) return "6弱";
    if (s >= 50) return "5強";
    if (s >= 45) return "5弱";
    if (s >= 40) return "4";
    if (s >= 30) return "3";
    if (s >= 20) return "2";
    if (s >= 10) return "1";
    return "-";
};

export const getShindoColor = (scale) => {
    const s = parseInt(scale);
    if (s >= 70) return '#c850c8';
    if (s >= 60) return '#ff6b6b';
    if (s >= 55) return '#ff8e53';
    if (s >= 50) return '#ffad5a';
    if (s >= 45) return '#ffcf77';
    if (s >= 40) return '#fff27d';
    if (s >= 30) return '#98ee99';
    if (s >= 20) return '#81d4fa';
    if (s >= 10) return '#bbdefb';
    return 'transparent';
};

export const formatFullTime = (timeStr) => {
    if (!timeStr) return "";
    const [date, time] = timeStr.split(' ');
    const [y, m, d] = date.split('/');
    return `${y}年${m}月${d}日 ${time.substring(0, 5)}ごろ`;
};

export const formatDepth = (depth) => {
    if (depth === '0' || depth === 0 || depth === '-1' || !depth || depth === '-') {
        return 'ごく浅い';
    }
    return `${depth}km`;
};
