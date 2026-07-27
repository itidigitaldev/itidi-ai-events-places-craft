(function () {
    'use strict';

    var itidiAiListingRetryCount = 0;
    var itidiAiListingMaxRetries = 20;
    var itidiAiListingRetryDelay = 500;
    var itidiAiDelayedScriptsTriggered = false;

    function itidiAiTriggerDelayedScripts() {
        if (itidiAiDelayedScriptsTriggered) {
            return;
        }

        itidiAiDelayedScriptsTriggered = true;

        [
            'mousemove',
            'touchstart',
            'keydown',
            'wheel',
            'scroll'
        ].forEach(function (eventName) {
            try {
                window.dispatchEvent(
                    new Event(eventName, {
                        bubbles: true,
                        cancelable: true
                    })
                );

                document.dispatchEvent(
                    new Event(eventName, {
                        bubbles: true,
                        cancelable: true
                    })
                );
            } catch (error) {
                // Ignore event dispatch errors.
            }
        });
    }

    function itidiAiInitListing(instance) {
        var container;
        var initMethod;

        if (!instance || instance.itidiAiInitialized || !instance.containerId || !instance.initMethod) {
            return;
        }

        container = document.getElementById(instance.containerId);
        initMethod = instance.initMethod;

        if (!container) {
            return;
        }

        if (
            window.IMGoingSSR &&
            typeof window.IMGoingSSR[initMethod] === 'function'
        ) {
            window.IMGoingSSR[initMethod](container, instance.bootstrap || {});
            instance.itidiAiInitialized = true;
        }
    }

    function itidiAiHasPendingListings(instances) {
        return instances.some(function (instance) {
            return instance && !instance.itidiAiInitialized;
        });
    }

    function itidiAiRunListingInit() {
        var data = window.itidiAiListingInit || {};
        var instances = data.instances || [];

        if (!instances.length) {
            return;
        }

        instances.forEach(itidiAiInitListing);

        if (!itidiAiHasPendingListings(instances)) {
            return;
        }

        /*
         * A caching/optimization plugin may hold the SSR script until user interaction.
         * Trigger common interaction events once, then retry listing init for a few seconds.
         */
        if (1 === itidiAiListingRetryCount) {
            itidiAiTriggerDelayedScripts();
        }

        if (itidiAiListingRetryCount >= itidiAiListingMaxRetries) {
            return;
        }

        itidiAiListingRetryCount += 1;

        setTimeout(function () {
            itidiAiRunListingInit();
        }, itidiAiListingRetryDelay);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', itidiAiRunListingInit);
    } else {
        itidiAiRunListingInit();
    }

    window.addEventListener('load', function () {
        setTimeout(function () {
            itidiAiRunListingInit();
        }, 800);
    });
}());
