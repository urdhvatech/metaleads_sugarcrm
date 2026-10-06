/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */
let currentNonce = null;

function setCSPNonce(nonce) {
    if (nonce && typeof nonce === 'string') {
        currentNonce = nonce;
    }
}

// === 2. Patch XMLHttpRequest and fetch to get X-Nonce ===
(function() {
    const origOpen = XMLHttpRequest.prototype.open;
    const origSend = XMLHttpRequest.prototype.send;

    // Only read X-Nonce from same-origin responses. Cross-origin responses
    // (e.g. Aptrinsic/Gainsight) don't expose this header, and
    // attempting to read it triggers a browser console warning
    // ("Refused to get unsafe header"). Skipping cross-origin requests also
    // adds a security layer: only our own server should set the CSP nonce,
    // regardless of CORS behavior.
    XMLHttpRequest.prototype.open = function(method, url, ...rest) {
        try {
            var resolved = new URL(url, window.location.href);
            this._isSameOrigin = resolved.origin === window.location.origin;
        } catch {
            this._isSameOrigin = false;
        }
        return origOpen.call(this, method, url, ...rest);
    };

    XMLHttpRequest.prototype.send = function(...args) {
        if (this._isSameOrigin) {
            this.addEventListener('load', function() {
                const xNonce = this.getResponseHeader('X-Nonce');
                if (xNonce) {
                    setCSPNonce(xNonce);
                    activateSpecificDataEventHandlers(document.body); // handle new elements
                }
            });
        }
        return origSend.apply(this, args);
    };
})();

(function() {
    const originalFetch = window.fetch;

    window.fetch = async function(...args) {
        const response = await originalFetch.apply(this, args);
        const xNonce = response.headers.get('X-Nonce');
        if (xNonce) {
            setCSPNonce(xNonce);
            activateSpecificDataEventHandlers(document.body); // handle new elements
        }
        return response;
    };
})();

// === 3. Initialization ===
function initInlineHandlers(initialNonce) {
    setCSPNonce(initialNonce);

    document.addEventListener('DOMContentLoaded', function() {
        activateSpecificDataEventHandlers(document.body);

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        activateSpecificDataEventHandlers(node);
                    }
                }
            }
        });

        observer.observe(document.body, {childList: true, subtree: true});
    });
}

// === 4. Handling of data-on*-{nonce} ===
function activateSpecificDataEventHandlers(root = document.body) {
    if (!currentNonce) return;

    const ATTRS = [
        'data-onclick', 'data-onmouseover', 'data-onmouseout', 'data-onmousedown',
        'data-onfocus', 'data-onblur', 'data-onload', 'data-onsubmit', 'data-onchange',
        'data-onkeyup', 'data-onkeydown', 'data-onkeypress', 'data-ondblclick',
    ];

    const selector = ATTRS.map((attr) => `[${attr}-${currentNonce}]`).join(',');
    const aTagSelector = 'a[href^="javascript:"]';

    const elementsToProcess = new Set();

    if (root.matches?.(selector) || root.matches?.(aTagSelector)) {
        elementsToProcess.add(root);
    }

    root.querySelectorAll(selector).forEach((el) => elementsToProcess.add(el));
    root.querySelectorAll(aTagSelector).forEach((el) => elementsToProcess.add(el));

    elementsToProcess.forEach((el) => processElement(el, ATTRS));
}

/**
 * Checks if a href value is a JavaScript no-op (void(0) only).
 * Only matches pure no-op patterns, not hrefs with actual logic.
 *
 * @param {string} href - The href attribute value
 * @returns {boolean} True if href is a pure JavaScript no-op
 */
function isJavaScriptNoOp(href) {
    if (!href || !href.toLowerCase().startsWith('javascript:')) {
        return false;
    }

    const jsCode = href.substring('javascript:'.length).trim();
    // Only match pure void(0) patterns, not code with additional logic
    return /^void\s*\(?\s*0\s*\)?;?\s*$/i.test(jsCode);
}

function processElement(el, ATTRS) {
    if (!currentNonce) return;

    // --- handle javascript:void(0) href only
    // Let CSP control other javascript: executions
    if (el.tagName === 'A') {
        const href = el.getAttribute('href');
        if (isJavaScriptNoOp(href)) {
            el.removeAttribute('href');
            // To maintain accessibility and expected behavior, ensure the element is still focusable
            // and can be triggered by keyboard if it's meant to be interactive.
            if (!el.hasAttribute('tabindex')) {
                el.setAttribute('tabindex', '0');
            }
            if (!el.hasAttribute('role')) {
                el.setAttribute('role', 'button');
            }
        }
    }

    // --- Add listeners for data-on* attributes
    ATTRS.forEach((attr) => {
        const fullAttr = `${attr}-${currentNonce}`;
        if (el.hasAttribute(fullAttr)) {
            const eventType = attr.replace(/^data-on/, '').toLowerCase();
            const code = el.getAttribute(fullAttr);

            const flagName = `data-${eventType}-handler-bound`;
            if (!el.hasAttribute(flagName)) {
                el.addEventListener(eventType, function(event) {
                    try {
                        // We need to intercept the return value for 'submit' events,
                        // as well as 'click' events on form submit buttons,
                        // to properly handle 'return false;' to cancel the action.
                        const isSubmitRelated =
                            eventType === 'submit' ||
                            (eventType === 'click' && el.type === 'submit');

                        if (isSubmitRelated) {
                            // Generate function that will execute the code and return result
                            const result = new Function('event', `return (function() { ${code} }).call(this, event);`).call(this, event);

                            // If the result is 'false' then prevent default actions (e.g submitting the form)
                            if (result === false) {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                        } else {
                            new Function('event', code).call(this, event);
                        }
                    } catch (e) {
                        console.error(`Handling error ${fullAttr}:`, e);
                    }
                });
                el.setAttribute(flagName, '1');
            }
        }
    });
}

// Expose for testing
if (typeof window !== 'undefined') {
    window.SUGAR = window.SUGAR || {};
    window.SUGAR.csp = window.SUGAR.csp || {};
    window.SUGAR.csp.isJavaScriptNoOp = isJavaScriptNoOp;
}

const originalConfirm = window.confirm;

window.confirm = function(msg) {
    const confirmed = originalConfirm(msg);

    if (confirmed) {
        return true;
    }

    event.preventDefault();

    return false;
}
