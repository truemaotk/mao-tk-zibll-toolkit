(function () {
    'use strict';
    var box, main, content, breadcrumb, slot;
    var placementObserver, resizeObserver, timeout, resizeFrame;
    var desktop = window.matchMedia('(min-width: 1025px)');
    var parsed = document.readyState !== 'loading';
    function stopPlacement() {
        if (placementObserver) { placementObserver.disconnect(); placementObserver = null; }
        window.clearTimeout(timeout);
    }
    function sync() {
        if (!box || !breadcrumb || !slot || !content) { return; }
        if (desktop.matches) {
            if (breadcrumb.parentNode !== slot) { slot.appendChild(breadcrumb); }
            var outer = box.getBoundingClientRect();
            var inner = content.getBoundingClientRect();
            slot.style.setProperty('--maotk-breadcrumb-left', Math.max(0, inner.left - outer.left).toFixed(2) + 'px');
            slot.style.setProperty('--maotk-breadcrumb-width', Math.max(0, inner.width).toFixed(2) + 'px');
        } else {
            if (breadcrumb.parentNode !== content) { content.insertBefore(breadcrumb, content.querySelector('article.article') || content.firstChild); }
            slot.style.removeProperty('--maotk-breadcrumb-left');
            slot.style.removeProperty('--maotk-breadcrumb-width');
        }
    }
    function schedule() { window.cancelAnimationFrame(resizeFrame); resizeFrame = window.requestAnimationFrame(sync); }
    function place() {
        if (box && document.documentElement.contains(box)) { sync(); return true; }
        var wrapper = document.getElementById('maotk-top-info-fallback');
        var candidate = wrapper && wrapper.querySelector('.maotk-top-info');
        main = document.querySelector('main.container');
        content = main && main.querySelector('.content-layout');
        // Unknown theme layouts and no-JS retain the native title and navigation.
        if (!candidate || !main || !main.parentNode || !content) { return false; }
        if (wrapper.getAttribute('data-maotk-origin') === 'footer') {
            var paints = window.performance && window.performance.getEntriesByType ? window.performance.getEntriesByType('paint') : null;
            if (!paints || paints.length || parsed) { stopPlacement(); return false; }
        }
        // While parsing, wait until the native article header and breadcrumb exist.
        // This avoids choosing a fallback just before the real breadcrumb is parsed.
        if (!parsed && !main.querySelector('article.article .article-header > .article-title, .single-head-cover .article-title')) { return false; }
        var fallback = wrapper.querySelector('[data-maotk-fallback-breadcrumb]');
        breadcrumb = main.querySelector('.breadcrumb');
        if (!breadcrumb) { breadcrumb = fallback; }
        else if (fallback) { fallback.remove(); }
        box = candidate;
        slot = box.querySelector('.maotk-top-info__breadcrumb-slot');
        if (breadcrumb) { breadcrumb.classList.add('maotk-top-info-breadcrumb'); }
        main.parentNode.insertBefore(box, main);
        // Extract the fallback breadcrumb before removing its hidden wrapper.
        if (breadcrumb && wrapper.contains(breadcrumb)) { content.insertBefore(breadcrumb, content.firstChild); }
        wrapper.remove();
        sync();
        document.body.classList.add('mao-tk-top-info-ready', 'moxing-top-info-fullwidth');
        if (window.MaoTKTopInfoReveal) { window.MaoTKTopInfoReveal.start(box); }
        if (window.ResizeObserver) {
            resizeObserver = new ResizeObserver(schedule);
            [box, main, content].forEach(function (element) { resizeObserver.observe(element); });
        }
        stopPlacement();
        return true;
    }
    function initialize() {
        if (place()) { return; }
        if (window.MutationObserver && document.documentElement) {
            placementObserver = new MutationObserver(place);
            placementObserver.observe(document.documentElement, { childList: true, subtree: true });
            timeout = window.setTimeout(stopPlacement, 6000);
        }
    }
    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('.maotk-top-info__smooth-scroll');
        if (!link || !box || !box.contains(link)) { return; }
        var href = link.getAttribute('href');
        var target = href && href.charAt(0) === '#' && document.getElementById(href.slice(1));
        if (!target) { return; }
        event.preventDefault();
        target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    });
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('pagehide', function () { stopPlacement(); if (resizeObserver) { resizeObserver.disconnect(); } });
    window.addEventListener('pageshow', function () { if (box && resizeObserver) { [box, main, content].forEach(function (element) { resizeObserver.observe(element); }); schedule(); } });
    if (!parsed) { document.addEventListener('DOMContentLoaded', function () { parsed = true; place(); }, { once: true }); }
    initialize();
}());
