(function () {
    'use strict';

    var config = window.itiEventShadowDom || {};
    var instances = config.instances || [];
    var cssAssets = config.cssAssets || [];

    function addShadowBaseStyle(shadow) {
        var baseStyle = document.createElement('style');

        baseStyle.textContent = [
            ':host {',
                'all: initial;',
                'font-size: 16px;',
            '}',
            '*, *::before, *::after {',
                'box-sizing: border-box;',
            '}'
        ].join('');

        shadow.appendChild(baseStyle);
    }

    function addShadowCss(shadow, href) {
        return new Promise(function (resolve) {
            if (!href) {
                resolve();
                return;
            }

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = resolve;
            link.onerror = resolve;
            shadow.appendChild(link);
        });
    }

    function cloneSourceToShadow(source, shadow) {
        Array.prototype.slice.call(source.childNodes).forEach(function (node) {
            if (
                node.nodeType === 1 &&
                node.tagName &&
                node.tagName.toLowerCase() === 'script'
            ) {
                return;
            }

            shadow.appendChild(node.cloneNode(true));
        });
    }

    function setupInstance(instance) {
        var host = document.getElementById(instance.shadowId);
        var source = document.getElementById(instance.sourceId);

        if (!host || !source || host.shadowRoot) {
            return Promise.resolve();
        }

        var shadow = host.attachShadow({ mode: 'open' });

        addShadowBaseStyle(shadow);

        return cssAssets.reduce(function (promise, href) {
            return promise.then(function () {
                return addShadowCss(shadow, href);
            });
        }, Promise.resolve()).then(function () {
            cloneSourceToShadow(source, shadow);

            host.style.display = '';
            source.style.display = 'none';

            if (
                window.IMGoingSSR &&
                typeof window.IMGoingSSR.initEventDetail === 'function'
            ) {
                window.IMGoingSSR.initEventDetail(shadow, instance.bootstrap || {});
            }
        });
    }

    instances.reduce(function (promise, instance) {
        return promise.then(function () {
            return setupInstance(instance);
        });
    }, Promise.resolve());
})();
