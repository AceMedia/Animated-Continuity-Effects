(function () {
  'use strict';

  const config = window.ACE_VIEW_TRANSITIONS || {};
  const cssVars = config.cssVars || {};
  const excludeList = Array.isArray(config.excludeList) ? config.excludeList : [];
  const sameElementRules = Array.isArray(config.sameElementRules) ? config.sameElementRules : [];
  const nameScope = typeof config.nameScope === 'string' && config.nameScope ? config.nameScope : 'post';
  const enableRootTransition = config.enableRootTransition !== false;
  const respectReducedMotion = config.respectReducedMotion !== false;
  const transitionMode = config.transitionMode || 'cross-document';
  const debugEnabled = config.debug === true;
  const contentSelectors = ['.wp-site-blocks', '#page', 'main', 'body'];

  const supportsViewTransitions = 'startViewTransition' in document;

  function log() {
    if (!debugEnabled || !window.console || typeof window.console.debug !== 'function') {
      return;
    }

    const args = Array.prototype.slice.call(arguments);
    args.unshift('[ACE]');
    window.console.debug.apply(window.console, args);
  }

  function isReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function applyCssVariables() {
    const root = document.documentElement;
    Object.keys(cssVars).forEach((key) => {
      if (cssVars[key]) {
        root.style.setProperty(key, String(cssVars[key]));
      }
    });
  }

  function slugify(value) {
    return String(value)
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 40);
  }

  function hasExcludedMatch(root) {
    if (!root || !excludeList.length) {
      return false;
    }

    return excludeList.some((selector) => {
      try {
        return !!root.querySelector(selector);
      } catch (error) {
        return false;
      }
    });
  }

  function scopeToken(rule, index) {
    return slugify([nameScope, rule.scope || 'post', rule.label || index].join('-'));
  }

  function uniqueMatchName(base, index) {
    return 'ace-' + base + '-' + index;
  }

  function applySharedElementNames() {
    const seen = new Set();

    sameElementRules.forEach((rule, ruleIndex) => {
      if (!rule || !rule.selector) {
        return;
      }

      let elements = [];
      try {
        elements = Array.from(document.querySelectorAll(rule.selector));
      } catch (error) {
        return;
      }

      elements.forEach((element, elementIndex) => {
        const token = uniqueMatchName(scopeToken(rule, ruleIndex), elementIndex);
        if (seen.has(token)) {
          return;
        }
        seen.add(token);
        element.style.viewTransitionName = token;
        if ('viewTransitionClass' in element.style) {
          element.style.viewTransitionClass = 'ace-' + slugify(rule.label || 'shared-element');
        }
      });
    });
  }

  function enableDocumentTransition() {
    if (!supportsViewTransitions || !enableRootTransition) {
      return;
    }

    document.documentElement.style.viewTransitionName = 'root';
  }

  function setDocumentHints() {
    document.documentElement.classList.add('ace-view-transitions-enabled');
    document.documentElement.style.setProperty('--ace-view-transition-enabled', '1');
  }

  function setupNavigationGuards() {
    window.addEventListener('pageswap', (event) => {
      if (!event || !event.viewTransition) {
        return;
      }

      if (hasExcludedMatch(document)) {
        event.viewTransition.skipTransition();
      }
    });

    window.addEventListener('pagereveal', (event) => {
      if (!event || !event.viewTransition) {
        return;
      }

      if (respectReducedMotion && isReducedMotion()) {
        event.viewTransition.skipTransition();
      }
    });
  }

  function isEligibleLink(anchor) {
    if (!anchor || anchor.tagName !== 'A') {
      return false;
    }

    if (anchor.target === '_blank' || anchor.hasAttribute('download')) {
      log('skip: target/download', anchor.href);
      return false;
    }

    if (anchor.closest('[aria-hidden="true"], [inert]')) {
      log('skip: hidden/inert', anchor.href);
      return false;
    }

    if (anchor.classList && anchor.classList.contains('ab-item')) {
      log('skip: admin bar', anchor.href);
      return false;
    }

    const href = anchor.getAttribute('href');
    if (!href || href.charAt(0) === '#') {
      return false;
    }

    let url;
    try {
      url = new URL(href, window.location.href);
    } catch (error) {
      return false;
    }

    if (url.origin !== window.location.origin) {
      log('skip: external URL', url.href);
      return false;
    }

    if (url.pathname === window.location.pathname && url.search === window.location.search) {
      log('skip: hash-only/same-page', url.href);
      return false;
    }

    if (/(?:^|\/)(wp-admin|wp-login\.php|wp-json)(?:\/|$)/.test(url.pathname) || /[?&](preview|customize)=/i.test(url.search + url.hash)) {
      log('skip: wp-admin/wp-login/wp-json/preview/customizer', url.href);
      return false;
    }

    if (excludeList.length) {
      for (let i = 0; i < excludeList.length; i += 1) {
        const selector = excludeList[i];
        if (!selector) {
          continue;
        }

        try {
          if (anchor.matches(selector) || anchor.closest(selector)) {
            log('skip: excluded selector', selector, url.href);
            return false;
          }
        } catch (error) {
          continue;
        }
      }
    }

    return true;
  }

  function getReplacementElement(nextDoc) {
    for (let i = 0; i < contentSelectors.length; i += 1) {
      try {
        const match = nextDoc.querySelector(contentSelectors[i]);
        if (match) {
          return match;
        }
      } catch (error) {
        // ignore invalid selectors and keep searching
      }
    }

    return nextDoc.body || nextDoc.documentElement;
  }

  function reinitializeFromDocument(doc) {
    applyCssVariables();
    setDocumentHints();
    enableDocumentTransition();
    applySharedElementNames();
    setupNavigationGuards();
    return doc;
  }

  function swapDocument(nextDoc) {
    const replacement = getReplacementElement(nextDoc);
    const nextBody = nextDoc.body ? nextDoc.body.cloneNode(true) : null;

    if (nextDoc.title) {
      document.title = nextDoc.title;
    }

    if (nextBody) {
      document.body.replaceWith(nextBody);
    } else if (replacement && replacement !== nextDoc.documentElement) {
      document.body.innerHTML = replacement.innerHTML;
    }

    // Inline scripts are not re-executed here. This is the first stable pass
    // for page-shell swaps and avoids duplicate execution on each navigation.
    return reinitializeFromDocument(nextDoc);
  }

  function loadPage(url, shouldReplaceHistory) {
    return window.fetch(url.href, {
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-ACE-View-Transition': '1',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Request failed: ' + response.status);
        }

        return response.text();
      })
      .then((html) => {
        const nextDoc = new DOMParser().parseFromString(html, 'text/html');
        const update = () => swapDocument(nextDoc);

        if (supportsViewTransitions) {
          return document.startViewTransition(update).finished.then(() => ({
            url: url.href,
            title: nextDoc.title || '',
          }));
        }

        document.documentElement.classList.add('ace-view-transition-fade');
        update();
        document.documentElement.classList.remove('ace-view-transition-fade');

        return Promise.resolve({
          url: url.href,
          title: nextDoc.title || '',
        });
      })
      .then((result) => {
        if (shouldReplaceHistory) {
          window.history.replaceState({ aceViewTransition: true, url: result.url }, '', result.url);
        } else {
          window.history.pushState({ aceViewTransition: true, url: result.url }, '', result.url);
        }

        document.dispatchEvent(new CustomEvent('ace:view-transition:navigated', { detail: { url: result.url } }));
        log('navigated', result.url);
      })
      .catch((error) => {
        log('navigation failed, falling back', error && error.message ? error.message : error);
        window.location.href = url.href;
      });
  }

  function onClick(event) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return;
    }

    if (transitionMode !== 'same-document') {
      return;
    }

    if (respectReducedMotion && isReducedMotion()) {
      log('skip: reduced motion');
      return;
    }

    const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!anchor || !isEligibleLink(anchor)) {
      return;
    }

    event.preventDefault();
    log('intercepting navigation', anchor.href);
    loadPage(new URL(anchor.getAttribute('href'), window.location.href), false);
  }

  function onPopState() {
    if (transitionMode !== 'same-document') {
      return;
    }

    const url = new URL(window.location.href);
    log('popstate', url.href);
    loadPage(url, true);
  }

  function init() {
    applyCssVariables();
    setDocumentHints();
    if (supportsViewTransitions) {
      enableDocumentTransition();
      applySharedElementNames();
      setupNavigationGuards();
    }

    document.addEventListener('click', onClick, true);
    window.addEventListener('popstate', onPopState);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
