export function toFancyboxPublicUrl(url) {
    if (!url) return url;
    if (url.endsWith('/public')) return url;
    return url.replace(/\/w=\d+$/, '/public');
}

export function prepareFancyboxPublicLinks(selector) {
    document.querySelectorAll(selector).forEach((el) => {
        const href = el.getAttribute('href');
        if (href) {
            el.setAttribute('href', toFancyboxPublicUrl(href));
        }
    });
}
